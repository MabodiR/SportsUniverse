<?php

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\MessageRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateMessageRequest
{
    public function execute(User $sender, User $recipient, string $message): MessageRequest
    {
        if (DB::table('user_blocks')->where(fn ($q) => $q->where(['blocker_id' => $sender->id, 'blocked_id' => $recipient->id])->orWhere(fn ($q) => $q->where(['blocker_id' => $recipient->id, 'blocked_id' => $sender->id])))->exists()) {
            throw ValidationException::withMessages(['recipient_id' => ['Messaging is unavailable for this profile.']]);
        }$key = collect([$sender->id, $recipient->id])->sort()->join(':');
        if (Conversation::where('direct_key', $key)->exists()) {
            throw ValidationException::withMessages(['recipient_id' => ['A conversation with this profile already exists.']]);
        }$pending = MessageRequest::where(['sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'status' => 'pending'])->first();
        if ($pending) {
            return $pending;
        }$reusable = MessageRequest::where(['sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'status' => 'declined'])->first();
        if ($reusable) {
            $reusable->update(['message' => $message, 'status' => 'pending', 'responded_at' => null]);

            return $reusable;
        }

return MessageRequest::create(['public_id' => (string) Str::ulid(), 'sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'message' => $message, 'status' => 'pending']);
    }
}
