<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SeedMassFeedPosts extends Command
{
    protected $signature = 'feed:seed-mass-posts {--count=5000000} {--total=} {--batch=50000} {--without-topics}';
    protected $description = 'Bulk-generate lightweight, feed-visible dummy posts across every configured sport';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') throw new RuntimeException('Mass feed generation requires PostgreSQL.');
        $target = max(1, (int) $this->option('count'));
        if ($this->option('total') !== null) {
            $nonMassPosts = DB::table('videos')->where('public_id', 'not like', '5M%')->count();
            $target = max(0, (int) $this->option('total') - $nonMassPosts);
        }
        $batchSize = max(1000, min(100000, (int) $this->option('batch')));
        $sourceVideos = DB::table('media')->whereIn('collection', ['performance-sports', 'demo-videos'])->where('kind', 'video')->where('processing_status', 'ready')->where('moderation_status', 'approved')->get();
        if ($sourceVideos->isEmpty()) throw new RuntimeException('At least one ready, approved source video is required.');
        if (! DB::table('sports')->exists() || ! DB::table('users')->where('status', 'active')->exists()) throw new RuntimeException('Sports and active users are required.');

        DB::statement('SET synchronous_commit TO OFF');
        DB::statement('CREATE TEMP TABLE mass_seed_users ON COMMIT PRESERVE ROWS AS SELECT id, row_number() OVER (ORDER BY id) AS rn FROM users WHERE status = ?', ['active']);
        DB::statement('CREATE UNIQUE INDEX mass_seed_users_rn_idx ON mass_seed_users (rn)');
        DB::statement('CREATE TEMP TABLE mass_seed_sports ON COMMIT PRESERVE ROWS AS SELECT id, name, row_number() OVER (ORDER BY id) AS rn FROM sports');
        DB::statement('CREATE UNIQUE INDEX mass_seed_sports_rn_idx ON mass_seed_sports (rn)');
        DB::statement("CREATE TEMP TABLE mass_seed_source_videos ON COMMIT PRESERVE ROWS AS SELECT media.*, row_number() OVER (ORDER BY id) AS source_rn FROM media WHERE collection IN ('performance-sports', 'demo-videos') AND kind = 'video' AND processing_status = 'ready' AND moderation_status = 'approved'");
        DB::statement('CREATE UNIQUE INDEX mass_seed_source_videos_rn_idx ON mass_seed_source_videos (source_rn)');
        $userCount = (int) DB::table('mass_seed_users')->count();
        $sportCount = (int) DB::table('mass_seed_sports')->count();
        $sourceVideoCount = (int) DB::table('mass_seed_source_videos')->count();
        $existing = (int) DB::table('videos')->where('public_id', 'like', '5M%')->selectRaw("COALESCE(MAX(CAST(SUBSTRING(public_id FROM 3) AS BIGINT)), 0) AS maximum")->value('maximum');

        if ($existing >= $target) { $this->info("Mass feed already contains {$existing} video posts."); return self::SUCCESS; }
        $this->info('Generating '.number_format($target - $existing).' posts across '.$sportCount.' sports in '.number_format($batchSize).'-row batches.');
        $bar = $this->output->createProgressBar($target - $existing);
        $bar->start();

        for ($start = $existing + 1; $start <= $target; $start += $batchSize) {
            $end = min($target, $start + $batchSize - 1);
            DB::transaction(function () use ($start, $end, $userCount, $sportCount, $sourceVideoCount) {
                    DB::insert(<<<'SQL'
                        INSERT INTO media (public_id, user_id, kind, collection, disk, path, original_name, mime_type, size_bytes, checksum_sha256, processing_status, moderation_status, thumbnail_path, duration_ms, width, height, metadata, processed_at, created_at, updated_at)
                        SELECT 'MV' || lpad(series.n::text, 24, '0'), users.id, 'video', 'performance-scale', source.disk, source.path,
                            'scale-video-' || series.n || '-' || source.original_name, source.mime_type, source.size_bytes, source.checksum_sha256,
                            'ready', 'approved', source.thumbnail_path, source.duration_ms, source.width, source.height, source.metadata, now(), now(), now()
                        FROM generate_series(CAST(? AS bigint), CAST(? AS bigint)) AS series(n)
                        JOIN mass_seed_users users ON users.rn = ((series.n - 1) % ?) + 1
                        JOIN mass_seed_source_videos source ON source.source_rn = ((series.n - 1) % ?) + 1
                        ON CONFLICT (public_id) DO NOTHING
                        SQL, [$start, $end, $userCount, $sourceVideoCount]);
                DB::insert(<<<'SQL'
                    INSERT INTO videos (
                        public_id, user_id, media_id, sport_id, caption, hashtags, visibility, status,
                        views_count, likes_count, comments_count, shares_count, saves_count, published_at,
                        location_name, comments_enabled, country_code, league, team, competition, content_type,
                        language, skill_tags, content_labels, analyzed_at, created_at, updated_at
                    )
                    SELECT
                        '5M' || lpad(series.n::text, 24, '0'), users.id, scale_video.id, sports.id,
                        'Scale demo: ' || sports.name || ' ' || CASE series.n % 5 WHEN 0 THEN 'match highlight' WHEN 1 THEN 'training session' WHEN 2 THEN 'skills showcase' WHEN 3 THEN 'tactical analysis' ELSE 'behind the scenes' END,
                        jsonb_build_array(lower(replace(sports.name, ' ', '')), 'sportsuniverse', 'scaledemo'),
                        'public', 'published', 25 + (series.n * 17) % 250000, 2 + (series.n * 7) % 18000,
                        series.n % 350, series.n % 240, series.n % 900,
                        now() - ((series.n % 525600)::text || ' minutes')::interval,
                        CASE series.n % 6 WHEN 0 THEN 'Johannesburg' WHEN 1 THEN 'London' WHEN 2 THEN 'Cape Town' WHEN 3 THEN 'Manchester' WHEN 4 THEN 'Durban' ELSE 'Pretoria' END,
                        true,
                        CASE series.n % 4 WHEN 0 THEN 'ZA' WHEN 1 THEN 'GB' WHEN 2 THEN 'US' ELSE 'AU' END,
                        CASE
                            WHEN lower(sports.name) LIKE '%football%' AND series.n % 4 = 0 THEN 'PSL'
                            WHEN lower(sports.name) LIKE '%football%' THEN 'Premier League'
                            WHEN lower(sports.name) LIKE '%rugby%' THEN 'United Rugby Championship'
                            WHEN lower(sports.name) LIKE '%cricket%' THEN 'SA20'
                            WHEN lower(sports.name) LIKE '%netball%' THEN 'Netball Super League'
                            ELSE sports.name || ' National League'
                        END,
                        sports.name || ' Club ' || ((series.n % 120) + 1),
                        sports.name || ' Championship',
                        CASE series.n % 5 WHEN 0 THEN 'match_highlight' WHEN 1 THEN 'training' WHEN 2 THEN 'skills' WHEN 3 THEN 'analysis' ELSE 'behind_the_scenes' END,
                        'en', jsonb_build_array(CASE series.n % 4 WHEN 0 THEN 'finishing' WHEN 1 THEN 'defending' WHEN 2 THEN 'speed' ELSE 'teamwork' END),
                        jsonb_build_object('sport', jsonb_build_array(lower(sports.name)), 'country', jsonb_build_array(lower(CASE series.n % 4 WHEN 0 THEN 'ZA' WHEN 1 THEN 'GB' WHEN 2 THEN 'US' ELSE 'AU' END))),
                        now(), now(), now()
                    FROM generate_series(CAST(? AS bigint), CAST(? AS bigint)) AS series(n)
                    JOIN mass_seed_users users ON users.rn = ((series.n - 1) % ?) + 1
                    JOIN mass_seed_sports sports ON sports.rn = ((series.n - 1) % ?) + 1
                    JOIN media scale_video ON scale_video.public_id = 'MV' || lpad(series.n::text, 24, '0')
                    ON CONFLICT (public_id) DO NOTHING
                    SQL, [$start, $end, $userCount, $sportCount]);

                if (! $this->option('without-topics')) {
                    DB::insert(<<<'SQL'
                        INSERT INTO video_content_topics (video_id, dimension, value, weight, source, created_at, updated_at)
                        SELECT videos.id, topics.dimension, topics.value, topics.weight, 'seed', now(), now()
                        FROM videos
                        JOIN sports ON sports.id = videos.sport_id
                        CROSS JOIN LATERAL (VALUES
                            ('sport', lower(sports.name), 2.0),
                            ('country', lower(videos.country_code), 1.7),
                            ('league', lower(videos.league), 2.0),
                            ('content_type', lower(videos.content_type), 1.4)
                        ) topics(dimension, value, weight)
                        WHERE videos.public_id BETWEEN ? AND ?
                        ON CONFLICT (video_id, dimension, value) DO NOTHING
                        SQL, [$this->publicId($start), $this->publicId($end)]);
                }
            });
            $bar->advance($end - $start + 1);
        }
        $bar->finish();
        $this->newLine(2);
        $this->info('Mass feed generation complete.');
        return self::SUCCESS;
    }

    private function publicId(int $number): string
    {
        return '5M'.str_pad((string) $number, 24, '0', STR_PAD_LEFT);
    }

    private function convertExistingDummyPostsToVideos(int $start, int $end): void
    {
        DB::transaction(function () use ($start, $end) {
            DB::insert(<<<'SQL'
                INSERT INTO media (public_id, user_id, kind, collection, disk, path, original_name, mime_type, size_bytes, checksum_sha256, processing_status, moderation_status, thumbnail_path, duration_ms, width, height, metadata, processed_at, created_at, updated_at)
                SELECT 'MV' || substring(videos.public_id FROM 3), videos.user_id, 'video', 'performance-scale', source.disk, source.path,
                    'scale-video-' || CAST(substring(videos.public_id FROM 3) AS bigint) || '-' || source.original_name,
                    source.mime_type, source.size_bytes, source.checksum_sha256, 'ready', 'approved', source.thumbnail_path,
                    source.duration_ms, source.width, source.height, source.metadata, now(), now(), now()
                FROM videos
                JOIN mass_seed_source_videos source ON source.source_sport_id = videos.sport_id
                    AND source.sport_rn = ((CAST(substring(videos.public_id FROM 3) AS bigint) - 1) % source.sport_video_count) + 1
                WHERE videos.public_id BETWEEN ? AND ?
                ON CONFLICT (public_id) DO UPDATE SET
                    disk = EXCLUDED.disk, path = EXCLUDED.path, original_name = EXCLUDED.original_name,
                    mime_type = EXCLUDED.mime_type, size_bytes = EXCLUDED.size_bytes,
                    checksum_sha256 = EXCLUDED.checksum_sha256, thumbnail_path = EXCLUDED.thumbnail_path,
                    duration_ms = EXCLUDED.duration_ms, width = EXCLUDED.width, height = EXCLUDED.height,
                    metadata = EXCLUDED.metadata, updated_at = now()
                SQL, [$this->publicId($start), $this->publicId($end)]);

            DB::update(<<<'SQL'
                UPDATE videos
                SET media_id = media.id, updated_at = now()
                FROM media
                WHERE videos.public_id BETWEEN ? AND ?
                    AND media.public_id = 'MV' || substring(videos.public_id FROM 3)
                    AND videos.media_id IS DISTINCT FROM media.id
                SQL, [$this->publicId($start), $this->publicId($end)]);

            DB::delete(<<<'SQL'
                DELETE FROM video_images
                USING videos
                WHERE video_images.video_id = videos.id AND videos.public_id BETWEEN ? AND ?
                SQL, [$this->publicId($start), $this->publicId($end)]);
        });
    }
}
