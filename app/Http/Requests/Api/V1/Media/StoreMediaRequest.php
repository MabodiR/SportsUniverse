<?php

namespace App\Http\Requests\Api\V1\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['kind' => ['required', Rule::in(['image', 'video', 'document'])], 'collection' => ['nullable', 'string', 'max:40', Rule::in(['profile', 'cover', 'gallery', 'highlights', 'certificates', 'resumes', 'medical', 'identity', 'contracts', 'uploads'])], 'trim_start_ms' => ['nullable', 'integer', 'min:0'], 'trim_end_ms' => ['nullable', 'integer', 'gt:trim_start_ms'], 'file' => ['required', File::default()->max($this->maxKilobytes())]];
    }

    public function after(): array
    {
        return [function ($validator) {
            $file = $this->file('file');
            if (! $file) {
                return;
            }$allowed = match ($this->input('kind')) {
                'image' => ['image/jpeg', 'image/png', 'image/webp'],'video' => ['video/mp4', 'video/quicktime', 'video/webm'],'document' => ['application/pdf', 'image/jpeg', 'image/png'],default => []
            };
            if (! in_array($file->getMimeType(), $allowed, true)) {
                $validator->errors()->add('file', 'The uploaded file type does not match its media kind.');
            }
            if ($this->input('kind') !== 'video' && ($this->filled('trim_start_ms') || $this->filled('trim_end_ms'))) {
                $validator->errors()->add('trim_start_ms', 'Only videos can be trimmed.');
            }
            if ($this->filled('trim_start_ms') && $this->filled('trim_end_ms') && ((int) $this->input('trim_end_ms') - (int) $this->input('trim_start_ms')) > 60000) {
                $validator->errors()->add('trim_end_ms', 'Video clips can be up to 60 seconds long.');
            }
        }];
    }

    private function maxKilobytes(): int
    {
        return match ($this->input('kind')) {
            'video' => config('media.max_video_kb'),'document' => config('media.max_document_kb'),default => config('media.max_image_kb')
        };
    }
}
