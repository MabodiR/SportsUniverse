<?php

namespace Database\Factories;

use App\Domain\Feed\Models\Video;
use App\Domain\Moderation\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        $target = Video::factory()->create();

        return ['public_id' => (string) Str::ulid(), 'reporter_id' => User::factory(), 'reportable_type' => $target->getMorphClass(), 'reportable_id' => $target->id, 'reason' => 'spam', 'details' => fake()->sentence(), 'status' => 'open'];
    }
}
