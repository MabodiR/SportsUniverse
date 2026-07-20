<?php

namespace App\Http\Requests\Api\V1\Feed;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'media_id' => ['nullable', 'required_without:image_media_ids', 'string', 'exists:media,public_id'],
            'image_media_ids' => ['nullable', 'required_without:media_id', 'array', 'max:10'],
            'image_media_ids.*' => ['string', 'distinct', 'exists:media,public_id'],
            'cover_media_id' => ['nullable', 'string', Rule::in($this->input('image_media_ids', []))],
            'sport_id' => ['nullable', 'exists:sports,id'],
            'caption' => ['nullable', 'string', 'max:2200'],
            'hashtags' => ['nullable', 'array', 'max:20'],
            'hashtags.*' => ['string', 'max:60'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'league' => ['nullable', 'string', 'max:120'],
            'team' => ['nullable', 'string', 'max:120'],
            'competition' => ['nullable', 'string', 'max:160'],
            'content_type' => ['nullable', Rule::in(['match_highlight', 'training', 'analysis', 'interview', 'news', 'skills', 'behind_the_scenes', 'other'])],
            'language' => ['nullable', 'string', 'max:12'],
            'skill_tags' => ['nullable', 'array', 'max:20'],
            'skill_tags.*' => ['string', 'max:60'],
            'location_name' => ['nullable', 'string', 'max:160'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'comments_enabled' => ['nullable', 'boolean'],
            'post_type' => ['nullable', Rule::in(['post', 'story'])],
            'visibility' => ['nullable', Rule::in(['public', 'followers', 'private'])],
            'publish' => ['nullable', 'boolean'],
        ];
    }
}
