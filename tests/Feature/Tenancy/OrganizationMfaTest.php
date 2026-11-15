<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

test('members without MFA are sent to security settings when the organization requires it', function () {
    $organization = Organization::factory()->create(['require_two_factor' => true]);
    $member = memberOf($organization);

    $this->actingAs($member)
        ->get(route('tenant.dashboard', $organization))
        ->assertRedirect(route('security.edit'));
});

test('members with two-factor authentication pass', function () {
    $organization = Organization::factory()->create(['require_two_factor' => true]);
    $member = User::factory()->withTwoFactor()->create();
    $organization->addMember($member, OrganizationRole::Member);

    $this->actingAs($member)
        ->get(route('tenant.dashboard', $organization))
        ->assertOk();
});

test('organizations without the requirement are unaffected', function () {
    $organization = Organization::factory()->create();

    $this->actingAs(memberOf($organization))
        ->get(route('tenant.dashboard', $organization))
        ->assertOk();
});

test('only owners can change the requirement', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->withTwoFactor()->create();
    $organization->addMember($admin, OrganizationRole::Admin);

    $this->actingAs($admin)
        ->patch(route('tenant.settings.security.update', $organization), ['require_two_factor' => true])
        ->assertForbidden();
});

test('owners must have MFA themselves before requiring it', function () {
    $organization = Organization::factory()->create();
    $owner = memberOf($organization, OrganizationRole::Owner);

    $this->actingAs($owner)
        ->patch(route('tenant.settings.security.update', $organization), ['require_two_factor' => true])
        ->assertSessionHasErrors('require_two_factor');

    $owner->forceFill(User::factory()->withTwoFactor()->raw())->save();

    $this->actingAs($owner->fresh())
        ->patch(route('tenant.settings.security.update', $organization), ['require_two_factor' => true])
        ->assertSessionHasNoErrors();

    expect($organization->fresh()->require_two_factor)->toBeTrue();
});
