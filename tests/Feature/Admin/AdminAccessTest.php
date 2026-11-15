<?php

use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\BillingStatus;
use Laravel\Pennant\Feature;

test('the admin area does not exist for regular users', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertNotFound();
});

test('platform admins need MFA', function () {
    $this->actingAs(User::factory()->create(['is_platform_admin' => true]))
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('security.edit'));
});

test('platform admins must have confirmed their password recently', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create(['is_platform_admin' => true]))
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('password.confirm'));
});

test('platform admins see the dashboard', function () {
    platformAdmin();
    Organization::factory()->count(2)->create();

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->where('stats.organizations', 2));
});

test('admin access is granted and revoked from the console, and audited', function () {
    $user = User::factory()->create(['email' => 'staff@example.com']);

    $this->artisan('admin:grant', ['email' => 'STAFF@example.com'])->assertSuccessful();
    expect($user->fresh()->is_platform_admin)->toBeTrue();

    $this->artisan('admin:grant', ['email' => 'staff@example.com', '--revoke' => true])->assertSuccessful();
    expect($user->fresh()->is_platform_admin)->toBeFalse()
        ->and(AuditLog::where('action', 'like', 'admin.access_%')->count())->toBe(2);

    $this->artisan('admin:grant', ['email' => 'nobody@example.com'])->assertFailed();
});

test('is_platform_admin cannot be mass assigned', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'X', 'email' => $user->email, 'is_platform_admin' => true]);

    expect($user->fresh()->is_platform_admin)->toBeFalse();
});

test('organizations can be searched by member email', function () {
    platformAdmin();
    $target = Organization::factory()->create(['name' => 'Needle Inc']);
    memberOf($target, OrganizationRole::Owner, ['email' => 'finder@needle.test']);
    Organization::factory()->create(['name' => 'Haystack']);

    $this->get(route('admin.organizations.index', ['q' => 'needle.test']))
        ->assertInertia(fn ($page) => $page
            ->has('organizations.data', 1)
            ->where('organizations.data.0.name', 'Needle Inc'));
});

test('admins can suspend and reactivate organizations, with a reason', function () {
    platformAdmin();
    $organization = Organization::factory()->create();
    $member = memberOf($organization);

    $this->patch(route('admin.organizations.status', $organization->public_id), ['status' => 'suspended'])
        ->assertSessionHasErrors('reason');
    $this->patch(route('admin.organizations.status', $organization->public_id), ['status' => 'suspended', 'reason' => 'Chargeback'])
        ->assertSessionHasNoErrors();

    expect($organization->fresh()->status)->toBe(OrganizationStatus::Suspended)
        ->and(AuditLog::where('action', 'admin.organization_suspended')->sole()->metadata)->toBe(['from' => 'active', 'reason' => 'Chargeback']);

    $this->actingAs($member)->get(route('tenant.dashboard', $organization))->assertForbidden();
});

test('admins can extend trials and override entitlements', function () {
    platformAdmin();
    $organization = Organization::factory()->create();

    $this->post(route('admin.organizations.trial', $organization->public_id), ['days' => 10])->assertSessionHasNoErrors();
    expect($organization->fresh()->entitlements()->trialDaysLeft())->toBe(10);

    $this->put(route('admin.organizations.entitlements', $organization->public_id), [
        'plan' => 'business',
        'features' => ['beta_export'],
        'seats' => 500,
        'reason' => 'Enterprise pilot',
    ])->assertSessionHasNoErrors();

    $entitlements = $organization->fresh()->entitlements();

    expect($entitlements->status)->toBe(BillingStatus::Custom)
        ->and($entitlements->plan->key)->toBe('business')
        ->and($entitlements->allows('beta_export'))->toBeTrue()
        ->and($entitlements->limit('seats'))->toBe(500);

    // Clearing everything removes the override.
    $this->put(route('admin.organizations.entitlements', $organization->public_id), ['plan' => null, 'features' => [], 'reason' => 'Pilot over'])->assertSessionHasNoErrors();
    expect($organization->fresh()->entitlement_overrides)->toBeNull();
});

test('admins can toggle feature flags per organization', function () {
    platformAdmin();
    $organization = Organization::factory()->create();
    $member = memberOf($organization);

    expect(Feature::for($organization)->active('beta_dashboard'))->toBeFalse();

    $this->put(route('admin.organizations.flags', [$organization->public_id, 'beta_dashboard']), ['state' => 'on'])->assertSessionHasNoErrors();
    expect(Feature::for($organization)->active('beta_dashboard'))->toBeTrue();

    $this->actingAs($member)->get(route('tenant.dashboard', $organization))
        ->assertInertia(fn ($page) => $page->where('currentOrganization.flags', ['beta_dashboard']));

    platformAdmin();
    $this->put(route('admin.organizations.flags', [$organization->public_id, 'unknown_flag']), ['state' => 'on'])->assertNotFound();
});

test('the organization detail page shows billing, members and history', function () {
    platformAdmin();
    $organization = Organization::factory()->create();
    memberOf($organization, OrganizationRole::Owner);
    subscribeTo($organization, 'price_pro_yearly');

    $this->get(route('admin.organizations.show', $organization->public_id))
        ->assertInertia(fn ($page) => $page
            ->component('admin/organizations/show')
            ->where('entitlements.plan', 'pro')
            ->where('subscription.price', 'price_pro_yearly')
            ->has('members', 1)
            ->has('flags', 1));
});
