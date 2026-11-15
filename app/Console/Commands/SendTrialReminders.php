<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Notifications\TrialEndingSoon;
use App\Support\Billing\BillingStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('billing:send-trial-reminders')]
#[Description('Email owners of organizations whose trial ends soon (runs daily).')]
class SendTrialReminders extends Command
{
    public function handle(): int
    {
        $days = (int) config('billing.trial.reminder_days_before');
        $sent = 0;

        Organization::query()
            ->where('status', 'active')
            ->whereNull('trial_reminder_sent_at')
            ->where('trial_ends_at', '>', now())
            ->where('trial_ends_at', '<=', now()->addDays($days))
            ->each(function (Organization $organization) use (&$sent): void {
                $entitlements = $organization->entitlements();

                // Already subscribed (or on a custom plan): no reminder needed.
                if ($entitlements->status !== BillingStatus::Trialing) {
                    return;
                }

                Notification::send(
                    $organization->owners()->with('user')->get()->pluck('user'),
                    new TrialEndingSoon(
                        organizationName: $organization->name,
                        planName: $entitlements->plan->name,
                        endsOn: $organization->trial_ends_at?->toFormattedDayDateString() ?? '',
                        billingUrl: route('tenant.billing.show', ['organization' => $organization->slug]),
                    ),
                );

                $organization->forceFill(['trial_reminder_sent_at' => now()])->saveQuietly();
                $sent++;
            });

        $this->components->info("Sent {$sent} trial reminder(s).");

        return self::SUCCESS;
    }
}
