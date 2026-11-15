<?php

namespace App\Http\Controllers;

use App\Facades\Audit;
use App\Models\Organization;
use App\Notifications\PaymentFailed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Cashier's webhook handling (subscription sync) plus:
 *  - idempotency: an event id is processed once, even if Stripe redelivers it;
 *  - owner notification when a renewal payment fails (dunning).
 *
 * Signature verification is done by Cashier when STRIPE_WEBHOOK_SECRET is set.
 * Always set it outside local development.
 */
class StripeWebhookController extends CashierWebhookController
{
    public function handleWebhook(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload) || ! isset($payload['type'])) {
            return new Response('Invalid payload', 400);
        }

        $eventId = $payload['id'] ?? null;

        // ON CONFLICT DO NOTHING: no exception (and no aborted transaction) on duplicates.
        if (is_string($eventId) && DB::table('stripe_events')->insertOrIgnore([
            'stripe_event_id' => $eventId,
            'type' => $payload['type'],
            'processed_at' => now(),
        ]) === 0) {
            return new Response('Webhook already processed', 200);
        }

        try {
            $response = parent::handleWebhook($request);
            $this->audit($payload);

            return $response;
        } catch (Throwable $exception) {
            // Let Stripe retry: forget the event so the retry is processed.
            if (is_string($eventId)) {
                DB::table('stripe_events')->where('stripe_event_id', $eventId)->delete();
            }

            throw $exception;
        }
    }

    /**
     * Billing events that matter to owners (and support) in the audit log.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function audit(array $payload): void
    {
        $action = match ($payload['type']) {
            'customer.subscription.created' => 'billing.subscription_created',
            'customer.subscription.updated' => 'billing.subscription_updated',
            'customer.subscription.deleted' => 'billing.subscription_cancelled',
            'invoice.payment_succeeded' => 'billing.payment_succeeded',
            'invoice.payment_failed' => 'billing.payment_failed',
            default => null,
        };

        $object = $payload['data']['object'] ?? [];
        $customer = is_array($object) ? ($object['customer'] ?? null) : null;

        if ($action === null || ! is_string($customer)) {
            return;
        }

        $organization = Organization::query()->where('stripe_id', $customer)->first();

        if ($organization === null) {
            return;
        }

        $metadata = array_filter([
            'stripe_event' => $payload['id'] ?? null,
            'status' => $object['status'] ?? null,
            'price' => $object['items']['data'][0]['price']['id'] ?? null,
            'cancel_at_period_end' => $object['cancel_at_period_end'] ?? null,
            'amount' => isset($object['amount_due'], $object['currency'])
                ? Cashier::formatAmount((int) $object['amount_due'], (string) $object['currency'])
                : null,
        ], fn ($value) => $value !== null);

        // System action: no user actor.
        Audit::record($action, $organization, $organization, $metadata, subjectLabel: $organization->name);
    }

    /**
     * A renewal (or other) invoice payment failed. Stripe retries per the
     * dashboard's Smart Retries settings; we tell the owners to fix their card.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        $invoice = $payload['data']['object'];
        $organization = is_string($invoice['customer'] ?? null)
            ? Organization::query()->where('stripe_id', $invoice['customer'])->first()
            : null;

        if ($organization !== null) {
            $owners = $organization->owners()->with('user')->get()->pluck('user');

            Notification::send($owners, new PaymentFailed(
                organizationName: $organization->name,
                billingUrl: route('tenant.billing.show', ['organization' => $organization->slug]),
                amountDue: isset($invoice['amount_due'], $invoice['currency'])
                    ? Cashier::formatAmount((int) $invoice['amount_due'], (string) $invoice['currency'])
                    : null,
            ));
        }

        return $this->successMethod();
    }
}
