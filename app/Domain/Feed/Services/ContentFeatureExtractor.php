<?php

namespace App\Domain\Feed\Services;

use App\Domain\Feed\Models\Video;
use Illuminate\Support\Str;

class ContentFeatureExtractor
{
    public function extract(Video $video): array
    {
        $video->loadMissing('sport', 'user.athleteProfile.sport');
        $topics = collect([
            ['dimension' => 'sport', 'value' => $video->sport?->name ?? $video->user->athleteProfile?->sport?->name, 'weight' => 2.0, 'source' => 'metadata'],
            ['dimension' => 'country', 'value' => $video->country_code, 'weight' => 1.7, 'source' => 'metadata'],
            ['dimension' => 'league', 'value' => $video->league, 'weight' => 2.0, 'source' => 'metadata'],
            ['dimension' => 'team', 'value' => $video->team, 'weight' => 2.0, 'source' => 'metadata'],
            ['dimension' => 'competition', 'value' => $video->competition, 'weight' => 1.8, 'source' => 'metadata'],
            ['dimension' => 'content_type', 'value' => $video->content_type, 'weight' => 1.4, 'source' => 'metadata'],
            ['dimension' => 'language', 'value' => $video->language, 'weight' => .8, 'source' => 'metadata'],
        ])->concat(collect($video->hashtags ?? [])->map(fn ($value) => ['dimension' => 'hashtag', 'value' => $value, 'weight' => 1.0, 'source' => 'caption']))
            ->concat(collect($video->skill_tags ?? [])->map(fn ($value) => ['dimension' => 'skill', 'value' => $value, 'weight' => 1.4, 'source' => 'metadata']))
            ->filter(fn ($topic) => filled($topic['value']))
            ->map(fn ($topic) => [...$topic, 'value' => Str::of((string) $topic['value'])->lower()->squish()->limit(160, '')->value()])
            ->unique(fn ($topic) => $topic['dimension'].':'.$topic['value'])->values();

        $text = collect([$video->caption, $video->transcript, $video->detected_text, $topics->pluck('value')->join(' ')])->filter()->join(' ');

        return ['topics' => $topics, 'labels' => $topics->groupBy('dimension')->map->pluck('value')->all(), 'embedding' => $this->embedding($text)];
    }

    private function embedding(string $text, int $dimensions = 64): array
    {
        $vector = array_fill(0, $dimensions, 0.0);
        preg_match_all('/[\pL\pN]{2,}/u', mb_strtolower($text), $matches);
        foreach ($matches[0] as $token) {
            $hash = unpack('N', substr(hash('sha256', $token, true), 0, 4))[1];
            $vector[$hash % $dimensions] += ($hash & 1) ? 1.0 : -1.0;
        }
        $norm = sqrt(array_sum(array_map(fn ($value) => $value * $value, $vector))) ?: 1.0;

        return array_map(fn ($value) => round($value / $norm, 6), $vector);
    }
}
