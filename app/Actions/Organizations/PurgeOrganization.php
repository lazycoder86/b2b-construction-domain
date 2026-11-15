<?php

namespace App\Actions\Organizations;

use App\Contracts\Billing\BillingGateway;
use App\Facades\Audit;
use App\Models\Organization;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeOrganization
{
    public function __construct(protected BillingGateway $billing) {}

    /**
     * Permanently delete an organization and everything it owns. Database rows go via
     * foreign-key cascades (memberships, invitations, usage, subscriptions, audit entries
     * and every tenant table with `organization_id ... cascadeOnDelete()`). Feature flag
     * values are removed by Organization's forceDeleted hook.
     */
    public function handle(Organization $organization): void
    {
        if ($this->billing->enabled()) {
            try {
                $this->billing->cancelNow($organization);
                $this->billing->deleteCustomer($organization);
            } catch (Throwable $exception) {
                // Don't keep personal data around because Stripe is unreachable; flag it for follow-up.
                Log::error('Could not delete the Stripe customer of a purged organization.', [
                    'organization_public_id' => $organization->public_id,
                    'stripe_id' => $organization->stripe_id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        Storage::disk('local')->deleteDirectory("exports/{$organization->public_id}");

        // Platform-level entry: the organization's own audit trail is deleted with it.
        Audit::record('organization.purged', organization: false, subject: $organization, metadata: [
            'public_id' => $organization->public_id,
            'stripe_id' => $organization->stripe_id,
        ], subjectLabel: $organization->name);

        $organization->forceDelete();
    }
}
