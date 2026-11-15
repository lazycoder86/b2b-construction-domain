<?php

namespace App\Concerns;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait OrganizationValidationRules
{
    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function organizationNameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Slugs are part of every tenant URL: lowercase letters, digits and single hyphens.
     * Soft-deleted organizations keep their slug reserved.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function organizationSlugRules(?Organization $ignore = null, bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            'min:3',
            'max:50',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::unique(Organization::class, 'slug')->ignore($ignore?->id),
        ];
    }
}
