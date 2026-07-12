<?php

namespace Database\Factories;

use App\Domain\Opportunities\Models\Opportunity;
use App\Domain\Opportunities\Models\OpportunityApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OpportunityApplicationFactory extends Factory
{
    protected $model = OpportunityApplication::class;

    public function definition(): array
    {
        return ['public_id' => (string) Str::ulid(), 'opportunity_id' => Opportunity::factory(), 'user_id' => User::factory(), 'cover_letter' => fake()->paragraph(), 'status' => 'submitted'];
    }
}
