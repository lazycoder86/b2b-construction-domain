<?php

namespace App\Support\Billing;

use App\Contracts\Billing\SubscriptionProvider;
use App\Models\Organization;
use Illuminate\Support\Facades\Log;

/**
 * Reads the organization's subscription from Cashier's local mirror tables
 * (kept in sync by Stripe webhooks). No Stripe API calls happen here.
 */
class CashierSubscriptionProvider implements SubscriptionProvider
{
    public function __construct(protected PlanCatalog $plans) {}

    public function current(Organization $organization): ?SubscriptionState
    {
        $subscription = $organization->subscription(config('billing.subscription_type'));

        // valid(): active (incl. past_due, see AppServiceProvider), on trial, or cancelled but in grace period.
        if ($subscription === null || ! $subscription->valid()) {
            return null;
        }

        // Single-price subscriptions store the price on the subscription; otherwise use the first item.
        $price = $subscription->stripe_price ?? $subscription->items->first()?->getAttribute('stripe_price');
        $plan = is_string($price) ? $this->plans->forStripePrice($price) : null;

        if ($plan === null) {
            Log::warning('Subscription has a Stripe price that is not in config/billing.php.', [
                'organization_id' => $organization->id,
                'subscription_id' => $subscription->id,
                'stripe_price' => $price,
            ]);

            return null;
        }

        $status = match (true) {
            $subscription->pastDue() => SubscriptionStatus::PastDue,
            $subscription->onGracePeriod() => SubscriptionStatus::Cancelling,
            default => SubscriptionStatus::Active,
        };

        return new SubscriptionState(
            planKey: $plan->key,
            status: $status,
            interval: $plan->intervalForStripePrice($price),
            endsAt: $subscription->ends_at?->toImmutable(),
        );
    }
}
