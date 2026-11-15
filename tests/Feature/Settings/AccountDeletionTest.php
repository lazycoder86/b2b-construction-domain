<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

test('deleting an account also deletes organizations where the user is the only member', function () {
    $user = User::factory()->withOrganization()->create();
    $organization = $user->fresh()->currentOrganization;

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    expect($user->fresh())->toBeNull()
        ->and($organization->fresh()->trashed())->toBeTrue();
});

test('the sole owner of an organization with other members cannot delete their account', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->withOwner($owner)->create(['name' => 'Busy Org']);
    $organization->addMember(User::factory()->create(), OrganizationRole::Member);

    $this->actingAs($owner)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors(['password' => __('You are the only owner of :organizations. Transfer ownership or remove the other members before deleting your account.', ['organizations' => 'Busy Org'])])
        ->assertRedirect(route('profile.edit'));

    $this->assertAuthenticated();
    expect($owner->fresh())->not->toBeNull()
        ->and($organization->fresh()->trashed())->toBeFalse();
});

test('an owner can leave when another owner remains', function () {
    $owner = User::factory()->create();
    $organization = Organization::factory()->withOwner($owner)->create();
    $organization->addMember(User::factory()->create(), OrganizationRole::Owner);

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect($owner->fresh())->toBeNull()
        ->and($organization->fresh()->trashed())->toBeFalse()
        ->and($organization->memberships()->count())->toBe(1);
});

test('members can always delete their account', function () {
    $member = User::factory()->create();
    $organization = Organization::factory()->withOwner()->withMember($member)->create();

    $this->actingAs($member)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect($member->fresh())->toBeNull()
        ->and($organization->fresh()->trashed())->toBeFalse();
});
