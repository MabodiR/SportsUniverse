<?php

namespace Database\Factories;

use App\Domain\Profiles\Models\UserProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserProfileFactory extends Factory
{
    protected $model = UserProfile::class;

    public function definition(): array
    {
        $city = fake()->city();

        return ['user_id' => User::factory(), 'slug' => Str::slug(fake()->unique()->userName()), 'date_of_birth' => fake()->dateTimeBetween('-40 years', '-16 years'), 'bio' => fake()->sentence(), 'country' => 'ZA', 'province' => fake()->randomElement(['Gauteng', 'Limpopo', 'Western Cape', 'KwaZulu-Natal']), 'city' => $city, 'is_public' => true, 'is_available' => fake()->boolean(), 'completeness' => fake()->numberBetween(40, 100)];
    }
}
