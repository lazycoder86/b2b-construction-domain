<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationStatus;
use App\Facades\Audit;
use App\Models\Organization;
use App\Models\User;

class RestoreOrganization
{
    /**
     * Cancel a scheduled deletion. The subscription was cancelled when deletion was
     * scheduled, so the organization comes back on its trial/free plan until it resubscribes.
     */
    public function handle(Organization $organization, User $owner): void
    {
        $organization->forceFill([
            'status' => OrganizationStatus::Active,
            'deletion_scheduled_at' => null,
        ])->save();

        Audit::record('organization.deletion_cancelled', $organization, $organization, actor: $owner);
    }
}
