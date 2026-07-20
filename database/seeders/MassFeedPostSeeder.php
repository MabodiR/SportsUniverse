<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class MassFeedPostSeeder extends Seeder
{
    public function run(): void
    {
        if (config('scale.mass_feed_import_online_media', true)) {
            $this->call(SportsMediaCatalogSeeder::class);
        }
        $parameters = [
            '--count' => (int) config('scale.mass_feed_post_count', 5_000_000),
            '--batch' => (int) config('scale.mass_feed_batch_size', 100_000),
        ];

        if (! config('scale.mass_feed_with_topics', true)) {
            $parameters['--without-topics'] = true;
        }

        Artisan::call('feed:seed-mass-posts', $parameters, $this->command?->getOutput());
    }
}
