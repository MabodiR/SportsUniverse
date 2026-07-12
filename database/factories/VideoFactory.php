<?php

namespace Database\Factories;

use App\Domain\Feed\Models\Video;
use App\Domain\Media\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VideoFactory extends Factory
{
    protected $model = Video::class;

    public function definition(): array
    {
        return ['public_id' => (string) Str::ulid(), 'user_id' => User::factory(), 'media_id' => Media::factory()->state(['kind' => 'video', 'mime_type' => 'video/mp4', 'processing_status' => 'ready', 'moderation_status' => 'approved']), 'caption' => fake()->sentence(), 'hashtags' => ['sport', 'talent'], 'visibility' => 'public', 'status' => 'published', 'published_at' => now()];
    }
}
