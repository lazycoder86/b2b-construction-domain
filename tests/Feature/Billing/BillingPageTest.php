<?php

use App\Contracts\Billing\BillingGateway;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use Tests\Fixtures\FakeBillingGateway;

beforeEach(function () {
    $this->organization = Organization::factory()->create(['trial_ends_at' => now()->addDays(3)]);
    $this->owner = memberOf($this->organization, OrganizationRole::Owner);
    app()->instance(BillingGateway::class, $this->billing = new FakeBillingGateway);
});

test('owners and admins can view billing; others cannot', function (OrganizationRole $role, int $status) {
    $this->actingAs(memberOf($this->organization, $role))
        ->get(route('tenant.billing.show', $this->organization))
        ->assertStatus($status);
})->with([
    'admin' => [OrganizationRole::Admin, 200],
    'member' => [OrganizationRole::Member, 403],
    'viewer' => [OrganizationRole::Viewer, 403],
]);

test('the billing page shows the plan, catalogue and usage', function () {
    $this->actingAs($this->owner)
        ->get(route('tenant.billing.show', $this->organization))
        ->assertInertia(fn ($page) => $page
            ->component('tenant/billing/show')
            ->where('billing.status', 'trialing')
            ->where('billing.plan', 'pro')
            ->where('billing.trial_days_left', 3)
            ->where('seats.used', 1)
            ->where('seats.limit', 15)
            ->where('canManage', true)
            ->where('stripeEnabled', true)
            ->where('hasSubscription', false)
            ->has('plans', 3)
            ->where('plans.1.key', 'pro')
            ->where('plans.1.prices.0.formatted', '$29.00')
            ->where('plans.1.prices.0.available', true)
            ->missing('invoices'));
});

test('admins can see billing but not manage it', function () {
    $admin = memberOf($this->organization, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->get(route('tenant.billing.show', $this->organization))
        ->assertInertia(fn ($page) => $page->where('canManage', false));

    $this->actingAs($admin)
        ->post(route('tenant.billing.subscribe', $this->organization), ['plan' => 'pro', 'interval' => 'monthly'])
        ->assertForbidden();

    expect($this->billing->calls)->toBe([]);
});

test('subscribing sends the owner to Stripe Checkout', function () {
    $this->actingAs($this->owner)
        ->post(route('tenant.billing.subscribe', $this->organization), ['plan' => 'business', 'interval' => 'yearly'], ['X-Inertia' => 'true'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.stripe.test/session_123');

    [[$organizationId, $plan, $interval, $successUrl]] = $this->billing->callsTo('checkout');

    expect($organizationId)->toBe($this->organization->id)
        ->and($plan)->toBe('business')
        ->and($interval)->toBe('yearly')
        ->and($successUrl)->toEndWith("/o/{$this->organization->slug}/billing?checkout=success");
});

test('subscribed organizations change plan instead of checking out again', function () {
    subscribeTo($this->organization, 'price_pro_monthly');

    $this->actingAs($this->owner)
        ->post(route('tenant.billing.subscribe', $this->organization), ['plan' => 'business', 'interval' => 'monthly'])
        ->assertRedirect(route('tenant.billing.show', $this->organization));

    expect($this->billing->callsTo('swap'))->toBe([[$this->organization->id, 'business', 'monthly']])
        ->and($this->billing->callsTo('checkout'))->toBe([]);
});

test('the plan and interval are validated', function () {
    $this->actingAs($this->owner);

    $this->post(route('tenant.billing.subscribe', $this->organization), ['plan' => 'nope', 'interval' => 'monthly'])
        ->assertSessionHasErrors('plan');
    $this->post(route('tenant.billing.subscribe', $this->organization), ['plan' => 'pro', 'interval' => 'weekly'])
        ->assertSessionHasErrors('interval');
    // The free plan has no price to buy.
    $this->post(route('tenant.billing.subscribe', $this->organization), ['plan' => 'free', 'interval' => 'monthly'])
        ->assertSessionHasErrors('plan');
});

test('subscribing is unavailable until Stripe is configured', function () {
    app()->instance(BillingGateway::class, new FakeBillingGateway(enabled: false));

    $this->actingAs($this->owner)
        ->post(route('tenant.billing.subscribe', $this->organization), ['plan' => 'pro', 'interval' => 'monthly'])
        ->assertStatus(503);
});

test('the real gateway is disabled without Stripe keys', function () {
    app()->forgetInstance(BillingGateway::class);

    expect(app(BillingGateway::class)->enabled())->toBeFalse();
});

test('the customer portal opens for organizations that are Stripe customers', function () {
    $this->actingAs($this->owner)
        ->post(route('tenant.billing.portal', $this->organization))
        ->assertNotFound();

    $this->organization->forceFill(['stripe_id' => 'cus_123'])->save();

    $this->actingAs($this->owner)
        ->post(route('tenant.billing.portal', $this->organization), [], ['X-Inertia' => 'true'])
        ->assertHeader('X-Inertia-Location', 'https://billing.stripe.test/portal_123');
});

test('invoices are loaded as a deferred prop', function () {
    $this->billing->invoices = [['id' => 'in_1', 'date' => now()->toIso8601String(), 'total' => '$29.00', 'status' => 'paid', 'url' => 'https://invoice.test/in_1']];

    $this->actingAs($this->owner)
        ->get(route('tenant.billing.show', $this->organization))
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($reload) => $reload->where('invoices.0.id', 'in_1')));
});
