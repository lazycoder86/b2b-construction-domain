<?php

use App\Actions\Members\ChangeMemberRole;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->owner = memberOf($this->organization, OrganizationRole::Owner);
});

function changeRole(Organization $organization, User $member, OrganizationRole $role)
{
    return test()->patch(route('tenant.members.update', [$organization, $member]), ['role' => $role->value]);
}

test('members can see the member list', function () {
    $member = memberOf($this->organization, OrganizationRole::Member);

    $this->actingAs($member)
        ->get(route('tenant.members.index', $this->organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/members/index')
            ->has('members', 2)
            ->where('members.0.role', 'owner')
            ->where('members.0.can_remove', false)
            ->where('members.0.assignable_roles', [])
            ->where('canInvite', false)
            ->where('invitations', [])
            ->missing('members.0.id'));
});

test('the owner sees what they can do', function () {
    $admin = memberOf($this->organization, OrganizationRole::Admin);

    $this->actingAs($this->owner)
        ->get(route('tenant.members.index', $this->organization))
        ->assertInertia(fn ($page) => $page
            ->where('canInvite', true)
            ->where('canTransferOwnership', true)
            ->where('canLeave', false)
            ->where('invitableRoles', ['owner', 'admin', 'member', 'viewer'])
            ->where('members.1.public_id', $admin->public_id)
            ->where('members.1.can_remove', true)
            ->where('members.1.assignable_roles', ['owner', 'admin', 'member', 'viewer']));
});

test('admins can change roles of non-owners', function () {
    $admin = memberOf($this->organization, OrganizationRole::Admin);
    $member = memberOf($this->organization);

    $this->actingAs($admin);
    changeRole($this->organization, $member, OrganizationRole::Viewer)->assertSessionHasNoErrors();

    expect($member->roleIn($this->organization))->toBe(OrganizationRole::Viewer);
});

test('admins cannot promote to owner or touch owners', function () {
    $admin = memberOf($this->organization, OrganizationRole::Admin);
    $member = memberOf($this->organization);

    $this->actingAs($admin);
    changeRole($this->organization, $member, OrganizationRole::Owner)->assertForbidden();
    changeRole($this->organization, $this->owner, OrganizationRole::Member)->assertForbidden();
    $this->delete(route('tenant.members.destroy', [$this->organization, $this->owner]))->assertForbidden();
});

test('members cannot change roles', function () {
    $member = memberOf($this->organization);
    $other = memberOf($this->organization);

    $this->actingAs($member);
    changeRole($this->organization, $other, OrganizationRole::Admin)->assertForbidden();
});

test('nobody can change their own role', function () {
    $this->actingAs($this->owner);
    changeRole($this->organization, $this->owner, OrganizationRole::Admin)->assertForbidden();
});

test('owners can demote another owner but never the last one', function () {
    $second = memberOf($this->organization, OrganizationRole::Owner);

    $this->actingAs($this->owner);
    changeRole($this->organization, $second, OrganizationRole::Admin)->assertSessionHasNoErrors();
    expect($second->roleIn($this->organization))->toBe(OrganizationRole::Admin);

    // Simulate a lone owner being demoted through the action directly.
    $this->expectException(ValidationException::class);
    app(ChangeMemberRole::class)->handle($this->organization->membershipFor($this->owner), OrganizationRole::Admin);
});

test('admins can remove members, who lose access immediately', function () {
    $admin = memberOf($this->organization, OrganizationRole::Admin);
    $member = memberOf($this->organization);
    $member->switchOrganization($this->organization);

    $this->actingAs($admin)
        ->delete(route('tenant.members.destroy', [$this->organization, $member]))
        ->assertSessionHasNoErrors();

    expect($member->belongsToOrganization($this->organization))->toBeFalse()
        ->and($member->fresh()->current_organization_id)->toBeNull();

    $this->actingAs($member)
        ->get(route('tenant.dashboard', $this->organization))
        ->assertNotFound();
});

test('members of other organizations cannot be targeted', function () {
    $outsider = Organization::factory()->create();
    $victim = memberOf($outsider);

    $this->actingAs($this->owner)
        ->delete("/o/{$this->organization->slug}/members/{$victim->public_id}")
        ->assertNotFound();

    expect($victim->belongsToOrganization($outsider))->toBeTrue();
});

test('members can leave', function () {
    $member = memberOf($this->organization);

    $this->actingAs($member)
        ->delete(route('tenant.membership.destroy', $this->organization))
        ->assertRedirect(route('dashboard'));

    expect($member->belongsToOrganization($this->organization))->toBeFalse();
});

test('the last owner cannot leave', function () {
    memberOf($this->organization);

    $this->actingAs($this->owner)
        ->delete(route('tenant.membership.destroy', $this->organization))
        ->assertSessionHasErrors('member');

    expect($this->owner->belongsToOrganization($this->organization))->toBeTrue();
});

test('owners can transfer ownership after confirming their password', function () {
    $admin = memberOf($this->organization, OrganizationRole::Admin);

    $this->actingAs($this->owner)
        ->post(route('tenant.members.transfer-ownership', [$this->organization, $admin]), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');

    $this->actingAs($this->owner)
        ->post(route('tenant.members.transfer-ownership', [$this->organization, $admin]), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect($admin->roleIn($this->organization))->toBe(OrganizationRole::Owner)
        ->and($this->owner->roleIn($this->organization))->toBe(OrganizationRole::Admin);
});

test('admins cannot transfer ownership', function () {
    $admin = memberOf($this->organization, OrganizationRole::Admin);
    $member = memberOf($this->organization);

    $this->actingAs($admin)
        ->post(route('tenant.members.transfer-ownership', [$this->organization, $member]), ['password' => 'password'])
        ->assertForbidden();
});
