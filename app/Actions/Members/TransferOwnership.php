<?php

namespace App\Actions\Members;

use App\Actions\Members\Concerns\LocksOrganization;
use App\Enums\OrganizationRole;
use App\Facades\Audit;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferOwnership
{
    use LocksOrganization;

    /**
     * Make $to an owner and step $from down to admin.
     *
     * @throws ValidationException
     */
    public function handle(Membership $from, Membership $to): void
    {
        if ($from->organization_id !== $to->organization_id || $from->id === $to->id) {
            throw ValidationException::withMessages(['member' => __('Choose another member of this organization.')]);
        }

        DB::transaction(function () use ($from, $to): void {
            $this->lock($from->organization);
            $from->refresh();
            $to->refresh();

            if ($from->role !== OrganizationRole::Owner) {
                throw ValidationException::withMessages(['member' => __('Only an owner can transfer ownership.')]);
            }

            $to->update(['role' => OrganizationRole::Owner]);
            $from->update(['role' => OrganizationRole::Admin]);

            Audit::record('ownership.transferred', $from->organization, $to->user, ['previous_owner' => $from->user->email]);
        });
    }
}
