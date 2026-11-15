<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Membership;
use App\Models\User;
use App\Policies\Concerns\ResolvesMembership;

/**
 * Rules for acting on *another* member. Leaving an organization yourself is
 * handled separately (any member may leave, unless they are the last owner).
 */
class MembershipPolicy
{
    use ResolvesMembership;

    /**
     * Change the target's role to $role.
     */
    public function update(User $user, Membership $target, OrganizationRole $role): bool
    {
        $actor = $this->membershipOf($user, $target->organization_id);

        return $actor !== null
            && $actor->id !== $target->id
            && $actor->role->can('members.update')
            && $this->canManage($actor, $target)
            && $actor->role->canAssign($role);
    }

    public function delete(User $user, Membership $target): bool
    {
        $actor = $this->membershipOf($user, $target->organization_id);

        return $actor !== null
            && $actor->id !== $target->id
            && $actor->role->can('members.remove')
            && $this->canManage($actor, $target);
    }

    /**
     * Only owners may act on owners.
     */
    protected function canManage(Membership $actor, Membership $target): bool
    {
        return $target->role !== OrganizationRole::Owner || $actor->role->can('members.manage-owners');
    }
}
