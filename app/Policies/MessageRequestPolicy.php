<?php

namespace App\Policies;

use App\Domain\Messaging\Models\MessageRequest;
use App\Models\User;

class MessageRequestPolicy
{
    public function respond(User $user, MessageRequest $request): bool
    {
        return $request->recipient_id === $user->id;
    }

    public function view(User $user, MessageRequest $request): bool
    {
        return in_array($user->id, [$request->sender_id, $request->recipient_id], true);
    }
}
