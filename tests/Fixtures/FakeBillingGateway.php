<?php

namespace Tests\Fixtures;

use App\Contracts\Billing\BillingGateway;
use App\Models\Organization;
use App\Support\Billing\Plan;

/**
 * Records calls instead of talking to Stripe. Bind it with
 * `app()->instance(BillingGateway::class, $fake = new FakeBillingGateway)`.
 */
class FakeBillingGateway implements BillingGateway
{
    /** @var list<array{0: string, 1: mixed}> */
    public array $calls = [];

    /** @var list<array{id: string, date: string, total: string, status: string, url: string|null}> */
    public array $invoices = [];

    public function __construct(public bool $enabled = true) {}

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function checkoutUrl(Organization $organization, Plan $plan, string $interval, string $successUrl, string $cancelUrl): string
    {
        $this->calls[] = ['checkout', [$organization->id, $plan->key, $interval, $successUrl, $cancelUrl]];

        return 'https://checkout.stripe.test/session_123';
    }

    public function swap(Organization $organization, Plan $plan, string $interval): void
    {
        $this->calls[] = ['swap', [$organization->id, $plan->key, $interval]];
    }

    public function portalUrl(Organization $organization, string $returnUrl): string
    {
        $this->calls[] = ['portal', [$organization->id, $returnUrl]];

        return 'https://billing.stripe.test/portal_123';
    }

    public function syncSeats(Organization $organization, int $seats): void
    {
        $this->calls[] = ['syncSeats', [$organization->id, $seats]];
    }

    public function cancelNow(Organization $organization): void
    {
        $this->calls[] = ['cancelNow', [$organization->id]];
    }

    public function deleteCustomer(Organization $organization): void
    {
        $this->calls[] = ['deleteCustomer', [$organization->id]];
    }

    public function invoices(Organization $organization): array
    {
        return $this->invoices;
    }

    /**
     * @return list<mixed>
     */
    public function callsTo(string $method): array
    {
        return array_values(array_map(
            fn (array $call) => $call[1],
            array_filter($this->calls, fn (array $call) => $call[0] === $method),
        ));
    }
}
