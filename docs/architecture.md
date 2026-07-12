# SportUniverse Architecture

SportUniverse is a modular Laravel monolith with versioned REST APIs. Domain logic lives under `app/Domain`, transport concerns under `app/Http`, and API routes under `routes/api/v1`. This keeps module boundaries explicit without introducing distributed-system overhead prematurely.

## Runtime services

- PostgreSQL is the system of record.
- Redis backs cache, queues, sessions, rate limits, and realtime coordination.
- OpenSearch provides profile and talent discovery indexes.
- S3-compatible storage (including Cloudflare R2) stores media.
- Laravel Reverb provides WebSocket transport for messaging and notifications.
- Queue workers run media analysis and FFmpeg transcoding jobs.
- Sanctum issues first-party web and mobile API tokens.
- Filament will provide the first administration interface.

## Module sequence

1. Authentication and onboarding
2. Profiles and sports taxonomy
3. Media and video processing
4. Video feed and community engagement
5. Talent discovery and OpenSearch
6. Messaging and Reverb
7. Opportunities
8. Notifications
9. Filament moderation and administration
10. Analytics and scale hardening

## Authentication and onboarding

Registration accepts email or phone identity and returns a Sanctum token. Onboarding is resumable and optional fields never prevent access to the feed. Roles use Spatie Permission. Profile completeness is calculated by the server using weighted persisted sections and cannot be supplied by clients.

Current endpoints are under `/api/v1`: `auth/register`, `auth/login`, `auth/logout`, `me`, and the `onboarding/*` workflow.

## Profiles and sports taxonomy

Profiles use stable public slugs, explicit visibility, indexed availability, and normalized sport/position references. Public responses omit completeness unless viewed by the owner or an administrator. Position validation enforces that a selected position belongs to the selected sport.

The profile module exposes `sports`, `profile`, `profile/athlete`, and public `profiles/{slug}` endpoints under `/api/v1`.

## Media lifecycle

Media objects are stored privately on the configured `MEDIA_DISK`. API downloads always pass through policy checks. Upload records track processing and moderation independently, use ULID public identifiers, store SHA-256 checksums, and never expose internal database identifiers or storage paths.

`ProcessMedia` jobs run on the `media` queue. Images are inspected for dimensions. Video workers use argument-array FFprobe and FFmpeg processes, generate thumbnails, and record duration, dimensions, and codec metadata. Failures are persisted without making incomplete media public.

## Video feed and engagement

Only approved, fully processed media can be published. Feed reads use denormalized counters and composite publication indexes; likes, saves, comments, shares, follows, and views retain normalized source records. Unique constraints prevent duplicate likes, saves, follows, and daily authenticated view counts.

The For You feed uses an initial engagement score with cursor pagination. The ranking action is intentionally replaceable when analytics data is sufficient for a personalized recommender.

## Talent discovery

Discovery uses an engine contract with OpenSearch in production and a database fallback for local development. Public profile documents denormalize role, sport, position, location, availability, age, club, organisation, and completeness fields for fast filtering and ranking. Profile updates enqueue idempotent index writes on the `search` queue.

`php artisan discovery:index-profiles --create` creates the versioned mapping and performs a chunked full index. Search requests are logged with filters, result counts, engine, and latency to support relevance tuning and search analytics.

## Messaging and realtime delivery

Direct messaging begins with a recipient-controlled request. Acceptance atomically creates a uniquely keyed direct conversation, both memberships, and the initial message. Conversation policies protect history and private Reverb channels; blocks apply in both directions and also remove follows and pending requests.

Messages are cursor-paginated and broadcast as `message.sent` on `private-conversations.{publicId}`. Database notifications provide durable offline delivery while Reverb supplies realtime updates. Participant pivots retain independent read and archive state.

## Opportunities marketplace

Club, academy, business, sponsor, and administrator roles can publish opportunities. Sport/position integrity, age ranges, deadlines, ownership, duplicate applications, and document ownership are enforced on the server. Published listings use composite indexes for status, deadline, taxonomy, location, and poster queries.

Applications retain review history fields and notify applicants whenever their status changes. Opportunities with applications are cancelled rather than deleted so application records remain auditable.

## Unified notifications

All durable notifications use Laravel's database notification store and category-based payloads. Social notifications additionally use Laravel's broadcast notification channel for realtime Reverb delivery. Users can independently enable or disable messages, message requests, opportunities, followers, engagement, moderation, profile views, and email digests.

Notification records are always queried through the authenticated user's relationship, preventing cross-user read or deletion access. The API supports filtered inbox pagination, unread counts, individual and bulk read actions, deletion, and preference management.

## Administration and moderation

Filament provides an admin-only panel at `/admin`; access requires both an active account and the `admin` role. Generated resources are read-oriented, while status changes use `ModerationService` so every media, video, report, verification, and account action creates an immutable audit record and an appropriate user notification.

Reports use polymorphic targets and can cover users, videos, comments, and media. Moderation queues prioritize open reports plus pending or flagged media. Opportunities with applications and other auditable user content are never hard-deleted through Filament.

## Analytics and rollups

Profile views are deduplicated per authenticated viewer, profile, and calendar day. Video engagement, follows, applications, searches, and profile views remain available as normalized source events. A scheduled `AggregateDailyMetrics` job writes idempotent user and platform rollups shortly after midnight on the `analytics` queue.

Creator and admin dashboards cache five-minute summaries and accept fixed 7, 30, or 90 day windows. Composite indexes match event deduplication, date-range rollups, creator ownership, and metric-series queries. Raw event retention and table partitioning can be introduced without changing the API contract.

## Web interface

The web client uses Inertia, Vue 3, TypeScript, Tailwind CSS 4, and the same Laravel session/Sanctum identity. Its visual tokens come from the supplied SportUniverse concept: Deep Navy `#0D1B2A`, Universe Blue `#1B63F3`, Community Pink `#E646A2`, Opportunity Orange `#FFB020`, and Growth Green `#18B26B`.

The first web slice provides responsive login and role registration plus the desktop/mobile video-first application shell. The shell follows the concept's fixed left navigation, global search, central immersive video, right interaction rail, athlete completeness card, and suggested-talent panel.
