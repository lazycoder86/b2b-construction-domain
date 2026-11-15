<?php

namespace App\Actions\Members;

use App\Actions\Members\Concerns\LocksOrganization;
use App\Enums\OrganizationRole;
use App\Facades\Audit;
use App\Models\Membership;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveMember
{
    use LocksOrganization;

    /**
     * Remove a member (or let a member leave). Authorization is the caller's job.
     * Access is revoked immediately: the tenant middleware checks membership on every request.
     *
     * @throws ValidationException
     */
    public function handle(Membership $membership): void
    {
        DB::transaction(function () use ($membership): void {
            $this->lock($membership->organization);
            $membership->refresh();

            if ($membership->role === OrganizationRole::Owner) {
                $this->ensureAnotherOwnerRemains($membership->organization);
            }

            $user = $membership->user;
            $leaving = Auth::id() === $user->id;

            Audit::record($leaving ? 'member.left' : 'member.removed', $membership->organization, $user, ['role' => $membership->role->value]);
            $membership->delete();

            if ($user->current_organization_id === $membership->organization_id) {
                $user->forceFill(['current_organization_id' => null])->saveQuietly();
            }
        });
    }
}
