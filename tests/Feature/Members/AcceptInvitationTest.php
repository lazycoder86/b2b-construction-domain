<?php

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->organization = Organization::factory()->withOwner()->create(['name' => 'Acme']);
    $this->invitation = Invitation::factory()->create([
        'organization_id' => $this->organization->id,
        'email' => 'invitee@example.com',
        'role' => OrganizationRole::Admin,
    ]);
    $this->token = $this->invitation->plainToken;
});

test('guests see the invitation and are sent back to it after login', function () {
    $this->get(route('invitations.show', $this->token))
        ->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertInertia(fn ($page) => $page
            ->component('invitations/show')
            ->where('status', 'guest')
            ->where('invitation.organization', 'Acme')
            ->where('invitation.role', 'Admin'));

    $user = User::factory()->create(['email' => 'invitee@example.com']);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('invitations.show', $this->token));
});

test('the register form is prefilled with the invited email', function () {
    $this->get(route('invitations.show', $this->token));

    $this->get(route('register'))
        ->assertInertia(fn ($page) => $page->where('email', 'invitee@example.com'));
});

test('the invited user can accept and becomes a member with the invited role', function () {
    $user = User::factory()->create(['email' => 'Invitee@Example.com']);

    $this->actingAs($user)
        ->get(route('invitations.show', $this->token))
        ->assertInertia(fn ($page) => $page->where('status', 'ready'));

    $this->actingAs($user)
        ->post(route('invitations.accept', $this->token))
        ->assertRedirect(route('tenant.dashboard', $this->organization));

    expect($user->roleIn($this->organization))->toBe(OrganizationRole::Admin)
        ->and($user->fresh()->current_organization_id)->toBe($this->organization->id)
        ->and($this->invitation->fresh()->isAccepted())->toBeTrue()
        ->and($this->organization->membershipFor($user)->invited_by)->toBe($this->invitation->invited_by);
});

test('invitations are single use', function () {
    $user = User::factory()->create(['email' => 'invitee@example.com']);
    $this->actingAs($user)->post(route('invitations.accept', $this->token));

    $this->actingAs($user)
        ->get(route('invitations.show', $this->token))
        ->assertInertia(fn ($page) => $page->where('status', 'accepted'));

    $this->actingAs($user)
        ->post(route('invitations.accept', $this->token))
        ->assertSessionHasErrors(['invitation' => 'This invitation has already been used.']);
});

test('only the invited email address can accept', function () {
    $intruder = User::factory()->create(['email' => 'someone-else@example.com']);

    $this->actingAs($intruder)
        ->get(route('invitations.show', $this->token))
        ->assertInertia(fn ($page) => $page->where('status', 'wrong_account'));

    $this->actingAs($intruder)
        ->post(route('invitations.accept', $this->token))
        ->assertSessionHasErrors('invitation');

    expect($intruder->belongsToOrganization($this->organization))->toBeFalse();
});

test('accepting requires a verified email address', function () {
    $user = User::factory()->unverified()->create(['email' => 'invitee@example.com']);

    $this->actingAs($user)
        ->get(route('invitations.show', $this->token))
        ->assertInertia(fn ($page) => $page->where('status', 'unverified'));

    $this->actingAs($user)
        ->post(route('invitations.accept', $this->token))
        ->assertRedirect(route('verification.notice'));
});

test('expired invitations cannot be accepted', function () {
    $this->invitation->forceFill(['expires_at' => now()->subMinute()])->save();
    $user = User::factory()->create(['email' => 'invitee@example.com']);

    $this->actingAs($user)
        ->post(route('invitations.accept', $this->token))
        ->assertSessionHasErrors(['invitation' => 'This invitation has expired. Ask for a new one.']);
});

test('unknown tokens and deleted organizations show an invalid invitation', function () {
    $this->get(route('invitations.show', 'nope'))
        ->assertInertia(fn ($page) => $page->where('status', 'invalid')->where('invitation', null));

    $this->organization->delete();

    $this->get(route('invitations.show', $this->token))
        ->assertInertia(fn ($page) => $page->where('status', 'invalid'));
});

test('a resent invitation invalidates the old link', function () {
    $this->invitation->regenerateToken();
    $this->invitation->save();

    $this->get(route('invitations.show', $this->token))
        ->assertInertia(fn ($page) => $page->where('status', 'invalid'));
});

test('accepting respects the seat limit', function () {
    config(['billing.plans.free.limits.seats' => 1]);
    $user = User::factory()->create(['email' => 'invitee@example.com']);

    $this->actingAs($user)
        ->post(route('invitations.accept', $this->token))
        ->assertSessionHasErrors('invitation');

    expect($user->belongsToOrganization($this->organization))->toBeFalse();
});

test('the invited user can decline', function () {
    $user = User::factory()->create(['email' => 'invitee@example.com']);

    $this->actingAs($user)
        ->post(route('invitations.decline', $this->token))
        ->assertRedirect(route('dashboard'));

    expect(Invitation::withoutGlobalScopes()->find($this->invitation->id))->toBeNull();
});

test('other users cannot decline someone else\'s invitation', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('invitations.decline', $this->token))
        ->assertForbidden();
});
