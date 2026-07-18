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

        $statements = [
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS videos_feed_recent_idx ON videos (published_at DESC, id DESC) WHERE status = 'published' AND visibility = 'public'",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS videos_feed_rank_idx ON videos ((likes_count * 3 + comments_count * 4 + shares_count * 5 + views_count * 0.05) DESC, published_at DESC, id DESC) WHERE status = 'published' AND visibility = 'public'",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS videos_feed_sport_idx ON videos (sport_id, published_at DESC, id DESC) WHERE status = 'published' AND visibility = 'public'",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS videos_feed_creator_idx ON videos (user_id, published_at DESC, id DESC) WHERE status = 'published' AND visibility = 'public'",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS media_feed_ready_idx ON media (id) WHERE processing_status = 'ready' AND moderation_status = 'approved'",
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS video_images_media_id_idx ON video_images (media_id, video_id)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS feed_preferences_video_exclusion_idx ON feed_preferences (user_id, video_id) WHERE video_id IS NOT NULL',
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS feed_preferences_creator_exclusion_idx ON feed_preferences (user_id, creator_id) WHERE scope = 'creator' AND creator_id IS NOT NULL",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS feed_preferences_sport_exclusion_idx ON feed_preferences (user_id, sport_id) WHERE scope = 'sport' AND sport_id IS NOT NULL",
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS video_likes_user_video_idx ON video_likes (user_id, video_id)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS saved_videos_user_video_idx ON saved_videos (user_id, video_id)',
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS video_shares_user_channel_video_idx ON video_shares (user_id, channel, video_id) WHERE user_id IS NOT NULL",
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS follows_follower_followed_idx ON follows (follower_id, followed_id)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS messages_unread_lookup_idx ON messages (conversation_id, created_at, sender_id)',
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS conversation_participants_unread_idx ON conversation_participants (user_id, conversation_id, last_read_at)',
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS message_requests_pending_recipient_idx ON message_requests (recipient_id, created_at DESC) WHERE status = 'pending'",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS notifications_unread_user_idx ON notifications (notifiable_type, notifiable_id, created_at DESC) WHERE read_at IS NULL",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS opportunities_open_idx ON opportunities (published_at DESC, deadline, id DESC) WHERE status = 'published'",
            "CREATE INDEX CONCURRENTLY IF NOT EXISTS comments_feed_idx ON comments (video_id, created_at DESC, id DESC) WHERE parent_id IS NULL AND moderation_status = 'approved'",
        ];

        foreach ($statements as $statement) {
            DB::statement($statement);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([
            'videos_feed_recent_idx', 'videos_feed_rank_idx', 'videos_feed_sport_idx', 'videos_feed_creator_idx',
            'media_feed_ready_idx', 'video_images_media_id_idx', 'feed_preferences_video_exclusion_idx',
            'feed_preferences_creator_exclusion_idx', 'feed_preferences_sport_exclusion_idx',
            'video_likes_user_video_idx', 'saved_videos_user_video_idx', 'video_shares_user_channel_video_idx',
            'follows_follower_followed_idx', 'messages_unread_lookup_idx', 'conversation_participants_unread_idx',
            'message_requests_pending_recipient_idx', 'notifications_unread_user_idx', 'opportunities_open_idx',
            'comments_feed_idx',
        ] as $index) {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$index}");
        }
    }
};
