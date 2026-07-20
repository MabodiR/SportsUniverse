<?php

namespace App\Domain\Feed\Services;

use App\Domain\Feed\Models\Video;
use Illuminate\Support\Facades\Http;

class AutomatedContentAnalyzer
{
    public function analyze(Video $video): array
    {
        $endpoint = config('recommendations.analysis_url');
        if (! $endpoint || (! $video->media && $video->images->isEmpty())) {
            return [];
        }

        return Http::acceptJson()->withToken((string) config('recommendations.analysis_token'))
            ->timeout(config('recommendations.analysis_timeout'))
            ->retry(2, 500)
            ->post(rtrim($endpoint, '/').'/v1/analyze', [
                'video_id' => $video->public_id,
                'media_url' => route('videos.stream', $video),
                'media_kind' => $video->media ? 'video' : 'images',
                'image_urls' => $video->images->map(fn ($image) => route('media.public', $image))->values()->all(),
                'caption' => $video->caption,
                'hashtags' => $video->hashtags ?? [],
                'metadata' => ['sport' => $video->sport?->name, 'country_code' => $video->country_code, 'league' => $video->league, 'team' => $video->team, 'competition' => $video->competition, 'content_type' => $video->content_type, 'language' => $video->language],
            ])->throw()->json();
    }
}
