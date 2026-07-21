<?php

namespace Database\Seeders;

use App\Domain\Sports\Models\Sport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SportSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('sports_catalogue') as $name => $positions) {
            $sport = Sport::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'is_active' => true, 'sort_order' => array_search($name, array_keys(config('sports_catalogue')), true) * 10]);
            foreach ($positions as $position) {
                $sport->positions()->updateOrCreate(['slug' => Str::slug($position)], ['name' => $position, 'is_active' => true]);
            }
        }
    }
}
