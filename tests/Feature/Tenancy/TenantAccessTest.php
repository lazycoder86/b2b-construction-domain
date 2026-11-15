<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

test('non-members get a 404 for an organization', function () {
    $user = User::factory()->withOrganization()->create();
    $other = Organization::factory()->withOwner()->create();

    $this->actingAs($user)
        ->get(route('tenant.dashboard', $other))
        ->assertNotFound();
});

test('unknown organizations get the same 404', function () {
    $user = User::factory()->withOrganization()->create();

    $this->actingAs($user)
        ->get('/o/does-not-exist/dashboard')
        ->assertNotFound();
});

test('suspended organizations are not accessible', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->suspended()->withOwner($user)->create();

    $this->actingAs($user)
        ->get(route('tenant.dashboard', $organization))
        ->assertForbidden();
});

test('soft-deleted organizations are not accessible', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->withOwner($user)->create();
    $organization->delete();

    $this->actingAs($user)
        ->get("/o/{$organization->slug}/dashboard")
        ->assertNotFound();
});

test('unverified users cannot reach tenant routes', function () {
    $user = User::factory()->unverified()->withOrganization()->create();
    $organization = $user->fresh()->currentOrganization;

    $this->actingAs($user)
        ->get(route('tenant.dashboard', $organization))
        ->assertRedirect(route('verification.notice'));
});

test('visiting an organization makes it the current one', function () {
    $user = User::factory()->withOrganization()->create();
    $second = Organization::factory()->withMember($user, OrganizationRole::Member)->create();

    $this->actingAs($user)
        ->get(route('tenant.dashboard', $second))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('currentOrganization.slug', $second->slug)
            ->where('currentOrganization.role', 'member')
            ->has('organizations', 2));

    expect($user->fresh()->current_organization_id)->toBe($second->id);
});

test('internal ids are not exposed to the frontend', function () {
    $user = User::factory()->withOrganization()->create();

    $this->actingAs($user)
        ->get(route('tenant.dashboard', $user->fresh()->currentOrganization))
        ->assertInertia(fn ($page) => $page
            ->missing('auth.user.id')
            ->missing('auth.user.current_organization_id')
            ->has('auth.user.public_id')
            ->missing('currentOrganization.id')
            ->missing('organizations.0.id'));
});
