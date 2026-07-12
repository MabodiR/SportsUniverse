<?php

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\MessageRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RespondToMessageRequest
{
    public function execute(MessageRequest $request, bool $accept): MessageRequest
    {
        return DB::transaction(function () use ($request, $accept) {
            $locked = MessageRequest::lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['request' => ['This message request has already been answered.']]);
            }if (! $accept) {
                $locked->update(['status' => 'declined', 'responded_at' => now()]);

                return $locked;
            }$key = collect([$locked->sender_id, $locked->recipient_id])->sort()->join(':');
            $conversation = Conversation::firstOrCreate(['direct_key' => $key], ['public_id' => (string) Str::ulid(), 'type' => 'direct', 'last_message_at' => now()]);
            $conversation->participants()->syncWithoutDetaching([$locked->sender_id => ['joined_at' => now()], $locked->recipient_id => ['joined_at' => now()]]);
            $conversation->messages()->create(['public_id' => (string) Str::ulid(), 'sender_id' => $locked->sender_id, 'body' => $locked->message]);
            $locked->update(['status' => 'accepted', 'conversation_id' => $conversation->id, 'responded_at' => now()]);

            return $locked->fresh('conversation');
        });
    }
}
