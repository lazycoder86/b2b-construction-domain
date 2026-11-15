<?php

namespace App\Support\Billing;

use App\Models\Organization;
use App\Models\UsageRecord;
use Carbon\CarbonInterface;

/**
 * Placeholder for usage-based pricing: record usage now, report it to Stripe
 * (metered billing) later without changing call sites.
 */
class UsageMeter
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(Organization $organization, string $metric, int $quantity = 1, array $metadata = []): UsageRecord
    {
        return $organization->usageRecords()->create([
            'metric' => $metric,
            'quantity' => $quantity,
            'recorded_at' => now(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    public function total(Organization $organization, string $metric, ?CarbonInterface $since = null, ?CarbonInterface $until = null): int
    {
        return (int) $organization->usageRecords()
            ->where('metric', $metric)
            ->when($since, fn ($query) => $query->where('recorded_at', '>=', $since))
            ->when($until, fn ($query) => $query->where('recorded_at', '<', $until))
            ->sum('quantity');
    }
}
