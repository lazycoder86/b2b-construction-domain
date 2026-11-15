<?php

namespace App\Actions\Members;

use App\Actions\Members\Concerns\LocksOrganization;
use App\Enums\OrganizationRole;
use App\Facades\Audit;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeMemberRole
{
    use LocksOrganization;

    /**
     * Authorization (MembershipPolicy::update) is the caller's job.
     *
     * @throws ValidationException
     */
    public function handle(Membership $membership, OrganizationRole $role): void
    {
        DB::transaction(function () use ($membership, $role): void {
            $this->lock($membership->organization);
            $membership->refresh();

            if ($membership->role === $role) {
                return;
            }

            if ($membership->role === OrganizationRole::Owner) {
                $this->ensureAnotherOwnerRemains($membership->organization);
            }

            $from = $membership->role;
            $membership->update(['role' => $role]);

            Audit::record('member.role_changed', $membership->organization, $membership->user, ['from' => $from->value, 'to' => $role->value]);
        });
    }
}
