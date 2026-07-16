<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Domain\Feed\Models\FeedPreference;
use App\Domain\Feed\Models\Video;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedPreferenceController extends Controller
{
    public function store(Request $request, Video $video): JsonResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['post', 'creator', 'sport', 'similar'])],
            'reason' => ['required', Rule::in(['irrelevant', 'repetitive', 'low_quality', 'not_my_sport', 'other'])],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        $sportId = $video->sport_id ?? $video->user()->with('athleteProfile')->first()?->athleteProfile?->sport_id;
        $preference = FeedPreference::updateOrCreate(
            ['user_id' => $request->user()->id, 'video_id' => $video->id, 'scope' => $data['scope']],
            ['creator_id' => $video->user_id, 'sport_id' => $sportId, 'reason' => $data['reason'], 'details' => $data['details'] ?? null, 'metadata' => ['hashtags' => $video->hashtags ?? []]],
        );

        return response()->json(['message' => 'Your feed preference was saved.', 'data' => ['id' => $preference->id, 'scope' => $preference->scope]], 201);
    }
}
