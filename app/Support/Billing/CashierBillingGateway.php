<?php

namespace App\Support\Billing;

use App\Contracts\Billing\BillingGateway;
use App\Models\Organization;
use InvalidArgumentException;

class CashierBillingGateway implements BillingGateway
{
    public function enabled(): bool
    {
        return filled(config('cashier.secret')) && filled(config('cashier.key'));
    }

    public function checkoutUrl(Organization $organization, Plan $plan, string $interval, string $successUrl, string $cancelUrl): string
    {
        $builder = $organization->newSubscription(config('billing.subscription_type'), $this->price($plan, $interval));

        if ($plan->perSeat) {
            $builder->quantity(max(1, $organization->memberships()->count()));
        }

        return $builder
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => $organization->public_id,
            ])
            ->asStripeCheckoutSession()
            ->url ?? throw new InvalidArgumentException('Stripe did not return a Checkout URL.');
    }

    public function swap(Organization $organization, Plan $plan, string $interval): void
    {
        $subscription = $organization->subscription(config('billing.subscription_type'))
            ?? throw new InvalidArgumentException('The organization has no subscription to change.');

        $subscription->swap($this->price($plan, $interval));

        // Seat counts only apply to per-seat plans; flat plans use quantity 1.
        $subscription->updateQuantity($plan->perSeat ? max(1, $organization->memberships()->count()) : 1);
    }

    public function portalUrl(Organization $organization, string $returnUrl): string
    {
        return $organization->billingPortalUrl($returnUrl);
    }

    public function syncSeats(Organization $organization, int $seats): void
    {
        $subscription = $organization->subscription(config('billing.subscription_type'));

        if ($subscription !== null && $subscription->valid() && $subscription->quantity !== $seats) {
            $subscription->updateQuantity(max(1, $seats));
        }
    }

    public function cancelNow(Organization $organization): void
    {
        $subscription = $organization->subscription(config('billing.subscription_type'));

        if ($subscription !== null && ! $subscription->ended()) {
            $subscription->cancelNow();
        }
    }

    public function deleteCustomer(Organization $organization): void
    {
        if ($organization->hasStripeId()) {
            $organization->stripe()->customers->delete((string) $organization->stripe_id);
        }
    }

    public function invoices(Organization $organization): array
    {
        if (! $organization->hasStripeId()) {
            return [];
        }

        $invoices = [];

        foreach ($organization->invoicesIncludingPending() as $invoice) {
            $stripeInvoice = $invoice->asStripeInvoice();

            $invoices[] = [
                'id' => (string) $stripeInvoice->id,
                'date' => $invoice->date()->toIso8601String(),
                'total' => $invoice->total(),
                'status' => (string) $stripeInvoice->status,
                'url' => $stripeInvoice->hosted_invoice_url,
            ];
        }

        return $invoices;
    }

    protected function price(Plan $plan, string $interval): string
    {
        return $plan->stripePrice($interval)
            ?? throw new InvalidArgumentException("Plan [{$plan->key}] has no Stripe price for [{$interval}]. Set it in .env (see config/billing.php).");
    }
}
