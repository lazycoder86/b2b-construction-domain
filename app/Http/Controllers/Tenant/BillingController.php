<?php

namespace App\Http\Controllers\Tenant;

use App\Contracts\Billing\BillingGateway;
use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Billing\Plan;
use App\Support\Billing\PlanCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Cashier;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class BillingController extends Controller
{
    public function __construct(
        protected PlanCatalog $plans,
        protected BillingGateway $billing,
    ) {}

    public function show(Request $request, Organization $organization): Response
    {
        Gate::authorize('viewBilling', $organization);

        $entitlements = $organization->entitlements();

        return Inertia::render('tenant/billing/show', [
            'billing' => [
                ...$entitlements->toArray(),
                'interval' => $entitlements->subscription?->interval,
                'ends_at' => $entitlements->subscription?->endsAt?->toIso8601String(),
                'payment_method' => $organization->pm_type !== null
                    ? ['brand' => $organization->pm_type, 'last_four' => $organization->pm_last_four]
                    : null,
            ],
            'plans' => array_map(fn (Plan $plan) => [
                'key' => $plan->key,
                'name' => $plan->name,
                'description' => $plan->description,
                'per_seat' => $plan->perSeat,
                'features' => $plan->features,
                'limits' => $plan->limits,
                'prices' => collect($plan->prices)->map(fn (array $price, string $interval) => [
                    'interval' => $interval,
                    'amount' => $price['amount'],
                    'formatted' => Cashier::formatAmount($price['amount']),
                    'available' => filled($price['stripe_price'] ?? null),
                ])->values(),
            ], $this->plans->all()),
            'seats' => [
                'used' => $organization->seatsUsed(),
                'limit' => $entitlements->limit('seats'),
            ],
            'invoices' => Inertia::defer(fn () => $this->invoices($organization)),
            'stripeEnabled' => $this->billing->enabled(),
            'hasSubscription' => $entitlements->subscription !== null,
            'isCustomer' => $organization->hasStripeId(),
            'canManage' => $request->user()->can('manageBilling', $organization),
            'checkout' => $request->query('checkout'),
        ]);
    }

    /**
     * Start a subscription (Stripe Checkout) or change the current one.
     */
    public function subscribe(Request $request, Organization $organization): SymfonyResponse
    {
        Gate::authorize('manageBilling', $organization);
        abort_unless($this->billing->enabled(), 503, __('Billing is not configured.'));

        $validated = $request->validate([
            'plan' => ['required', 'string', Rule::in(array_map(fn (Plan $plan) => $plan->key, $this->plans->all()))],
            'interval' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
        ]);

        $plan = $this->plans->get($validated['plan']);

        if ($plan->stripePrice($validated['interval']) === null) {
            return back()->withErrors(['plan' => __('This plan is not available for purchase.')]);
        }

        if ($organization->entitlements()->subscription !== null) {
            $previous = $organization->entitlements()->plan->key;
            $this->billing->swap($organization, $plan, $validated['interval']);
            Audit::record('billing.plan_changed', $organization, $organization, ['from' => $previous, 'to' => $plan->key, 'interval' => $validated['interval']]);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Your plan was changed to :plan.', ['plan' => $plan->name])]);

            return to_route('tenant.billing.show', ['organization' => $organization->slug]);
        }

        $billingUrl = route('tenant.billing.show', ['organization' => $organization->slug]);
        Audit::record('billing.checkout_started', $organization, $organization, ['plan' => $plan->key, 'interval' => $validated['interval']]);

        return Inertia::location($this->billing->checkoutUrl(
            $organization,
            $plan,
            $validated['interval'],
            successUrl: $billingUrl.'?checkout=success',
            cancelUrl: $billingUrl.'?checkout=cancelled',
        ));
    }

    /**
     * Stripe Customer Portal: payment methods, invoices, cancellation.
     */
    public function portal(Organization $organization): SymfonyResponse
    {
        Gate::authorize('manageBilling', $organization);
        abort_unless($this->billing->enabled() && $organization->hasStripeId(), 404);
        Audit::record('billing.portal_opened', $organization, $organization);

        return Inertia::location($this->billing->portalUrl(
            $organization,
            route('tenant.billing.show', ['organization' => $organization->slug]),
        ));
    }

    /**
     * @return list<array{id: string, date: string, total: string, status: string, url: string|null}>
     */
    protected function invoices(Organization $organization): array
    {
        if (! $this->billing->enabled()) {
            return [];
        }

        try {
            return $this->billing->invoices($organization);
        } catch (Throwable $exception) {
            // Stripe being unreachable must not break the billing page.
            Log::warning('Could not load invoices from Stripe.', ['organization_id' => $organization->id, 'exception' => $exception->getMessage()]);

            return [];
        }
    }
}
