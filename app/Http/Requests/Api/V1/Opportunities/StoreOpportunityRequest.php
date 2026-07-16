<?php

namespace App\Http\Requests\Api\V1\Opportunities;

use App\Domain\Opportunities\Models\Opportunity;
use App\Domain\Sports\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Opportunity::class) ?? false;
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:200'], 'type' => ['required', Rule::in(['trial', 'job', 'training_camp', 'sponsorship', 'scout_day', 'academy_application'])], 'description' => ['required', 'string', 'max:20000'], 'sport_id' => ['nullable', 'exists:sports,id'], 'position_id' => ['nullable', 'exists:positions,id'], 'country' => ['nullable', 'string', 'size:2'], 'province' => ['nullable', 'string', 'max:120'], 'city' => ['nullable', 'string', 'max:120'], 'is_remote' => ['nullable', 'boolean'], 'minimum_age' => ['nullable', 'integer', 'between:5,100'], 'maximum_age' => ['nullable', 'integer', 'between:5,100', 'gte:minimum_age'], 'requirements' => ['nullable', 'array', 'max:30'], 'requirements.*' => ['string', 'max:500'], 'required_documents' => ['nullable', 'array', 'max:12'], 'required_documents.*.key' => ['required', 'string', 'max:80', 'distinct'], 'required_documents.*.label' => ['required', 'string', 'max:160'], 'required_documents.*.collection' => ['required', Rule::in(['resumes', 'certificates', 'identity', 'medical', 'gallery', 'uploads'])], 'required_documents.*.required' => ['required', 'boolean'], 'deadline' => ['nullable', 'date', 'after:now'], 'publish' => ['nullable', 'boolean']];
    }

    public function after(): array
    {
        return [function ($validator) {
            if ($this->filled('position_id') && $this->filled('sport_id') && ! Position::whereKey($this->integer('position_id'))->where('sport_id', $this->integer('sport_id'))->exists()) {
                $validator->errors()->add('position_id', 'The position does not belong to the selected sport.');
            }
        }];
    }
}
