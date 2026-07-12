<?php

namespace Database\Factories;

use App\Domain\Sports\Models\Position;
use App\Domain\Sports\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return ['sport_id' => Sport::factory(), 'name' => $name, 'slug' => Str::slug($name), 'is_active' => true];
    }
}
