<?php

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BlockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()->join('user_blocks', 'users.id', '=', 'user_blocks.blocked_id')->leftJoin('user_profiles', 'user_profiles.user_id', '=', 'users.id')->where('user_blocks.blocker_id', $request->user()->id)->orderByDesc('user_blocks.created_at')->get(['users.id', 'users.name', 'user_profiles.slug', 'user_profiles.profile_image_path', 'user_blocks.created_at as blocked_at']);

        return response()->json(['data' => $users]);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot block yourself.');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $user, $data) {
            DB::table('user_blocks')->updateOrInsert(['blocker_id' => $request->user()->id, 'blocked_id' => $user->id], ['reason' => $data['reason'] ?? null, 'created_at' => now()]);
            DB::table('message_requests')->where(fn ($q) => $q->where(['sender_id' => $request->user()->id, 'recipient_id' => $user->id])->orWhere(fn ($q) => $q->where(['sender_id' => $user->id, 'recipient_id' => $request->user()->id])))->where('status', 'pending')->update(['status' => 'declined', 'responded_at' => now(), 'updated_at' => now()]);
            $request->user()->following()->detach($user);
            $user->following()->detach($request->user());
        });

        return response()->json(['message' => 'User blocked.']);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        DB::table('user_blocks')->where(['blocker_id' => $request->user()->id, 'blocked_id' => $user->id])->delete();

        return response()->json(['message' => 'User unblocked.']);
    }
}
