<?php

namespace App\Policies\Concerns;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Support\Tenancy\CurrentOrganization;

trait ResolvesMembership
{
    /**
     * The acting user's membership in the organization. Reuses the membership
     * resolved by the tenant middleware when it matches; otherwise queries.
     */
    protected function membershipOf(User $user, Organization|int $organization): ?Membership
    {
        $organizationId = $organization instanceof Organization ? $organization->id : $organization;
        $current = app(CurrentOrganization::class);

        if ($current->id() === $organizationId && $current->membership()?->user_id === $user->id) {
            return $current->membership();
        }

        return Membership::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->first();
    }

    protected function hasPermission(User $user, Organization|int $organization, string $permission): bool
    {
        return $this->membershipOf($user, $organization)?->role->can($permission) ?? false;
    }
}
