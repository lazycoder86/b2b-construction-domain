<?php

namespace App\Http\Requests\Organizations;

use App\Concerns\OrganizationValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrganizationRequest extends FormRequest
{
    use OrganizationValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => $this->organizationNameRules(),
            // Optional: derived from the name when omitted.
            'slug' => $this->organizationSlugRules(required: false),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge(['slug' => strtolower(trim((string) $this->input('slug')))]);
        }
    }
}
