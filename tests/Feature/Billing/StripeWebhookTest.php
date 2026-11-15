<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Notifications\PaymentFailed;
use App\Support\Billing\BillingStatus;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->organization = Organization::factory()->create(['stripe_id' => 'cus_test', 'trial_ends_at' => now()->addDays(5)]);
    $this->owner = memberOf($this->organization, OrganizationRole::Owner);
});

function stripeEvent(string $type, array $object, string $id = 'evt_1'): array
{
    return ['id' => $id, 'type' => $type, 'data' => ['object' => $object]];
}

function subscriptionObject(string $status = 'active', string $price = 'price_pro_monthly'): array
{
    return [
        'id' => 'sub_test',
        'customer' => 'cus_test',
        'status' => $status,
        'metadata' => ['type' => 'default'],
        'cancel_at_period_end' => false,
        'items' => ['data' => [[
            'id' => 'si_test',
            'quantity' => 1,
            'price' => ['id' => $price, 'product' => 'prod_test'],
        ]]],
    ];
}

test('a created subscription is mirrored and grants the plan', function () {
    $this->postJson(route('cashier.webhook'), stripeEvent('customer.subscription.created', subscriptionObject()))
        ->assertOk();

    $organization = $this->organization->fresh();
    $entitlements = $organization->entitlements();

    expect($organization->subscriptions()->count())->toBe(1)
        ->and($entitlements->status)->toBe(BillingStatus::Active)
        ->and($entitlements->plan->key)->toBe('pro')
        // Cashier ends the card-less trial once a real subscription exists.
        ->and($organization->trial_ends_at)->toBeNull();
});

test('the same event is processed only once', function () {
    $event = stripeEvent('customer.subscription.created', subscriptionObject(), 'evt_dupe');

    $this->postJson(route('cashier.webhook'), $event)->assertOk();
    $this->postJson(route('cashier.webhook'), $event)->assertOk()->assertSee('Webhook already processed');

    $this->assertDatabaseCount('stripe_events', 1);
});

test('a deleted subscription removes paid access', function () {
    $this->postJson(route('cashier.webhook'), stripeEvent('customer.subscription.created', subscriptionObject(), 'evt_a'));
    $this->postJson(route('cashier.webhook'), stripeEvent('customer.subscription.deleted', subscriptionObject('canceled'), 'evt_b'))
        ->assertOk();

    expect($this->organization->fresh()->entitlements()->plan->key)->toBe('free');
});

test('a failed payment emails the owners', function () {
    Notification::fake();
    $admin = memberOf($this->organization, OrganizationRole::Admin);

    $this->postJson(route('cashier.webhook'), stripeEvent('invoice.payment_failed', [
        'id' => 'in_test',
        'customer' => 'cus_test',
        'amount_due' => 2900,
        'currency' => 'usd',
    ]))->assertOk();

    Notification::assertSentTo($this->owner, PaymentFailed::class, fn (PaymentFailed $notification) => $notification->amountDue === '$29.00'
        && str_ends_with($notification->billingUrl, "/o/{$this->organization->slug}/billing"));
    Notification::assertNotSentTo($admin, PaymentFailed::class);
});

test('malformed payloads are rejected', function () {
    $this->postJson(route('cashier.webhook'), ['nope' => true])->assertStatus(400);
});

test('the webhook needs no session or CSRF token', function () {
    // A raw JSON body without any session cookie or X-CSRF-TOKEN (as Stripe sends it).
    $this->call('POST', route('cashier.webhook'), server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(stripeEvent('ping.unknown', [])))
        ->assertOk();
});
