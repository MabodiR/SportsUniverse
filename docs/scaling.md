# SportsUniverse scaling services

Local development can continue with one PostgreSQL node, local media and database queues. Production should set:

- `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`
- `SCALE_REDIS_COUNTERS=true` to buffer high-frequency view counters
- `DB_WRITE_HOST` to the PostgreSQL primary and `DB_READ_HOSTS` to a comma-separated replica list
- `MEDIA_DISK=media` and the `MEDIA_*` object-storage credentials
- `MEDIA_CDN_URL` to the CDN origin used for processed public media
- `DISCOVERY_DRIVER=opensearch` and `OPENSEARCH_HOSTS` to the search cluster

Workers and scheduled services:

```bash
php artisan queue:work redis --queue=media,feeds,search,default --tries=3 --timeout=1800
php artisan schedule:work
php artisan discovery:index-profiles --create
php artisan feed:precompute
```

`video_view_events` is range-partitioned monthly. The scheduler creates the next three partitions. Run `php artisan db:partition-video-views --months=6` before the scheduler is first enabled.

Recommendation feeds are generated only for users active during the configured window and cached in Redis. PostgreSQL remains the source of truth; Redis data may always be rebuilt.

Object storage must remain private for original uploads. The CDN should expose only processed post paths, enforce HTTPS, support byte-range requests, and use immutable cache headers for versioned ULID paths.

OpenSearch is a derived index. Create it and perform a full profile import during deployment; application writes queue incremental indexing jobs. Keep database fallback enabled until cluster health and index counts are verified.
