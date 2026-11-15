<?php

namespace App\Support\Billing;

use App\Contracts\Billing\SubscriptionProvider;
use App\Models\Organization;

/**
 * Works out an organization's plan, in order of precedence:
 *
 *   1. entitlement_overrides.plan  (set by sales/support)
 *   2. paid subscription           (SubscriptionProvider, i.e. Stripe)
 *   3. app-managed trial           (organizations.trial_ends_at in the future)
 *   4. config('billing.default_plan')
 *
 * entitlement_overrides.features / .limits are then layered on top of the plan.
 */
class EntitlementResolver
{
    public function __construct(
        protected PlanCatalog $plans,
        protected SubscriptionProvider $subscriptions,
    ) {}

    public function for(Organization $organization): Entitlements
    {
        $overrides = $organization->entitlement_overrides ?? [];
        $subscription = $this->subscriptions->current($organization);
        $overridePlan = $this->plans->find(is_string($overrides['plan'] ?? null) ? $overrides['plan'] : null);
        $subscribedPlan = $subscription !== null ? $this->plans->find($subscription->planKey) : null;
        $trialEndsAt = $organization->trial_ends_at;
        $onTrial = $trialEndsAt !== null && $trialEndsAt->isFuture();

        [$plan, $status] = match (true) {
            $overridePlan !== null => [$overridePlan, BillingStatus::Custom],
            $subscribedPlan !== null => [$subscribedPlan, BillingStatus::fromSubscription($subscription->status)],
            $onTrial => [$this->plans->trial(), BillingStatus::Trialing],
            default => [$this->plans->default(), BillingStatus::Free],
        };

        /** @var list<string> $extraFeatures */
        $extraFeatures = array_values(array_filter((array) ($overrides['features'] ?? []), 'is_string'));
        /** @var array<string, int|null> $extraLimits */
        $extraLimits = array_filter((array) ($overrides['limits'] ?? []), fn ($value) => $value === null || is_int($value));

        return new Entitlements(
            plan: $plan,
            status: $status,
            features: array_values(array_unique([...$plan->features, ...$extraFeatures])),
            limits: [...$plan->limits, ...$extraLimits],
            trialEndsAt: $onTrial ? $trialEndsAt : null,
            subscription: $subscription,
        );
    }
}
