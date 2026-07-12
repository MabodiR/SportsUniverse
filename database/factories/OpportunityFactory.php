<?php

namespace Database\Factories;

use App\Domain\Opportunities\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        return ['public_id' => (string) Str::ulid(), 'posted_by_id' => User::factory(), 'title' => fake()->sentence(5), 'type' => fake()->randomElement(['trial', 'job', 'training_camp', 'sponsorship', 'scout_day', 'academy_application']), 'description' => fake()->paragraphs(2, true), 'country' => 'ZA', 'province' => 'Gauteng', 'city' => 'Johannesburg', 'requirements' => ['Completed profile', 'Available on selected date'], 'status' => 'published', 'published_at' => now(), 'deadline' => now()->addMonth()];
    }
}
