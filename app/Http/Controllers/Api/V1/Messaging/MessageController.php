<?php

namespace App\Http\Controllers\Api\V1\Messaging;

use App\Domain\Media\Models\Media;
use App\Domain\Messaging\Events\MessageSent;
use App\Domain\Messaging\Models\Conversation;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Messaging\StoreMessageRequest;
use App\Http\Resources\Api\V1\Messaging\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    public function index(Conversation $conversation): AnonymousResourceCollection
    {
        Gate::authorize('view', $conversation);

        return MessageResource::collection($conversation->messages()->with('sender.profile', 'media')->latest()->cursorPaginate(30));
    }

    public function store(StoreMessageRequest $request, Conversation $conversation, NotificationDispatcher $notifications): JsonResponse
    {
        Gate::authorize('send', $conversation);
        $otherIds = $conversation->participants()->where('users.id', '!=', $request->user()->id)->pluck('users.id');
        if (DB::table('user_blocks')->where(fn ($q) => $q->where('blocker_id', $request->user()->id)->whereIn('blocked_id', $otherIds)->orWhere(fn ($q) => $q->whereIn('blocker_id', $otherIds)->where('blocked_id', $request->user()->id)))->exists()) {
            throw ValidationException::withMessages(['conversation' => ['Messaging is unavailable in this conversation.']]);
        }$media = null;
        if ($request->filled('media_id')) {
            $media = Media::where('public_id', $request->validated('media_id'))->where('user_id', $request->user()->id)->where('processing_status', 'ready')->where('moderation_status', 'approved')->first();
            if (! $media) {
                throw ValidationException::withMessages(['media_id' => ['Use an owned, approved media item that has finished processing.']]);
            }
        }$message = DB::transaction(function () use ($request, $conversation, $media) {
            $message = $conversation->messages()->create(['public_id' => (string) Str::ulid(), 'sender_id' => $request->user()->id, 'media_id' => $media?->id, 'body' => $request->validated('body')]);
            $conversation->update(['last_message_at' => $message->created_at]);
            $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now(), 'archived_at' => null]);

            return $message;
        });
        $message->load('conversation', 'sender.profile', 'media');
        MessageSent::dispatch($message);
        $conversation->participants()->where('users.id', '!=', $request->user()->id)->each(fn ($recipient) => $notifications->send($recipient, 'messages', ['event' => 'new_message', 'message_id' => $message->public_id, 'conversation_id' => $conversation->public_id, 'sender_id' => $request->user()->id, 'sender_name' => $request->user()->name, 'preview' => str($message->body)->limit(120)->value()]));

        return response()->json(['message' => 'Message sent.', 'data' => new MessageResource($message)], 201);
    }
}
