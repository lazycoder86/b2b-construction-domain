<?php

namespace App\Jobs;

use App\Contracts\Billing\BillingGateway;
use App\Models\Organization;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Keep a per-seat subscription's quantity equal to the member count.
 * Unique per organization so bursts of membership changes collapse into one API call.
 */
class SyncSubscriptionSeats implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 30;

    public function __construct(public int $organizationId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->organizationId;
    }

    public function handle(BillingGateway $billing): void
    {
        $organization = Organization::find($this->organizationId);

        if ($organization === null || ! $billing->enabled()) {
            return;
        }

        $entitlements = $organization->entitlements();

        if ($entitlements->subscription === null || ! $entitlements->plan->perSeat) {
            return;
        }

        $billing->syncSeats($organization, $organization->memberships()->count());
    }
}
