<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\User;
use App\Policies\Concerns\ResolvesMembership;

class InvitationPolicy
{
    use ResolvesMembership;

    /**
     * Resend or cancel. Inviting with a role you can't assign yourself is not
     * allowed, and the same applies to managing such invitations.
     */
    public function manage(User $user, Invitation $invitation): bool
    {
        $actor = $this->membershipOf($user, $invitation->organization_id);

        return $actor !== null
            && $actor->role->can('members.invite')
            && $actor->role->canAssign($invitation->role);
    }

    public function invite(User $user, int $organizationId, OrganizationRole $role): bool
    {
        $actor = $this->membershipOf($user, $organizationId);

        return $actor !== null
            && $actor->role->can('members.invite')
            && $actor->role->canAssign($role);
    }
}
