<?php

namespace App\Http\Controllers\Api\V1\Club;

use App\Domain\Opportunities\Models\Opportunity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicClubController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $term = trim((string) ($validated['q'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 20);

        $clubs = DB::table('club_workspaces')
            ->join('users', 'users.id', '=', 'club_workspaces.owner_id')
            ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
            ->where('users.status', 'active')
            ->where(fn ($query) => $query->whereNull('profiles.is_public')->orWhere('profiles.is_public', true))
            ->when($term !== '', fn ($query) => $query->where(fn ($search) => $search
                ->whereRaw('LOWER(club_workspaces.name) LIKE ?', ['%'.mb_strtolower($term).'%'])
                ->orWhereRaw('LOWER(COALESCE(club_workspaces.bio, \'\')) LIKE ?', ['%'.mb_strtolower($term).'%'])
                ->orWhereRaw('LOWER(COALESCE(profiles.city, \'\')) LIKE ?', ['%'.mb_strtolower($term).'%'])))
            ->select(['club_workspaces.id', 'club_workspaces.name', 'club_workspaces.slug', 'club_workspaces.bio', 'club_workspaces.website', 'profiles.city', 'profiles.province', 'profiles.profile_image_path'])
            ->orderBy('club_workspaces.name')
            ->paginate($perPage);

        $workspaceIds = collect($clubs->items())->pluck('id');
        $staffCounts = DB::table('club_staff')->whereIn('workspace_id', $workspaceIds)->where('status', 'active')->selectRaw('workspace_id, COUNT(*) AS total')->groupBy('workspace_id')->pluck('total', 'workspace_id');
        $ownerIds = DB::table('club_workspaces')->whereIn('id', $workspaceIds)->pluck('owner_id', 'id');
        $opportunityCounts = Opportunity::query()->whereIn('posted_by_id', $ownerIds)->where('status', 'published')->where(fn ($query) => $query->whereNull('deadline')->orWhere('deadline', '>=', now()))->selectRaw('posted_by_id, COUNT(*) AS total')->groupBy('posted_by_id')->pluck('total', 'posted_by_id');

        return response()->json([
            'data' => collect($clubs->items())->map(fn ($club) => $this->summary($club, (int) ($staffCounts[$club->id] ?? 0), (int) ($opportunityCounts[$ownerIds[$club->id] ?? 0] ?? 0)))->values(),
            'meta' => ['current_page' => $clubs->currentPage(), 'per_page' => $clubs->perPage(), 'total' => $clubs->total(), 'last_page' => $clubs->lastPage()],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $club = DB::table('club_workspaces')->join('users', 'users.id', '=', 'club_workspaces.owner_id')->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')->where('club_workspaces.slug', $slug)->where('users.status', 'active')->where(fn ($query) => $query->whereNull('profiles.is_public')->orWhere('profiles.is_public', true))->first(['club_workspaces.*', 'profiles.city', 'profiles.province', 'profiles.country', 'profiles.profile_image_path', 'profiles.cover_image_path']);
        abort_unless($club, 404, 'Club not found.');

        $staff = DB::table('club_staff')->join('users', 'users.id', '=', 'club_staff.user_id')->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')->where('workspace_id', $club->id)->where('club_staff.status', 'active')->where('users.status', 'active')->orderBy('users.name')->get(['users.id', 'users.name', 'profiles.slug', 'profiles.profile_image_path AS image', 'club_staff.role']);
        $opportunities = Opportunity::query()->where('posted_by_id', $club->owner_id)->where('status', 'published')->where(fn ($query) => $query->whereNull('deadline')->orWhere('deadline', '>=', now()))->with('sport')->latest('published_at')->limit(20)->get()->map(fn ($item) => ['id' => $item->public_id, 'title' => $item->title, 'type' => $item->type, 'city' => $item->city, 'is_remote' => $item->is_remote, 'sport' => $item->sport?->name, 'deadline' => $item->deadline]);

        return response()->json(['data' => [
            ...$this->summary($club, $staff->count(), $opportunities->count()),
            'cover_image' => $club->cover_image_path,
            'location' => ['city' => $club->city, 'province' => $club->province, 'country' => $club->country],
            'staff' => $staff,
            'opportunities' => $opportunities,
        ]]);
    }

    private function summary(object $club, int $staffCount, int $opportunityCount): array
    {
        return ['id' => $club->id, 'name' => $club->name, 'slug' => $club->slug, 'bio' => $club->bio, 'website' => $club->website, 'image' => $club->profile_image_path, 'city' => $club->city, 'province' => $club->province, 'staff_count' => $staffCount, 'opportunities_count' => $opportunityCount];
    }
}
