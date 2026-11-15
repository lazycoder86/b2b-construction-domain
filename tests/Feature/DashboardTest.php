<?php

use App\Models\Organization;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('users without an organization are sent to onboarding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('organizations.create'));
});

test('users are sent to their current organization dashboard', function () {
    $user = User::factory()->withOrganization()->create();
    $organization = $user->fresh()->currentOrganization;

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('tenant.dashboard', $organization));
});

test('a stale current organization falls back to another membership', function () {
    $user = User::factory()->create();
    $kept = Organization::factory()->withMember($user)->create(['name' => 'Kept']);
    $left = Organization::factory()->create();
    $user->forceFill(['current_organization_id' => $left->id])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('tenant.dashboard', $kept));
});

test('members can visit the organization dashboard', function () {
    $user = User::factory()->withOrganization()->create();
    $organization = $user->fresh()->currentOrganization;

    $this->actingAs($user)
        ->get(route('tenant.dashboard', $organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/dashboard')
            ->where('currentOrganization.slug', $organization->slug)
            ->where('currentOrganization.role', 'owner')
            ->has('organizations', 1));
});
