# Mass feed performance dataset

`MassFeedPostSeeder` creates a resumable, feed-visible dataset across every sport currently stored in the `sports` table. It first imports a bounded catalogue of real sports images and videos from Wikimedia Commons, then safely reuses those local assets instead of downloading millions of physical files. Each post also receives realistic engagement counters, dates, country, league, team, content type, hashtags, and normalized recommendation topics.

Every imported asset is restricted to supported formats and an allow-list of reusable public-domain or Creative Commons licences. Author, licence, licence URL, and original Commons page are stored in `media.metadata` and exposed by feed responses for visible attribution. Approximately 1% of generated posts are videos; each receives its own media database record while referencing a shared downloaded source file.

## Run

Set the desired values in the target environment:

```dotenv
MASS_FEED_POST_COUNT=5000000
MASS_FEED_BATCH_SIZE=100000
MASS_FEED_WITH_TOPICS=true
MASS_FEED_IMPORT_ONLINE_MEDIA=true
MASS_FEED_IMAGES_PER_SPORT=12
MASS_FEED_VIDEOS_PER_SPORT=2
MASS_FEED_MAX_VIDEO_MB=80
```

Then run:

```bash
php artisan db:seed --class=MassFeedPostSeeder --force
```

The seeder uses deterministic `5M…` public IDs and resumes after the highest completed batch. Running it again does not duplicate completed posts.

Keep topics enabled when testing personalized recommendations. Disable them only when measuring basic post storage/query throughput, since topic rows are required for detailed affinity ranking.

This is intentionally not called from `DatabaseSeeder`; normal deployments must not generate the performance dataset accidentally. Run it only against an isolated performance-test environment or a production-like environment where synthetic content is acceptable.
