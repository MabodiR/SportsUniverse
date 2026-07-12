<?php

namespace Database\Factories;

use App\Domain\Messaging\Models\Conversation;
use App\Domain\Messaging\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return ['public_id' => (string) Str::ulid(), 'conversation_id' => Conversation::factory(), 'sender_id' => User::factory(), 'body' => fake()->sentence()];
    }
}
