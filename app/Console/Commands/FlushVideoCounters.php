<?php

namespace App\Console\Commands;

use App\Domain\Feed\Services\BufferedVideoCounters;
use Illuminate\Console\Command;

class FlushVideoCounters extends Command
{
    protected $signature = 'feed:flush-counters {--limit=5000}';
    protected $description = 'Flush buffered Redis video counters to PostgreSQL';
    public function handle(BufferedVideoCounters $counters): int
    {
        $this->info('Flushed '.$counters->flush((int) $this->option('limit')).' video counters.');
        return self::SUCCESS;
    }
}
