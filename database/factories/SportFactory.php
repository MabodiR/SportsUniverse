<?php

namespace Database\Factories;

use App\Domain\Sports\Models\Sport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SportFactory extends Factory
{
    protected $model = Sport::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return ['name' => Str::title($name), 'slug' => Str::slug($name), 'is_active' => true, 'sort_order' => 0];
    }
}
