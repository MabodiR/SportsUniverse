<?php

return [
    'redis_counters' => (bool) env('SCALE_REDIS_COUNTERS', false),
    'feed_cache_seconds' => (int) env('FEED_CACHE_SECONDS', 120),
    'recommendation_size' => (int) env('RECOMMENDATION_FEED_SIZE', 500),
    'recommendation_active_days' => (int) env('RECOMMENDATION_ACTIVE_DAYS', 14),
    'video_views_table' => env('VIDEO_VIEWS_TABLE', 'video_view_events'),
    'cdn_url' => rtrim((string) env('MEDIA_CDN_URL', ''), '/'),
];
