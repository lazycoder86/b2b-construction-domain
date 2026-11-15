<?php

use App\Actions\Organizations\CreateOrganization;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\BillingStatus;
use Illuminate\Support\Facades\Log;

test('organizations without a trial or subscription are on the free plan', function () {
    $entitlements = Organization::factory()->create()->entitlements();

    expect($entitlements->plan->key)->toBe('free')
        ->and($entitlements->status)->toBe(BillingStatus::Free)
        ->and($entitlements->allows('csv_export'))->toBeFalse()
        ->and($entitlements->limit('seats'))->toBe(3);
});

test('new organizations start a card-less trial of the trial plan', function () {
    $organization = app(CreateOrganization::class)->handle(User::factory()->create(), 'Trial Co');
    $entitlements = $organization->entitlements();

    expect($entitlements->status)->toBe(BillingStatus::Trialing)
        ->and($entitlements->plan->key)->toBe('pro')
        ->and($entitlements->allows('csv_export'))->toBeTrue()
        ->and($entitlements->trialDaysLeft())->toBe(14)
        ->and($organization->onGenericTrial())->toBeTrue();
});

test('trials can be disabled', function () {
    config(['billing.trial.days' => 0]);

    $organization = app(CreateOrganization::class)->handle(User::factory()->create(), 'No Trial Co');

    expect($organization->trial_ends_at)->toBeNull()
        ->and($organization->entitlements()->status)->toBe(BillingStatus::Free);
});

test('an expired trial falls back to the free plan', function () {
    $organization = Organization::factory()->create(['trial_ends_at' => now()->subMinute()]);

    expect($organization->entitlements()->plan->key)->toBe('free');
});

test('an active subscription grants its plan', function () {
    $organization = Organization::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    subscribeTo($organization, 'price_business_yearly');

    $entitlements = $organization->entitlements();

    expect($entitlements->status)->toBe(BillingStatus::Active)
        ->and($entitlements->plan->key)->toBe('business')
        ->and($entitlements->subscription?->interval)->toBe('yearly')
        ->and($entitlements->limit('seats'))->toBeNull()
        ->and($entitlements->allows('api_access'))->toBeTrue();
});

test('past due subscriptions keep access during dunning', function () {
    $organization = Organization::factory()->create();
    subscribeTo($organization, 'price_pro_monthly', 'past_due');

    expect($organization->entitlements())
        ->status->toBe(BillingStatus::PastDue)
        ->plan->key->toBe('pro');
});

test('cancelled subscriptions keep access until the period ends', function () {
    $organization = Organization::factory()->create();
    subscribeTo($organization, 'price_pro_monthly', 'active', now()->addDays(10));

    expect($organization->entitlements()->status)->toBe(BillingStatus::Cancelling);

    $ended = Organization::factory()->create();
    subscribeTo($ended, 'price_pro_monthly', 'canceled', now()->subDay());

    expect($ended->entitlements()->status)->toBe(BillingStatus::Free);
});

test('unpaid and incomplete subscriptions grant nothing', function (string $status) {
    $organization = Organization::factory()->create();
    subscribeTo($organization, 'price_pro_monthly', $status);

    expect($organization->entitlements()->plan->key)->toBe('free');
})->with(['unpaid', 'incomplete', 'incomplete_expired']);

test('an unknown Stripe price is logged and ignored', function () {
    Log::spy();
    $organization = Organization::factory()->create();
    subscribeTo($organization, 'price_not_in_config');

    expect($organization->entitlements()->plan->key)->toBe('free');
    Log::shouldHaveReceived('warning')->once();
});

test('overrides can set a custom plan and extra features and limits', function () {
    $organization = Organization::factory()->create([
        'entitlement_overrides' => [
            'plan' => 'pro',
            'features' => ['webhooks'],
            'limits' => ['seats' => 50, 'bogus' => 'nope'],
        ],
    ]);
    subscribeTo($organization, 'price_business_monthly');

    $entitlements = $organization->entitlements();

    expect($entitlements->status)->toBe(BillingStatus::Custom)
        ->and($entitlements->plan->key)->toBe('pro')
        ->and($entitlements->allows('webhooks'))->toBeTrue()
        ->and($entitlements->limit('seats'))->toBe(50)
        ->and($entitlements->limits)->not->toHaveKey('bogus');
});

test('seat limits come from the plan', function () {
    $organization = Organization::factory()->withOwner()->create();
    memberOf($organization);
    memberOf($organization);

    expect($organization->seatLimit())->toBe(3)
        ->and($organization->hasAvailableSeat())->toBeFalse();

    subscribeTo($organization, 'price_pro_monthly');

    expect($organization->seatLimit())->toBe(15)
        ->and($organization->hasAvailableSeat())->toBeTrue();
});

test('the current organization shares its billing state with the frontend', function () {
    $user = User::factory()->create();
    $organization = app(CreateOrganization::class)->handle($user, 'Shared Co');

    $this->actingAs($user)
        ->get(route('tenant.dashboard', $organization))
        ->assertInertia(fn ($page) => $page
            ->where('currentOrganization.billing.plan', 'pro')
            ->where('currentOrganization.billing.status', 'trialing')
            ->where('currentOrganization.billing.trial_days_left', 14));
});
