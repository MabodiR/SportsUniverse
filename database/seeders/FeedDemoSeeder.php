<?php

namespace Database\Seeders;

use App\Domain\Feed\Models\Video;
use App\Domain\Media\Models\Media;
use App\Domain\Sports\Models\Sport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class FeedDemoSeeder extends Seeder
{
    public function run(): void
    {
        Role::findOrCreate('athlete', 'web');

        $sports = Sport::all();
        if ($sports->isEmpty()) {
            return;
        }

        foreach (range(1, 6) as $index) {
            $user = User::updateOrCreate(
                ['email' => 'athlete'.$index.'@sportuniverse.test'],
                ['name' => 'Demo Athlete '.$index, 'password' => Hash::make('Password123!'), 'email_verified_at' => now(), 'status' => 'active'],
            );
            $user->syncRoles(['athlete']);
            $user->profile()->updateOrCreate(['user_id' => $user->id], ['slug' => 'demo-athlete-'.$index, 'bio' => 'Demo SportUniverse athlete profile.', 'country' => 'ZA', 'city' => $index % 2 ? 'Johannesburg' : 'Pretoria', 'completeness' => 85]);
            $sport = $sports->get(($index - 1) % $sports->count());
            $position = $sport->positions()->first();
            $user->athleteProfile()->updateOrCreate(['user_id' => $user->id], ['sport_id' => $sport->id, 'position_id' => $position?->id, 'primary_sport' => $sport->name, 'position' => $position?->name, 'playing_level' => 'Amateur']);
            $media = Media::firstOrCreate(
                ['user_id' => $user->id, 'path' => 'demo/videos/athlete-'.$index.'.mp4'],
                ['public_id' => (string) Str::ulid(), 'kind' => 'video', 'collection' => 'highlights', 'disk' => 'local', 'original_name' => 'highlight-'.$index.'.mp4', 'mime_type' => 'video/mp4', 'size_bytes' => 1024, 'processing_status' => 'ready', 'moderation_status' => 'approved', 'processed_at' => now()],
            );
            Video::updateOrCreate(
                ['media_id' => $media->id],
                ['public_id' => (string) Str::ulid(), 'user_id' => $user->id, 'sport_id' => $sport->id, 'caption' => 'Demo '.$sport->name.' highlight', 'hashtags' => ['sport', 'talent', $sport->slug], 'visibility' => 'public', 'status' => 'published', 'published_at' => now(), 'likes_count' => 25 * $index, 'views_count' => 500 * $index],
            );
        }
    }
}
