<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $counts = [];
        if ($user = $request->user()) {
            $feedSeen = $request->session()->get('nav_seen.feed', now()->subDay());
            $followingSeen = $request->session()->get('nav_seen.following', now()->subDays(7));
            $opportunitiesSeen = $request->session()->get('nav_seen.opportunities', now()->subDays(7));
            $counts = [
                'feed' => DB::table('videos')->where('status', 'published')->where('visibility', 'public')->where('published_at', '>', $feedSeen)->count(),
                'following' => DB::table('videos')->join('follows', 'follows.followed_id', '=', 'videos.user_id')->where('follows.follower_id', $user->id)->where('videos.status', 'published')->where('videos.published_at', '>', $followingSeen)->count(),
                'opportunities' => DB::table('opportunities')->where('status', 'published')->where('published_at', '>', $opportunitiesSeen)->where(fn ($query) => $query->whereNull('deadline')->orWhere('deadline', '>', now()))->count(),
                'messages' => DB::table('messages')->join('conversation_participants as mine', 'mine.conversation_id', '=', 'messages.conversation_id')->where('mine.user_id', $user->id)->where('messages.sender_id', '!=', $user->id)->where(fn ($query) => $query->whereNull('mine.last_read_at')->orWhereColumn('messages.created_at', '>', 'mine.last_read_at'))->count() + DB::table('message_requests')->where('recipient_id', $user->id)->where('status', 'pending')->count(),
                'notifications' => $user->unreadNotifications()->count(),
            ];
            $path = '/'.$request->path();
            if ($path === '/feed') $request->session()->put('nav_seen.feed', now());
            if ($path === '/following') $request->session()->put('nav_seen.following', now());
            if (str_starts_with($path, '/opportunities')) $request->session()->put('nav_seen.opportunities', now());
        }
        return [
            ...parent::share($request),
            'auth' => ['user' => $request->user()?->loadMissing('roles', 'profile')->loadCount('following')],
            'flash' => ['success' => fn () => $request->session()->get('success')],
            'nav_counts' => $counts,
        ];
    }
}
