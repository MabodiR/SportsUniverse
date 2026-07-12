<?php

namespace Database\Factories;

use App\Domain\Profiles\Models\AthleteProfile;
use App\Domain\Sports\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AthleteProfileFactory extends Factory
{
    protected $model = AthleteProfile::class;

    public function definition(): array
    {
        $position = Position::factory()->create();

        return ['user_id' => User::factory(), 'sport_id' => $position->sport_id, 'position_id' => $position->id, 'club_name' => fake()->company(), 'playing_level' => fake()->randomElement(['School', 'Amateur', 'Semi-professional', 'Professional']), 'height_cm' => fake()->numberBetween(150, 210), 'weight_kg' => fake()->randomFloat(2, 45, 130)];
    }
}
