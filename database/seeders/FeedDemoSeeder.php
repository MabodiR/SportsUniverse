<?php

namespace Database\Seeders;

use App\Domain\Feed\Models\Video;
use App\Domain\Media\Models\Media;
use App\Domain\Sports\Models\Sport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FeedDemoSeeder extends Seeder
{
    public function run(): void
    {
        $sports = Sport::all();
        if ($sports->isEmpty()) {
            return;
        }foreach (range(1, 6) as $index) {
            $user = User::factory()->create(['name' => 'Demo Athlete '.$index, 'email' => 'athlete'.$index.'@sportuniverse.test']);
            $user->assignRole('athlete');
            $user->profile()->create(['slug' => 'demo-athlete-'.$index, 'bio' => 'Demo SportUniverse athlete profile.', 'country' => 'ZA', 'city' => $index % 2 ? 'Johannesburg' : 'Pretoria', 'completeness' => 85]);
            $sport = $sports->get(($index - 1) % $sports->count());
            $position = $sport->positions()->first();
            $user->athleteProfile()->create(['sport_id' => $sport->id, 'position_id' => $position?->id, 'primary_sport' => $sport->name, 'position' => $position?->name, 'playing_level' => 'Amateur']);
            $media = Media::factory()->for($user)->create(['kind' => 'video', 'collection' => 'highlights', 'mime_type' => 'video/mp4', 'path' => 'demo/videos/athlete-'.$index.'.mp4', 'original_name' => 'highlight-'.$index.'.mp4']);
            Video::factory()->for($user)->create(['media_id' => $media->id, 'sport_id' => $sport->id, 'public_id' => (string) Str::ulid(), 'caption' => 'Demo '.$sport->name.' highlight', 'likes_count' => fake()->numberBetween(5, 500), 'views_count' => fake()->numberBetween(100, 10000)]);
        }
    }
}
