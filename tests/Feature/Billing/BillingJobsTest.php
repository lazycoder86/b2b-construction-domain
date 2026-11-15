<?php

use App\Actions\Users\DeleteUser;
use App\Contracts\Billing\BillingGateway;
use App\Enums\OrganizationRole;
use App\Jobs\SyncSubscriptionSeats;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\TrialEndingSoon;
use App\Support\Billing\UsageMeter;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\FakeBillingGateway;

test('trial reminders go to owners once, a few days before the trial ends', function () {
    Notification::fake();
    $ending = Organization::factory()->create(['trial_ends_at' => now()->addDays(2)]);
    $owner = memberOf($ending, OrganizationRole::Owner);
    $member = memberOf($ending);
    $later = Organization::factory()->create(['trial_ends_at' => now()->addDays(10)]);
    memberOf($later, OrganizationRole::Owner);
    $subscribed = Organization::factory()->create(['trial_ends_at' => now()->addDay()]);
    $subscribedOwner = memberOf($subscribed, OrganizationRole::Owner);
    subscribeTo($subscribed);

    $this->artisan('billing:send-trial-reminders')->assertSuccessful();
    $this->artisan('billing:send-trial-reminders')->assertSuccessful();

    Notification::assertSentToTimes($owner, TrialEndingSoon::class, 1);
    Notification::assertNotSentTo($member, TrialEndingSoon::class);
    Notification::assertNotSentTo($subscribedOwner, TrialEndingSoon::class);
    Notification::assertCount(1);
});

test('per-seat subscriptions follow the member count', function () {
    config(['billing.plans.pro.per_seat' => true]);
    app()->instance(BillingGateway::class, $billing = new FakeBillingGateway);
    $organization = Organization::factory()->withOwner()->create();
    subscribeTo($organization, 'price_pro_monthly');

    memberOf($organization);

    expect($billing->callsTo('syncSeats'))->toContain([$organization->id, 2]);
});

test('flat-priced subscriptions are not synced', function () {
    app()->instance(BillingGateway::class, $billing = new FakeBillingGateway);
    $organization = Organization::factory()->withOwner()->create();
    subscribeTo($organization, 'price_pro_monthly');

    memberOf($organization);

    expect($billing->callsTo('syncSeats'))->toBe([]);
});

test('an account cannot be deleted while its solo organization is still subscribed', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->withOwner($user)->create(['name' => 'Paid Co']);
    subscribeTo($organization);

    expect(fn () => app(DeleteUser::class)->handle($user))
        ->toThrow(ValidationException::class, 'Cancel the subscription for Paid Co before deleting your account.');

    expect($user->fresh())->not->toBeNull();
});

test('usage can be recorded and totalled per organization', function () {
    $meter = app(UsageMeter::class);
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();

    $meter->record($organization, 'api_calls', 5);
    $meter->record($organization, 'api_calls', 2);
    $meter->record($other, 'api_calls', 100);
    $meter->record($organization, 'exports');

    expect($meter->total($organization, 'api_calls'))->toBe(7)
        ->and($meter->total($organization, 'api_calls', since: now()->addMinute()))->toBe(0)
        ->and($meter->total($organization, 'exports'))->toBe(1);
});

test('membership changes in organizations that never paid queue nothing', function () {
    Queue::fake();
    $organization = Organization::factory()->withOwner()->create();

    memberOf($organization);

    Queue::assertNotPushed(SyncSubscriptionSeats::class);
});
