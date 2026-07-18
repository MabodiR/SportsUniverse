<?php

namespace App\Http\Controllers\Web;

use App\Domain\Messaging\Models\Conversation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessagingContextController extends Controller
{
    public function __invoke(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot message yourself.');
        $key = collect([$request->user()->id, $user->id])->sort()->join(':');
        $conversation = Conversation::where('direct_key', $key)->first();
        $mutual = $request->user()->following()->whereKey($user->id)->exists()
            && $user->following()->whereKey($request->user()->id)->exists();

        if (! $conversation && $mutual) {
            $conversation = DB::transaction(function () use ($key, $request, $user) {
                $conversation = Conversation::firstOrCreate(['direct_key' => $key], ['public_id' => (string) Str::ulid(), 'type' => 'direct']);
                $conversation->participants()->syncWithoutDetaching([$request->user()->id => ['joined_at' => now()], $user->id => ['joined_at' => now()]]);

                return $conversation;
            });
        }

        $archived = $conversation ? (bool) DB::table('conversation_participants')->where(['conversation_id' => $conversation->id, 'user_id' => $request->user()->id])->value('archived_at') : false;

        return response()->json(['data' => [
            'mode' => $conversation ? 'conversation' : 'request',
            'recipient' => ['id' => $user->id, 'name' => $user->name, 'slug' => $user->profile?->slug],
            'conversation' => $conversation ? [
                'id' => $conversation->public_id,
                'archived' => $archived,
                'messages' => $conversation->messages()->with('sender')->latest()->limit(50)->get()->reverse()->values()->map(fn ($message) => [
                    'id' => $message->public_id,
                    'body' => $message->deleted_at ? null : $message->body,
                    'mine' => $message->sender_id === $request->user()->id,
                    'sender' => $message->sender?->name,
                    'created_at' => $message->created_at,
                ]),
            ] : null,
        ]]);
    }
}
