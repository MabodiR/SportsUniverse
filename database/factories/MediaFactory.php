<?php

namespace Database\Factories;

use App\Domain\Media\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $id = (string) Str::ulid();

        return ['public_id' => $id, 'user_id' => User::factory(), 'kind' => 'image', 'collection' => 'gallery', 'disk' => 'local', 'path' => 'users/1/image/'.$id.'.jpg', 'original_name' => 'photo.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 1024, 'processing_status' => 'ready', 'moderation_status' => 'approved', 'processed_at' => now()];
    }
}
