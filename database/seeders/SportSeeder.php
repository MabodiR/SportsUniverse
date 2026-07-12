<?php

namespace Database\Seeders;

use App\Domain\Sports\Models\Sport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SportSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = ['Football' => ['Goalkeeper', 'Defender', 'Midfielder', 'Winger', 'Striker'], 'Rugby' => ['Prop', 'Hooker', 'Lock', 'Flanker', 'Scrum-half', 'Fly-half', 'Centre', 'Wing', 'Fullback'], 'Athletics' => ['Sprinter', 'Middle-distance', 'Long-distance', 'Hurdler', 'Jumper', 'Thrower'], 'Netball' => ['Goal Shooter', 'Goal Attack', 'Wing Attack', 'Centre', 'Wing Defence', 'Goal Defence', 'Goal Keeper'], 'Cricket' => ['Batter', 'Bowler', 'All-rounder', 'Wicket-keeper']];
        foreach ($catalogue as $name => $positions) {
            $sport = Sport::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'is_active' => true]);
            foreach ($positions as $position) {
                $sport->positions()->updateOrCreate(['slug' => Str::slug($position)], ['name' => $position, 'is_active' => true]);
            }
        }
    }
}
