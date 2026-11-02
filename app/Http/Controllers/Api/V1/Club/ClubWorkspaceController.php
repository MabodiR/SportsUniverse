<?php

namespace App\Http\Controllers\Api\V1\Club;

use App\Domain\Opportunities\Models\OpportunityApplication;
use App\Events\NotificationRequested;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClubWorkspaceController extends Controller
{
    private const PLAYER_STATUSES = ['active', 'trial', 'injured', 'inactive', 'released'];

    private function workspace(Request $r): object
    {
        $user = $r->user();
        $workspace = DB::table('club_workspaces')->where('owner_id', $user->id)->first() ?? DB::table('club_workspaces')->join('club_staff', 'club_staff.workspace_id', '=', 'club_workspaces.id')->where('club_staff.user_id', $user->id)->where('club_staff.status', 'active')->select('club_workspaces.*')->first();
        if (! $workspace && $user->hasAnyRole(['club', 'academy', 'scout', 'agent', 'admin'])) {
            $name = $user->organisationProfile?->organisation_name ?? $user->name;
            $id = DB::table('club_workspaces')->insertGetId(['public_id' => (string) Str::ulid(), 'owner_id' => $user->id, 'name' => $name, 'slug' => Str::slug($name).'-'.$user->id, 'bio' => $user->profile?->bio, 'website' => $user->organisationProfile?->website, 'created_at' => now(), 'updated_at' => now()]);
            $workspace = DB::table('club_workspaces')->find($id);
        }abort_unless($workspace, 403, 'Club and scout tools require an eligible account or staff membership.');

        return $workspace;
    }

    public function overview(Request $r): JsonResponse
    {
        $w = $this->workspace($r);
        $shortlists = DB::table('talent_shortlists')->where('workspace_id', $w->id)->get()->map(function ($list) {
            $list->athletes = DB::table('shortlist_athletes')->join('users', 'users.id', '=', 'shortlist_athletes.athlete_id')->join('user_profiles', 'user_profiles.user_id', '=', 'users.id')->leftJoin('athlete_profiles', 'athlete_profiles.user_id', '=', 'users.id')->leftJoin('sports', 'sports.id', '=', 'athlete_profiles.sport_id')->where('shortlist_id', $list->id)->select('users.id', 'users.name', 'user_profiles.slug', 'user_profiles.city', 'user_profiles.bio', 'sports.name as sport', 'athlete_profiles.position', 'athlete_profiles.playing_level')->get();

            return $list;
        });
        $staff = DB::table('club_staff')->join('users', 'users.id', '=', 'club_staff.user_id')->where('workspace_id', $w->id)->select('users.id', 'users.name', 'users.email', 'club_staff.role', 'club_staff.status')->get();
        $invitations = DB::table('trial_invitations')->join('users', 'users.id', '=', 'trial_invitations.athlete_id')->where('workspace_id', $w->id)->select('trial_invitations.*', 'users.name as athlete_name')->latest('sent_at')->limit(30)->get();
        $players = DB::table('club_workspace_players')
            ->join('users', 'users.id', '=', 'club_workspace_players.athlete_id')
            ->join('user_profiles', 'user_profiles.user_id', '=', 'users.id')
            ->leftJoin('athlete_profiles', 'athlete_profiles.user_id', '=', 'users.id')
            ->leftJoin('sports', 'sports.id', '=', 'athlete_profiles.sport_id')
            ->where('club_workspace_players.workspace_id', $w->id)
            ->select('club_workspace_players.*', 'users.name', 'user_profiles.slug', 'user_profiles.city', 'sports.name as sport', 'athlete_profiles.position', 'athlete_profiles.playing_level')
            ->orderByRaw("case club_workspace_players.status when 'active' then 1 when 'trial' then 2 when 'injured' then 3 when 'inactive' then 4 else 5 end")
            ->orderBy('users.name')
            ->get();
        $canManagePlayers = $this->canManagePlayers($r, $w);

        return response()->json(['data' => ['workspace' => $w, 'permissions' => ['is_owner' => $w->owner_id === $r->user()->id, 'can_manage_staff' => $w->owner_id === $r->user()->id, 'can_manage_players' => $canManagePlayers], 'players' => $players, 'shortlists' => $shortlists, 'staff' => $staff, 'invitations' => $invitations, 'counts' => ['players' => $players->where('status', '!=', 'released')->count(), 'athletes' => DB::table('shortlist_athletes')->join('talent_shortlists', 'talent_shortlists.id', '=', 'shortlist_athletes.shortlist_id')->where('talent_shortlists.workspace_id', $w->id)->count(), 'notes' => DB::table('scouting_notes')->where('workspace_id', $w->id)->count(), 'invitations' => DB::table('trial_invitations')->where('workspace_id', $w->id)->count()]]]);
    }

    public function addPlayer(Request $r): JsonResponse
    {
        $w = $this->workspace($r);
        abort_unless($this->canManagePlayers($r, $w), 403, 'You do not have permission to manage players.');
        $data = $this->validatePlayer($r, true);
        $athlete = User::findOrFail($data['athlete_id']);
        abort_unless($athlete->hasRole('athlete'), 422, 'Only athlete accounts can be added as players.');

        DB::table('club_workspace_players')->updateOrInsert(
            ['workspace_id' => $w->id, 'athlete_id' => $athlete->id],
            [...collect($data)->except('athlete_id')->all(), 'added_by_id' => $r->user()->id, 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json(['message' => 'Player added to your roster.'], 201);
    }

    public function updatePlayer(Request $r, User $athlete): JsonResponse
    {
        $w = $this->workspace($r);
        abort_unless($this->canManagePlayers($r, $w), 403, 'You do not have permission to manage players.');
        abort_unless(DB::table('club_workspace_players')->where(['workspace_id' => $w->id, 'athlete_id' => $athlete->id])->exists(), 404, 'Player is not on this roster.');
        $data = $this->validatePlayer($r, false);
        DB::table('club_workspace_players')->where(['workspace_id' => $w->id, 'athlete_id' => $athlete->id])->update([...$data, 'updated_at' => now()]);

        return response()->json(['message' => 'Player details updated.']);
    }

    public function removePlayer(Request $r, User $athlete): JsonResponse
    {
        $w = $this->workspace($r);
        abort_unless($this->canManagePlayers($r, $w), 403, 'You do not have permission to manage players.');
        $removed = DB::table('club_workspace_players')->where(['workspace_id' => $w->id, 'athlete_id' => $athlete->id])->delete();
        abort_unless($removed, 404, 'Player is not on this roster.');

        return response()->json(['message' => 'Player removed from your roster.']);
    }

    private function validatePlayer(Request $r, bool $creating): array
    {
        return $r->validate([
            'athlete_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:users,id'],
            'status' => [$creating ? 'sometimes' : 'required', 'string', 'in:'.implode(',', self::PLAYER_STATUSES)],
            'squad' => ['nullable', 'string', 'max:80'],
            'shirt_number' => ['nullable', 'string', 'max:12'],
            'joined_on' => ['nullable', 'date'],
            'contract_ends_on' => ['nullable', 'date', 'after_or_equal:joined_on'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function canManagePlayers(Request $r, object $workspace): bool
    {
        if ((int) $workspace->owner_id === (int) $r->user()->id) {
            return true;
        }

        return DB::table('club_staff')->where('workspace_id', $workspace->id)->where('user_id', $r->user()->id)->where('status', 'active')->whereIn('role', ['manager', 'scout', 'coach'])->exists();
    }

    public function update(Request $r): JsonResponse
    {
        $w = $this->workspace($r);
        abort_unless($w->owner_id === $r->user()->id, 403);
        $d = $r->validate(['name' => ['required', 'string', 'max:160'], 'bio' => ['nullable', 'string', 'max:5000'], 'website' => ['nullable', 'url', 'max:255']]);
        DB::table('club_workspaces')->where('id', $w->id)->update([...$d, 'updated_at' => now()]);

        return response()->json(['message' => 'Club page updated.']);
    }

    public function createShortlist(Request $r): JsonResponse
    {
        $w = $this->workspace($r);
        $d = $r->validate(['name' => ['required', 'string', 'max:120']]);
        $id = DB::table('talent_shortlists')->insertGetId(['workspace_id' => $w->id, 'name' => $d['name'], 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id, 'name' => $d['name']]], 201);
    }

    public function addAthlete(Request $r, int $id): JsonResponse
    {
        $w = $this->workspace($r);
        abort_unless(DB::table('talent_shortlists')->where('id', $id)->where('workspace_id', $w->id)->exists(), 404);
        $d = $r->validate(['athlete_id' => ['required', 'exists:users,id']]);
        DB::table('shortlist_athletes')->insertOrIgnore(['shortlist_id' => $id, 'athlete_id' => $d['athlete_id'], 'added_by_id' => $r->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Athlete shortlisted.']);
    }

    public function note(Request $r): JsonResponse
    {
        $w = $this->workspace($r);
        $d = $r->validate(['athlete_id' => ['required', 'exists:users,id'], 'note' => ['required', 'string', 'max:10000'], 'rating' => ['nullable', 'integer', 'between:1,10']]);
        $id = DB::table('scouting_notes')->insertGetId(['workspace_id' => $w->id, 'athlete_id' => $d['athlete_id'], 'author_id' => $r->user()->id, 'note' => $d['note'], 'rating' => $d['rating'] ?? null, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['data' => ['id' => $id, ...$d]], 201);
    }

    public function notes(Request $r, int $athlete): JsonResponse
    {
        $w = $this->workspace($r);

        return response()->json(['data' => DB::table('scouting_notes')->join('users', 'users.id', '=', 'scouting_notes.author_id')->where('workspace_id', $w->id)->where('athlete_id', $athlete)->select('scouting_notes.*', 'users.name as author_name')->latest()->get()]);
    }

    public function compare(Request $r): JsonResponse
    {
        $this->workspace($r);
        $ids = $r->validate(['athlete_ids' => ['required', 'array', 'between:2,4'], 'athlete_ids.*' => ['integer', 'exists:users,id']])['athlete_ids'];
        $items = User::whereIn('id', $ids)->with('profile', 'athleteProfile.sport', 'athleteProfile.taxonomyPosition')->withCount('followers')->withSum('videos', 'views_count')->get()->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'slug' => $u->profile?->slug, 'age' => $u->profile?->date_of_birth?->age, 'city' => $u->profile?->city, 'sport' => $u->athleteProfile?->sport?->name, 'position' => $u->athleteProfile?->taxonomyPosition?->name, 'level' => $u->athleteProfile?->playing_level, 'height' => $u->athleteProfile?->height_cm, 'weight' => $u->athleteProfile?->weight_kg, 'followers' => $u->followers_count, 'views' => (int) $u->videos_sum_views_count]);

        return response()->json(['data' => $items]);
    }

    public function invite(Request $r): JsonResponse
    {
        $w = $this->workspace($r);
        $d = $r->validate(['athlete_id' => ['required', 'exists:users,id'], 'opportunity_id' => ['nullable', 'exists:opportunities,id'], 'title' => ['required', 'string', 'max:200'], 'message' => ['required', 'string', 'max:5000']]);
        $id = DB::table('trial_invitations')->insertGetId(['public_id' => (string) Str::ulid(), 'workspace_id' => $w->id, 'athlete_id' => $d['athlete_id'], 'sent_by_id' => $r->user()->id, 'opportunity_id' => $d['opportunity_id'] ?? null, 'title' => $d['title'], 'message' => $d['message'], 'status' => 'sent', 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        NotificationRequested::dispatch((int) $d['athlete_id'], 'opportunities', ['event' => 'trial_invitation', 'invitation_id' => $id, 'club_name' => $w->name, 'title' => $d['title']]);

        return response()->json(['data' => ['id' => $id, 'status' => 'sent']], 201);
    }

    public function staff(Request $r): JsonResponse
    {
        $w = $this->workspace($r);
        abort_unless($w->owner_id === $r->user()->id, 403);
        $d = $r->validate(['email' => ['required', 'email', 'exists:users,email'], 'role' => ['required', 'in:manager,scout,coach,analyst']]);
        $staff = User::where('email', $d['email'])->firstOrFail();
        abort_if($staff->id === $r->user()->id, 422);
        DB::table('club_staff')->updateOrInsert(['workspace_id' => $w->id, 'user_id' => $staff->id], ['invited_by_id' => $r->user()->id, 'role' => $d['role'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => 'Staff account added.'], 201);
    }

    public function updateStaff(Request $r, User $staff): JsonResponse
    {
        $w = $this->workspace($r);
        abort_unless($w->owner_id === $r->user()->id, 403, 'Only the workspace owner can manage staff access.');
        abort_unless(DB::table('club_staff')->where(['workspace_id' => $w->id, 'user_id' => $staff->id])->exists(), 404, 'Staff account not found.');
        $data = $r->validate(['role' => ['required', 'in:manager,scout,coach,analyst'], 'status' => ['required', 'in:active,inactive']]);
        DB::table('club_staff')->where(['workspace_id' => $w->id, 'user_id' => $staff->id])->update([...$data, 'updated_at' => now()]);

        return response()->json(['message' => 'Staff access updated.', 'data' => ['id' => $staff->id, ...$data]]);
    }

    public function removeStaff(Request $r, User $staff): JsonResponse
    {
        $w = $this->workspace($r);
        abort_unless($w->owner_id === $r->user()->id, 403, 'Only the workspace owner can remove staff access.');
        $removed = DB::table('club_staff')->where(['workspace_id' => $w->id, 'user_id' => $staff->id])->delete();
        abort_unless($removed, 404, 'Staff account not found.');

        return response()->json(['message' => 'Staff access removed.']);
    }

    public function pipeline(Request $r): JsonResponse
    {
        $w = $this->workspace($r);
        $items = OpportunityApplication::whereHas('opportunity', fn ($q) => $q->where('posted_by_id', $w->owner_id))->with('user.profile', 'opportunity')->latest()->get()->map(fn ($a) => ['id' => $a->public_id, 'status' => $a->status, 'athlete' => ['id' => $a->user->id, 'name' => $a->user->name, 'slug' => $a->user->profile?->slug], 'opportunity' => $a->opportunity->title, 'applied_at' => $a->created_at]);

        return response()->json(['data' => $items]);
    }

    public function move(Request $r, OpportunityApplication $application): JsonResponse
    {
        $w = $this->workspace($r);
        abort_unless($application->opportunity()->where('posted_by_id', $w->owner_id)->exists(), 403);
        $d = $r->validate(['status' => ['required', 'in:submitted,reviewing,shortlisted,accepted,rejected']]);
        $application->update(['status' => $d['status'], 'reviewed_at' => now()]);
        NotificationRequested::dispatch($application->user_id, 'opportunities', ['event' => 'opportunity_application_status', 'application_id' => $application->public_id, 'status' => $d['status']]);

        return response()->json(['data' => ['status' => $d['status']]]);
    }
}
