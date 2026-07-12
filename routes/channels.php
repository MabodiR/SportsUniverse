<?php

use App\Domain\Messaging\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversations.{publicId}', function ($user, string $publicId) {
    return Conversation::where('public_id', $publicId)
        ->whereHas('participants', fn ($query) => $query->whereKey($user->id))
        ->exists();
});
