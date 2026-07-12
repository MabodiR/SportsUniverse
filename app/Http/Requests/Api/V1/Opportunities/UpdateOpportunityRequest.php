<?php

namespace App\Http\Requests\Api\V1\Opportunities;

class UpdateOpportunityRequest extends StoreOpportunityRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('opportunity')) ?? false;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        foreach ($rules as &$fieldRules) {
            $fieldRules = array_map(fn ($rule) => $rule === 'required' ? 'sometimes' : $rule, $fieldRules);
        }

return $rules;
    }
}
