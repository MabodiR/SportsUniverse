<?php

namespace Database\Seeders;

use App\Domain\Media\Models\Media;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class SportsMediaCatalogSeeder extends Seeder
{
    private const API = 'https://commons.wikimedia.org/w/api.php';
    private const LICENSES = ['cc0', 'public domain', 'cc by 2.0', 'cc by 3.0', 'cc by 4.0', 'cc by-sa 2.0', 'cc by-sa 3.0', 'cc by-sa 4.0'];

    public function run(): void
    {
        $disk = config('media.disk');
        $ownerId = DB::table('users')->where('status', 'active')->orderBy('id')->value('id');
        if (! $ownerId) throw new RuntimeException('An active user is required to own imported performance media.');
        $imported = 0;
        // Exact sports footage selections prevent broad searches importing unrelated clips.
        $catalogue = [
            'Football' => 'File:Footballers.webm',
            'Basketball' => 'File:Basketball-Basic Types of Dribbling.webm',
            'Swimming' => 'File:Backstroke Underwater.webm',
            'Tennis' => 'File:Kudrinskaya Square Building game tennis summer in moscow 2025.webm',
            'Volleyball' => 'File:2012-08-04-olympics-beach-volleyball.webm',
        ];
        foreach ($catalogue as $name => $title) {
            $sport = DB::table('sports')->where('name', $name)->first();
            if (! $sport) throw new RuntimeException("Missing sport: {$name}");
            $assets = $this->search($title, 1);
            if (count($assets) !== 1) throw new RuntimeException("Could not resolve licensed sports footage: {$title}");
            $imported += $this->store($assets[0], $sport, $ownerId, $disk) ? 1 : 0;
            $this->command?->info("{$name}: source ready.");
        }

        $total = Media::where('collection', 'performance-sports')->count();
        if ($total === 0) throw new RuntimeException('No reusable sports media could be imported from Wikimedia Commons.');
        $this->command?->info("Sports media catalogue ready: {$total} assets ({$imported} newly downloaded). Each asset retains its source and licence metadata.");
    }

    private function search(string $sport, int $limit): array
    {
        $response = Http::withHeaders(['User-Agent' => 'SportsUniversePerformanceSeeder/1.0 ('.config('app.url').')'])
            ->timeout(45)->retry(3, 750)->get(self::API, [
                'action' => 'query', 'format' => 'json', 'formatversion' => 2, 'titles' => $sport,
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
        if (Media::where('collection', 'performance-sports')->where('original_name', 'commons-'.$key.'.mp4')->exists()) return false;
        $maxBytes = str_starts_with($asset['mime'], 'image/') ? 12 * 1024 * 1024 : (int) config('scale.mass_feed_max_video_mb', 80) * 1024 * 1024;
        if (($asset['reported_size'] ?? 0) > $maxBytes) return false;
        $response = Http::withHeaders(['User-Agent' => 'SportsUniversePerformanceSeeder/1.0 ('.config('app.url').')'])->timeout(180)->retry(2, 1000)->get($asset['download_url']);
        if (! $response->successful() || strlen($response->body()) > $maxBytes) return false;
        $extension = 'mp4';
        $input = tempnam(sys_get_temp_dir(), 'sports-source-');
        $output = $input.'.mp4';
        try {
            file_put_contents($input, $response->body());
            $process = new Process([config('media.ffmpeg_binary', 'ffmpeg'), '-y', '-i', $input,
                '-t', '60', '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2', '-c:v', 'libx264',
                '-preset', 'fast', '-crf', '25', '-pix_fmt', 'yuv420p', '-an', '-movflags', '+faststart', $output]);
            $process->setTimeout(300)->mustRun();
            $body = file_get_contents($output);
            $asset['mime'] = 'video/mp4';
            $path = "performance/sports/{$sport->id}/{$key}.{$extension}";
            if (! Storage::disk($disk)->put($path, $body)) throw new RuntimeException('Failed to store sports video.');
        } finally {
            if (is_file($input)) unlink($input);
            if (is_file($output)) unlink($output);
        }
        Media::create(['public_id' => (string) Str::ulid(), 'user_id' => $ownerId, 'kind' => str_starts_with($asset['mime'], 'image/') ? 'image' : 'video', 'collection' => 'performance-sports', 'disk' => $disk, 'path' => $path, 'original_name' => "commons-{$key}.{$extension}", 'mime_type' => $asset['mime'], 'size_bytes' => strlen($body), 'checksum_sha256' => hash('sha256', $body), 'processing_status' => 'ready', 'moderation_status' => 'approved', 'width' => $asset['width'], 'height' => $asset['height'], 'metadata' => ['source' => 'Wikimedia Commons', 'source_url' => $asset['source_url'], 'author' => $asset['author'], 'license' => $asset['license'], 'license_url' => $asset['license_url'], 'credit' => $asset['credit'], 'sport_id' => $sport->id, 'sport' => $sport->name, 'performance_test_asset' => true, 'changes' => 'Converted to silent MP4; limited to 60 seconds'], 'processed_at' => now()]);
        return true;
    }

    private function allowedLicense(string $license): bool { return in_array(strtolower($license), self::LICENSES, true); }
    private function plain(?string $value): string { return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5)); }
    private function extension(string $mime): string { return match ($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'video/webm' => 'webm', 'video/ogg', 'application/ogg' => 'ogv', default => 'bin' }; }
}
