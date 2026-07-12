<?php

namespace Database\Factories;

use App\Domain\Messaging\Models\MessageRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MessageRequestFactory extends Factory
{
    protected $model = MessageRequest::class;

    public function definition(): array
    {
        return ['public_id' => (string) Str::ulid(), 'sender_id' => User::factory(), 'recipient_id' => User::factory(), 'message' => fake()->sentence(), 'status' => 'pending'];
    }
}
