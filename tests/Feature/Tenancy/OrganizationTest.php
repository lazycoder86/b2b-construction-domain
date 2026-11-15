<?php

use App\Actions\Organizations\CreateOrganization;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

test('the create organization page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('organizations.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organizations/create')
            ->where('isFirstOrganization', true));
});

test('users can create an organization and become its owner', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('organizations.store'), ['name' => 'Acme Inc'])
        ->assertRedirect('/o/acme-inc/dashboard');

    $organization = Organization::where('slug', 'acme-inc')->firstOrFail();

    expect($organization->public_id)->toHaveLength(26)
        ->and($user->roleIn($organization))->toBe(OrganizationRole::Owner)
        ->and($user->fresh()->current_organization_id)->toBe($organization->id);
});

test('a custom slug can be chosen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('organizations.store'), ['name' => 'Acme Inc', 'slug' => 'ACME'])
        ->assertRedirect('/o/acme/dashboard');
});

test('slugs must be unique and well formed', function (string $slug) {
    Organization::factory()->create(['slug' => 'taken']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('organizations.store'), ['name' => 'Acme', 'slug' => $slug])
        ->assertSessionHasErrors('slug');
})->with(['taken', 'ab', 'has space', 'trailing-', 'double--hyphen', 'under_score']);

test('generated slugs avoid collisions, including soft-deleted organizations', function () {
    Organization::factory()->create(['slug' => 'acme'])->delete();
    Organization::factory()->create(['slug' => 'acme-2']);

    expect(CreateOrganization::uniqueSlugFor('Acme'))->toBe('acme-3')
        ->and(CreateOrganization::uniqueSlugFor('A'))->toBe('a-org')
        ->and(CreateOrganization::uniqueSlugFor('!!!'))->toBe('org');
});

test('users can belong to several organizations', function () {
    $user = User::factory()->withOrganization()->create();

    $this->actingAs($user)
        ->post(route('organizations.store'), ['name' => 'Second Org'])
        ->assertRedirect('/o/second-org/dashboard');

    expect($user->organizations()->count())->toBe(2);
});
