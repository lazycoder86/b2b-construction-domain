<?php

namespace App\Contracts\Billing;

use App\Models\Organization;
use App\Support\Billing\Plan;

/**
 * Every call that talks to Stripe's API goes through here, so controllers stay
 * thin and tests can swap in a fake (tests/Fixtures/FakeBillingGateway.php).
 */
interface BillingGateway
{
    /**
     * Whether Stripe credentials are configured.
     */
    public function enabled(): bool;

    /**
     * URL of a Stripe Checkout session that starts a subscription.
     */
    public function checkoutUrl(Organization $organization, Plan $plan, string $interval, string $successUrl, string $cancelUrl): string;

    /**
     * Change the plan/interval of an existing subscription (prorated).
     */
    public function swap(Organization $organization, Plan $plan, string $interval): void;

    /**
     * URL of the Stripe Customer Portal (payment methods, invoices, cancellation).
     */
    public function portalUrl(Organization $organization, string $returnUrl): string;

    /**
     * Keep a per-seat subscription's quantity equal to the member count.
     */
    public function syncSeats(Organization $organization, int $seats): void;

    /**
     * Cancel the subscription immediately (organization deletion).
     */
    public function cancelNow(Organization $organization): void;

    /**
     * Delete the Stripe customer (final purge of an organization).
     */
    public function deleteCustomer(Organization $organization): void;

    /**
     * @return list<array{id: string, date: string, total: string, status: string, url: string|null}>
     */
    public function invoices(Organization $organization): array;
}
