<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;

test('any member can view the settings page', function (OrganizationRole $role, bool $canUpdate) {
    $organization = Organization::factory()->create();
    $user = memberOf($organization, $role);

    $this->actingAs($user)
        ->get(route('tenant.settings.edit', $organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/settings/general')
            ->where('canUpdate', $canUpdate));
})->with([
    'owner' => [OrganizationRole::Owner, true],
    'admin' => [OrganizationRole::Admin, true],
    'member' => [OrganizationRole::Member, false],
    'viewer' => [OrganizationRole::Viewer, false],
]);

test('owners and admins can update the organization', function (OrganizationRole $role) {
    $organization = Organization::factory()->create();
    $user = memberOf($organization, $role);

    $this->actingAs($user)
        ->patch(route('tenant.settings.update', $organization), ['name' => 'Renamed', 'slug' => 'renamed'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/o/renamed/settings');

    expect($organization->fresh())
        ->name->toBe('Renamed')
        ->slug->toBe('renamed');
})->with([OrganizationRole::Owner, OrganizationRole::Admin]);

test('members and viewers cannot update the organization', function (OrganizationRole $role) {
    $organization = Organization::factory()->create(['name' => 'Original']);
    $user = memberOf($organization, $role);

    $this->actingAs($user)
        ->patch(route('tenant.settings.update', $organization), ['name' => 'Hacked', 'slug' => 'hacked'])
        ->assertForbidden();

    expect($organization->fresh()->name)->toBe('Original');
})->with([OrganizationRole::Member, OrganizationRole::Viewer]);

test('the slug must stay unique when updating', function () {
    Organization::factory()->create(['slug' => 'taken']);
    $organization = Organization::factory()->create(['slug' => 'mine']);
    $user = memberOf($organization, OrganizationRole::Owner);

    $this->actingAs($user)
        ->patch(route('tenant.settings.update', $organization), ['name' => 'Mine', 'slug' => 'taken'])
        ->assertSessionHasErrors('slug');

    // Keeping its own slug is fine.
    $this->actingAs($user)
        ->patch(route('tenant.settings.update', $organization), ['name' => 'Mine', 'slug' => 'mine'])
        ->assertSessionHasNoErrors();
});

test('admins of one organization cannot update another', function () {
    $mine = Organization::factory()->create();
    $theirs = Organization::factory()->create(['name' => 'Theirs']);
    $user = memberOf($mine, OrganizationRole::Admin);

    $this->actingAs($user)
        ->patch(route('tenant.settings.update', $theirs), ['name' => 'Hacked', 'slug' => 'hacked'])
        ->assertNotFound();

    expect($theirs->fresh()->name)->toBe('Theirs');
});
