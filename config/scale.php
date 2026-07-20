<?php

return [
    'redis_counters' => (bool) env('SCALE_REDIS_COUNTERS', false),
    'feed_cache_seconds' => (int) env('FEED_CACHE_SECONDS', 120),
    'feed_candidate_size' => (int) env('FEED_CANDIDATE_SIZE', 5_000),
    'feed_rebuild_candidate_size' => (int) env('FEED_REBUILD_CANDIDATE_SIZE', 100_000),
    'recommendation_size' => (int) env('RECOMMENDATION_FEED_SIZE', 500),
    'recommendation_active_days' => (int) env('RECOMMENDATION_ACTIVE_DAYS', 14),
    'video_views_table' => env('VIDEO_VIEWS_TABLE', 'video_view_events'),
    'mass_feed_post_count' => (int) env('MASS_FEED_POST_COUNT', 5_000_000),
    'mass_feed_batch_size' => (int) env('MASS_FEED_BATCH_SIZE', 100_000),
    'mass_feed_with_topics' => env('MASS_FEED_WITH_TOPICS', true),
    'mass_feed_import_online_media' => env('MASS_FEED_IMPORT_ONLINE_MEDIA', true),
    'mass_feed_images_per_sport' => (int) env('MASS_FEED_IMAGES_PER_SPORT', 12),
    'mass_feed_videos_per_sport' => (int) env('MASS_FEED_VIDEOS_PER_SPORT', 2),
    'mass_feed_max_video_mb' => (int) env('MASS_FEED_MAX_VIDEO_MB', 80),
    'cdn_url' => rtrim((string) env('MEDIA_CDN_URL', ''), '/'),
];
