<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** PostgreSQL cannot build concurrent indexes inside a transaction. */
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE INDEX CONCURRENTLY IF NOT EXISTS videos_feed_public_posts_recent_idx
            ON videos (published_at DESC, id DESC)
            WHERE status = 'published' AND visibility = 'public' AND post_type = 'post'
        SQL);

        foreach ($this->viewEventPartitions() as $table) {
            $index = substr($table.'_user_video_date_idx', 0, 63);
            DB::statement(sprintf(
                'CREATE INDEX CONCURRENTLY IF NOT EXISTS %s ON %s (user_id, video_id, viewed_on DESC)',
                $this->quoteIdentifier($index),
                $this->quoteIdentifier($table),
            ));
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS videos_feed_public_posts_recent_idx');

        foreach ($this->viewEventPartitions() as $table) {
            $index = substr($table.'_user_video_date_idx', 0, 63);
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS '.$this->quoteIdentifier($index));
        }
    }

    /** @return array<int, string> */
    private function viewEventPartitions(): array
    {
        return array_map(fn ($row) => $row->table_name, DB::select(<<<'SQL'
            SELECT child.relname AS table_name
            FROM pg_inherits
            JOIN pg_class parent ON pg_inherits.inhparent = parent.oid
            JOIN pg_class child ON pg_inherits.inhrelid = child.oid
            WHERE parent.relname = 'video_view_events'
        SQL));
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
