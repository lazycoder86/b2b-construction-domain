<?php

namespace App\Http\Requests\Organizations;

use App\Concerns\OrganizationValidationRules;
use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    use OrganizationValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->organization());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => $this->organizationNameRules(),
            'slug' => $this->organizationSlugRules($this->organization()),
        ];
    }

    public function organization(): Organization
    {
        /** @var Organization */
        return $this->route('organization');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => strtolower(trim((string) $this->input('slug')))]);
    }
}
