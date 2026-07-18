<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_feed_items', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->decimal('score', 18, 4);
            $table->unsignedInteger('position');
            $table->ulid('generation');
            $table->timestamp('generated_at');
            $table->primary(['user_id', 'video_id']);
            $table->unique(['user_id', 'position']);
            $table->index(['user_id', 'score']);
        });

        if (DB::getDriverName() !== 'pgsql') return;

        DB::statement('CREATE TABLE video_view_events (
            video_id BIGINT NOT NULL REFERENCES videos(id) ON DELETE CASCADE,
            user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
            session_key VARCHAR(64) NULL,
            watched_ms INTEGER NOT NULL DEFAULT 0,
            completed BOOLEAN NOT NULL DEFAULT FALSE,
            viewed_on DATE NOT NULL,
            created_at TIMESTAMP WITHOUT TIME ZONE NULL,
            updated_at TIMESTAMP WITHOUT TIME ZONE NULL,
            UNIQUE (video_id, user_id, viewed_on)
        ) PARTITION BY RANGE (viewed_on)');

        foreach (range(-1, 2) as $offset) {
            $from = Carbon::now()->startOfMonth()->addMonths($offset);
            $to = $from->copy()->addMonth();
            $name = 'video_view_events_'.$from->format('Ym');
            DB::statement("CREATE TABLE {$name} PARTITION OF video_view_events FOR VALUES FROM ('{$from->toDateString()}') TO ('{$to->toDateString()}')");
            DB::statement("CREATE INDEX {$name}_user_date_idx ON {$name} (user_id, viewed_on DESC)");
            DB::statement("CREATE INDEX {$name}_video_date_idx ON {$name} (video_id, viewed_on DESC)");
        }
        DB::statement('CREATE TABLE video_view_events_default PARTITION OF video_view_events DEFAULT');
        DB::statement('INSERT INTO video_view_events (video_id, user_id, session_key, watched_ms, completed, viewed_on, created_at, updated_at)
            SELECT video_id, user_id, session_key, watched_ms, completed, viewed_on, created_at, updated_at FROM video_views
            ON CONFLICT (video_id, user_id, viewed_on) DO NOTHING');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') DB::statement('DROP TABLE IF EXISTS video_view_events CASCADE');
        Schema::dropIfExists('recommendation_feed_items');
    }
};
