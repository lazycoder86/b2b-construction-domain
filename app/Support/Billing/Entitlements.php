<?php

namespace App\Support\Billing;

use Carbon\CarbonImmutable;

/**
 * What an organization may do right now. Resolve via $organization->entitlements().
 *
 * Check capabilities, never plan names:
 *     $entitlements->allows('csv_export')
 *     $entitlements->limit('seats')        // null = unlimited
 *     $entitlements->withinLimit('seats', $used)
 */
final readonly class Entitlements
{
    /**
     * @param  list<string>  $features
     * @param  array<string, int|null>  $limits
     */
    public function __construct(
        public Plan $plan,
        public BillingStatus $status,
        public array $features,
        public array $limits,
        public ?CarbonImmutable $trialEndsAt = null,
        public ?SubscriptionState $subscription = null,
    ) {}

    public function allows(string $feature): bool
    {
        return in_array($feature, $this->features, true);
    }

    /**
     * Limit for a key; null means unlimited. Keys missing from the plan are unlimited.
     */
    public function limit(string $key): ?int
    {
        return $this->limits[$key] ?? null;
    }

    /**
     * Whether $used is still below the limit, i.e. one more is allowed.
     */
    public function hasRoomFor(string $key, int $used, int $adding = 1): bool
    {
        $limit = $this->limit($key);

        return $limit === null || $used + $adding <= $limit;
    }

    public function isTrialing(): bool
    {
        return $this->status === BillingStatus::Trialing;
    }

    public function trialDaysLeft(): ?int
    {
        if (! $this->isTrialing() || $this->trialEndsAt === null) {
            return null;
        }

        return (int) max(0, ceil(now()->diffInHours($this->trialEndsAt) / 24));
    }

    /**
     * @return array{plan: string, plan_name: string, status: string, trial_ends_at: string|null, trial_days_left: int|null, features: list<string>, limits: array<string, int|null>}
     */
    public function toArray(): array
    {
        return [
            'plan' => $this->plan->key,
            'plan_name' => $this->plan->name,
            'status' => $this->status->value,
            'trial_ends_at' => $this->trialEndsAt?->toIso8601String(),
            'trial_days_left' => $this->trialDaysLeft(),
            'features' => $this->features,
            'limits' => $this->limits,
        ];
    }
}
