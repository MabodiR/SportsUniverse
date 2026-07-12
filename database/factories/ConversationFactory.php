<?php

namespace Database\Factories;

use App\Domain\Messaging\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return ['public_id' => (string) Str::ulid(), 'type' => 'direct', 'direct_key' => null, 'last_message_at' => now()];
    }
}
