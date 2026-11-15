<?php

namespace App\Actions\Organizations;

use App\Contracts\Billing\BillingGateway;
use App\Enums\OrganizationStatus;
use App\Facades\Audit;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationDeletionScheduled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ScheduleOrganizationDeletion
{
    public function __construct(protected BillingGateway $billing) {}

    /**
     * Stop billing now, lock everyone but owners out, and purge after the grace period.
     * Authorization (OrganizationPolicy::delete + password) is the caller's job.
     */
    public function handle(Organization $organization, User $owner): void
    {
        // Stripe first: if cancelling fails, nothing changes and the owner can retry.
        if ($this->billing->enabled()) {
            $this->billing->cancelNow($organization);
        }

        DB::transaction(function () use ($organization, $owner): void {
            $organization->forceFill([
                'status' => OrganizationStatus::PendingDeletion,
                'deletion_scheduled_at' => now()->addDays((int) config('tenancy.deletion_grace_days')),
            ])->save();

            Audit::record('organization.deletion_scheduled', $organization, $organization, [
                'purge_at' => $organization->deletion_scheduled_at?->toIso8601String(),
            ], actor: $owner);
        });

        Notification::send(
            $organization->owners()->with('user')->get()->pluck('user'),
            new OrganizationDeletionScheduled(
                organizationName: $organization->name,
                purgeOn: $organization->deletion_scheduled_at?->toFormattedDayDateString() ?? '',
                restoreUrl: route('organizations.deletion.show', ['organization' => $organization->slug]),
            ),
        );
    }
}
