<?php

namespace App\Console\Commands;

use App\Domain\Discovery\Contracts\ProfileIndexer;
use App\Domain\Discovery\Services\OpenSearchManager;
use App\Models\User;
use Illuminate\Console\Command;

class ManageProfileSearchIndex extends Command
{
    protected $signature = 'discovery:index-profiles {--create : Create the OpenSearch index first}';

    protected $description = 'Create and populate the talent discovery profile index';

    public function handle(OpenSearchManager $manager, ProfileIndexer $indexer): int
    {
        if (config('discovery.driver') !== 'opensearch') {
            $this->warn('DISCOVERY_DRIVER is not opensearch; no remote index was changed.');

            return self::SUCCESS;
        }if ($this->option('create')) {
            $manager->ensureIndex();
        }$bar = $this->output->createProgressBar(User::count());
        User::query()->whereHas('profile', fn ($q) => $q->where('is_public', true))->chunkById(200, function ($users) use ($indexer, $bar) {
            foreach ($users as $user) {
                $indexer->index($user);
                $bar->advance();
            }
        });
        $bar->finish();
        $this->newLine();
        $this->info('Profile index updated.');

        return self::SUCCESS;
    }
}
