<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RefreshTalentShowcases extends Command
{
    protected $signature = 'feed:refresh-talent-showcases {--count=1000} {--dry-run} {--force}';

    protected $description = 'Replace every existing feed post with athlete talent showcases while preserving users, sports and approved source media';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Talent showcase refresh requires PostgreSQL.');
        }

        $count = max(1, min(100000, (int) $this->option('count')));
        $existingVideos = DB::table('videos')->count();
        $replaceableVideoMedia = DB::table('media')->where('kind', 'video')->where('collection', '!=', 'performance-sports')->count();
        $attachedImages = DB::table('video_images')->distinct('media_id')->count('media_id');
        $approvedSources = DB::table('media')->where('collection', 'performance-sports')->where('kind', 'video')->where('processing_status', 'ready')->where('moderation_status', 'approved')->count();

        $this->table(['Action', 'Records'], [
            ['All existing posts to replace', number_format($existingVideos)],
            ['Non-source video media to remove', number_format($replaceableVideoMedia)],
            ['Post image attachments to remove', number_format($attachedImages)],
            ['Approved licensed source videos preserved', number_format($approvedSources)],
            ['New talent showcase posts', number_format($count)],
        ]);

        if ($approvedSources === 0) {
            throw new RuntimeException('No approved performance-sports source videos are available. Import and review licensed media first.');
        }

        if ($this->option('dry-run')) return self::SUCCESS;
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Production refresh requires --force after reviewing --dry-run output.');
            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm('Permanently replace every existing post, including genuine user uploads?')) return self::FAILURE;

        DB::transaction(function () use ($count) {
            DB::statement(<<<'SQL'
                CREATE TEMP TABLE talent_refresh_post_images ON COMMIT DROP AS
                SELECT DISTINCT media.id FROM media
                JOIN video_images ON video_images.media_id = media.id
                WHERE media.collection != 'performance-sports'
                SQL);
            DB::table('videos')->delete();
            DB::table('media')->where('kind', 'video')->where('collection', '!=', 'performance-sports')->delete();
            DB::statement('DELETE FROM media USING talent_refresh_post_images WHERE media.id = talent_refresh_post_images.id');

            $exitCode = Artisan::call('feed:seed-mass-posts', ['--count' => $count]);
            $this->output->write(Artisan::output());
            if ($exitCode !== self::SUCCESS || DB::table('videos')->count() !== $count) {
                throw new RuntimeException('Replacement generation failed; the original feed has been restored.');
            }
        });
        $this->info('Talent showcase refresh complete. Users, profiles, sports and approved source media were preserved.');
        return self::SUCCESS;
    }
}
