<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateVideoViewPartitions extends Command
{
    protected $signature = 'db:partition-video-views {--months=3}';
    protected $description = 'Create upcoming monthly PostgreSQL video-view partitions';
    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') return self::SUCCESS;
        foreach (range(0, max(1, (int) $this->option('months')) - 1) as $offset) {
            $from = Carbon::now()->startOfMonth()->addMonths($offset);
            $to = $from->copy()->addMonth();
            $name = 'video_view_events_'.$from->format('Ym');
            $exists = DB::table('pg_class')->where('relname', $name)->exists();
            if (! $exists) {
                DB::statement("CREATE TABLE {$name} PARTITION OF video_view_events FOR VALUES FROM ('{$from->toDateString()}') TO ('{$to->toDateString()}')");
                DB::statement("CREATE INDEX {$name}_user_date_idx ON {$name} (user_id, viewed_on DESC)");
                DB::statement("CREATE INDEX {$name}_video_date_idx ON {$name} (video_id, viewed_on DESC)");
            }
        }
        return self::SUCCESS;
    }
}
