<?php

namespace App\Http\Controllers\Web;

use App\Domain\Opportunities\Models\Opportunity;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ClubPageController extends Controller
{
    public function __invoke(string $slug): Response
    {
        $club = DB::table('club_workspaces')->where('slug', $slug)->firstOrFail();
        $staff = DB::table('club_staff')->join('users', 'users.id', '=', 'club_staff.user_id')->where('workspace_id', $club->id)->where('club_staff.status', 'active')->get(['users.name', 'club_staff.role']);
        $opportunities = Opportunity::query()->where('posted_by_id', $club->owner_id)->where('status', 'published')->with('sport')->latest('published_at')->limit(12)->get()->map(fn ($item) => ['id' => $item->public_id, 'title' => $item->title, 'type' => $item->type, 'city' => $item->city, 'sport' => $item->sport?->name, 'deadline' => $item->deadline]);

        $title = $club->name.' | SportUniverse';
        $description = str($club->bio ?: 'View this club, its staff and latest sporting opportunities on SportUniverse.')->squish()->limit(160)->value();

        return Inertia::render('Club/Show', compact('club', 'staff', 'opportunities'))->withViewData('seo', ['title' => $title, 'description' => $description, 'type' => 'profile']);
    }
}
