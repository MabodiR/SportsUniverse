<?php

namespace Database\Seeders;

use App\Domain\Media\Models\Media;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SportsMediaCatalogSeeder extends Seeder
{
    private const API = 'https://commons.wikimedia.org/w/api.php';
    private const LICENSES = ['cc0', 'public domain', 'cc by 2.0', 'cc by 3.0', 'cc by 4.0', 'cc by-sa 2.0', 'cc by-sa 3.0', 'cc by-sa 4.0'];

    public function run(): void
    {
        $disk = config('media.disk');
        $ownerId = DB::table('users')->where('status', 'active')->orderBy('id')->value('id');
        if (! $ownerId) throw new RuntimeException('An active user is required to own imported performance media.');
        $imagesPerSport = (int) config('scale.mass_feed_images_per_sport', 12);
        $videosPerSport = (int) config('scale.mass_feed_videos_per_sport', 2);
        $imported = 0;

        foreach (DB::table('sports')->orderBy('id')->get(['id', 'name']) as $sport) {
            $assets = $this->search($sport->name, max($imagesPerSport * 3, 30));
            $images = collect($assets)->filter(fn ($asset) => str_starts_with($asset['mime'], 'image/'))->take($imagesPerSport);
            $videos = collect($assets)->filter(fn ($asset) => str_starts_with($asset['mime'], 'video/') || $asset['mime'] === 'application/ogg')->take($videosPerSport);
            if ($videos->count() < $videosPerSport) {
                $videos = $videos->concat(collect($this->search($sport->name.' video', max($videosPerSport * 8, 30)))->filter(fn ($asset) => str_starts_with($asset['mime'], 'video/') || $asset['mime'] === 'application/ogg'))->unique('source_url')->take($videosPerSport);
            }
            foreach ($images->concat($videos) as $asset) $imported += $this->store($asset, $sport, $ownerId, $disk) ? 1 : 0;
            $this->command?->info("{$sport->name}: {$images->count()} images and {$videos->count()} videos selected.");
        }

        $total = Media::where('collection', 'performance-sports')->count();
        if ($total === 0) throw new RuntimeException('No reusable sports media could be imported from Wikimedia Commons.');
        $this->command?->info("Sports media catalogue ready: {$total} assets ({$imported} newly downloaded). Each asset retains its source and licence metadata.");
    }

    private function search(string $sport, int $limit): array
    {
        $response = Http::withHeaders(['User-Agent' => 'SportsUniversePerformanceSeeder/1.0 ('.config('app.url').')'])
            ->timeout(45)->retry(3, 750)->get(self::API, [
                'action' => 'query', 'format' => 'json', 'formatversion' => 2, 'generator' => 'search',
                'gsrsearch' => $sport.' sport', 'gsrnamespace' => 6, 'gsrlimit' => min(50, $limit),
                'prop' => 'imageinfo', 'iiprop' => 'url|mime|size|sha1|extmetadata', 'iiurlwidth' => 1280,
            ])->throw()->json();

        return collect(data_get($response, 'query.pages', []))->map(function ($page) use ($sport) {
            $info = data_get($page, 'imageinfo.0', []);
            $metadata = $info['extmetadata'] ?? [];
            $license = $this->plain(data_get($metadata, 'LicenseShortName.value'));
            $mime = strtolower((string) ($info['mime'] ?? ''));
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'video/webm', 'video/ogg', 'application/ogg'], true)) return null;
            $sourceUrl = (string) ($info['descriptionurl'] ?? '');
            $downloadUrl = str_starts_with($mime, 'image/') ? ($info['thumburl'] ?? $info['url'] ?? null) : ($info['url'] ?? null);
            if (! $downloadUrl || ! $sourceUrl || ! $this->allowedLicense($license)) return null;
            return ['sport' => $sport, 'title' => (string) ($page['title'] ?? $sport), 'mime' => $mime, 'download_url' => $downloadUrl, 'source_url' => $sourceUrl, 'author' => $this->plain(data_get($metadata, 'Artist.value')) ?: 'Wikimedia Commons contributor', 'license' => $license, 'license_url' => $this->plain(data_get($metadata, 'LicenseUrl.value')), 'credit' => $this->plain(data_get($metadata, 'Credit.value')), 'width' => $info['thumbwidth'] ?? $info['width'] ?? null, 'height' => $info['thumbheight'] ?? $info['height'] ?? null, 'sha1' => $info['sha1'] ?? null, 'reported_size' => $info['size'] ?? null];
        })->filter()->values()->all();
    }

    private function store(array $asset, object $sport, int $ownerId, string $disk): bool
    {
        $key = sha1($asset['source_url']);
        if (Media::where('collection', 'performance-sports')->where('original_name', 'commons-'.$key.'.'.$this->extension($asset['mime']))->exists()) return false;
        $maxBytes = str_starts_with($asset['mime'], 'image/') ? 12 * 1024 * 1024 : (int) config('scale.mass_feed_max_video_mb', 80) * 1024 * 1024;
        if (($asset['reported_size'] ?? 0) > $maxBytes) return false;
        $response = Http::withHeaders(['User-Agent' => 'SportsUniversePerformanceSeeder/1.0 ('.config('app.url').')'])->timeout(180)->retry(2, 1000)->get($asset['download_url']);
        if (! $response->successful() || strlen($response->body()) > $maxBytes) return false;
        $extension = $this->extension($asset['mime']);
        $path = "performance/sports/{$sport->id}/{$key}.{$extension}";
        Storage::disk($disk)->put($path, $response->body());
        Media::create(['public_id' => (string) Str::ulid(), 'user_id' => $ownerId, 'kind' => str_starts_with($asset['mime'], 'image/') ? 'image' : 'video', 'collection' => 'performance-sports', 'disk' => $disk, 'path' => $path, 'original_name' => "commons-{$key}.{$extension}", 'mime_type' => $asset['mime'], 'size_bytes' => strlen($response->body()), 'checksum_sha256' => hash('sha256', $response->body()), 'processing_status' => 'ready', 'moderation_status' => 'approved', 'width' => $asset['width'], 'height' => $asset['height'], 'metadata' => ['source' => 'Wikimedia Commons', 'source_url' => $asset['source_url'], 'author' => $asset['author'], 'license' => $asset['license'], 'license_url' => $asset['license_url'], 'credit' => $asset['credit'], 'sport_id' => $sport->id, 'sport' => $sport->name, 'performance_test_asset' => true], 'processed_at' => now()]);
        return true;
    }

    private function allowedLicense(string $license): bool { return in_array(strtolower($license), self::LICENSES, true); }
    private function plain(?string $value): string { return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5)); }
    private function extension(string $mime): string { return match ($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'video/webm' => 'webm', 'video/ogg', 'application/ogg' => 'ogv', default => 'bin' }; }
}
