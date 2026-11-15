<?php

namespace App\Actions\Members\Concerns;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use Illuminate\Validation\ValidationException;

trait LocksOrganization
{
    /**
     * Lock the organization row so concurrent membership changes are serialized
     * (e.g. two owners demoting each other at the same time). Call inside a transaction.
     */
    protected function lock(Organization $organization): void
    {
        Organization::query()->whereKey($organization->id)->lockForUpdate()->first();
    }

    /**
     * @throws ValidationException
     */
    protected function ensureAnotherOwnerRemains(Organization $organization, string $errorKey = 'member'): void
    {
        if ($organization->memberships()->where('role', OrganizationRole::Owner)->count() <= 1) {
            throw ValidationException::withMessages([
                $errorKey => __('An organization must have at least one owner. Transfer ownership first.'),
            ]);
        }
    }
}
