<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\ResolvesMembership;

class OrganizationPolicy
{
    use ResolvesMembership;

    public function view(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization, 'organization.view');
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization, 'organization.update');
    }

    /**
     * Security policies such as requiring MFA for all members.
     */
    public function manageSecurity(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization, 'organization.security');
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization, 'organization.delete');
    }

    public function viewMembers(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization, 'members.view');
    }

    public function inviteMembers(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization, 'members.invite');
    }

    public function updateMembers(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization, 'members.update');
    }

    public function viewBilling(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization, 'billing.view');
    }

    public function manageBilling(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization, 'billing.manage');
    }

    public function viewAuditLog(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization, 'audit.view');
    }

    public function export(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization, 'organization.export');
    }

    public function transferOwnership(User $user, Organization $organization): bool
    {
        return $this->hasPermission($user, $organization, 'ownership.transfer');
    }
}
