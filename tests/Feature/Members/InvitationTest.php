<?php

use App\Enums\OrganizationRole;
use App\Models\Invitation;
use App\Models\Organization;
use App\Notifications\OrganizationInvitation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->organization = Organization::factory()->create(['name' => 'Acme']);
    $this->owner = memberOf($this->organization, OrganizationRole::Owner, ['name' => 'Olive Owner']);
});

function invite(Organization $organization, string $email, OrganizationRole|string $role = OrganizationRole::Member)
{
    return test()->post(route('tenant.invitations.store', $organization), [
        'email' => $email,
        'role' => $role instanceof OrganizationRole ? $role->value : $role,
    ]);
}

test('admins can invite people and an email is sent', function () {
    $admin = memberOf($this->organization, OrganizationRole::Admin);

    $this->actingAs($admin);
    invite($this->organization, 'New.Person@Example.com')->assertSessionHasNoErrors();

    $invitation = Invitation::withoutGlobalScopes()->sole();
    expect($invitation)
        ->email->toBe('new.person@example.com')
        ->role->toBe(OrganizationRole::Member)
        ->invited_by->toBe($admin->id)
        ->and($invitation->expires_at->isFuture())->toBeTrue()
        ->and($invitation->token_hash)->toHaveLength(64);

    Notification::assertSentTo(
        new AnonymousNotifiable,
        OrganizationInvitation::class,
        function (OrganizationInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use ($invitation) {
            $token = str($notification->acceptUrl)->afterLast('/')->toString();

            return $notifiable->routes['mail'] === 'new.person@example.com'
                && $notification->organizationName === 'Acme'
                && Invitation::hashToken($token) === $invitation->token_hash;
        },
    );
});

test('the invitation email reads well', function () {
    $this->actingAs($this->owner);
    invite($this->organization, 'someone@example.com', OrganizationRole::Admin);

    Notification::assertSentOnDemand(OrganizationInvitation::class, function (OrganizationInvitation $notification) {
        $mail = $notification->toMail(new AnonymousNotifiable);

        return $mail->subject === 'You are invited to join Acme'
            && str_contains($mail->introLines[0], 'Olive Owner has invited you to join Acme as Admin.')
            && $mail->actionUrl === $notification->acceptUrl;
    });
});

test('members and viewers cannot invite', function (OrganizationRole $role) {
    $this->actingAs(memberOf($this->organization, $role));

    invite($this->organization, 'x@example.com')->assertForbidden();

    Notification::assertNothingSent();
})->with([OrganizationRole::Member, OrganizationRole::Viewer]);

test('admins cannot invite owners but owners can', function () {
    $this->actingAs(memberOf($this->organization, OrganizationRole::Admin));
    invite($this->organization, 'boss@example.com', OrganizationRole::Owner)->assertForbidden();

    $this->actingAs($this->owner);
    invite($this->organization, 'boss@example.com', OrganizationRole::Owner)->assertSessionHasNoErrors();
});

test('the email and role are validated', function () {
    $this->actingAs($this->owner);

    invite($this->organization, 'not-an-email')->assertSessionHasErrors('email');
    invite($this->organization, 'ok@example.com', 'superuser')->assertSessionHasErrors('role');
});

test('existing members cannot be invited again', function () {
    memberOf($this->organization, OrganizationRole::Member, ['email' => 'already@example.com']);

    $this->actingAs($this->owner);
    invite($this->organization, 'ALREADY@example.com')->assertSessionHasErrors(['email' => 'This person is already a member.']);
});

test('a second open invitation for the same email is refused', function () {
    $this->actingAs($this->owner);
    invite($this->organization, 'twice@example.com')->assertSessionHasNoErrors();
    invite($this->organization, 'Twice@example.com')->assertSessionHasErrors('email');

    expect(Invitation::withoutGlobalScopes()->count())->toBe(1);
});

test('an expired invitation is replaced by a new one', function () {
    Invitation::factory()->expired()->create(['organization_id' => $this->organization->id, 'email' => 'late@example.com']);

    $this->actingAs($this->owner);
    invite($this->organization, 'late@example.com')->assertSessionHasNoErrors();

    $invitations = Invitation::withoutGlobalScopes()->get();
    expect($invitations)->toHaveCount(1)
        ->and($invitations->first()->isExpired())->toBeFalse();
});

test('seat limits count members and pending invitations', function () {
    config(['billing.plans.free.limits.seats' => 2]);
    $this->actingAs($this->owner);

    invite($this->organization, 'first@example.com')->assertSessionHasNoErrors();
    invite($this->organization, 'second@example.com')->assertSessionHasErrors(['email' => 'Your organization has no seats left.']);
});

test('invitations can be resent with a fresh link', function () {
    $invitation = Invitation::factory()->create(['organization_id' => $this->organization->id, 'expires_at' => now()->addHour()]);
    $oldHash = $invitation->token_hash;

    $this->actingAs($this->owner)
        ->post(route('tenant.invitations.resend', [$this->organization, $invitation]))
        ->assertSessionHasNoErrors();

    $invitation = Invitation::withoutGlobalScopes()->find($invitation->id);
    expect($invitation->token_hash)->not->toBe($oldHash)
        ->and($invitation->expires_at->greaterThan(now()->addDays(6)))->toBeTrue();

    Notification::assertSentOnDemandTimes(OrganizationInvitation::class, 1);
});

test('invitations can be cancelled', function () {
    $invitation = Invitation::factory()->create(['organization_id' => $this->organization->id]);

    $this->actingAs($this->owner)
        ->delete(route('tenant.invitations.destroy', [$this->organization, $invitation]))
        ->assertSessionHasNoErrors();

    expect(Invitation::withoutGlobalScopes()->count())->toBe(0);
});

test('admins cannot manage invitations for owners', function () {
    $invitation = Invitation::factory()->create(['organization_id' => $this->organization->id, 'role' => OrganizationRole::Owner]);

    $this->actingAs(memberOf($this->organization, OrganizationRole::Admin))
        ->delete(route('tenant.invitations.destroy', [$this->organization, $invitation]))
        ->assertForbidden();
});

test('invitations of another organization cannot be touched', function () {
    $foreign = Invitation::factory()->create();

    $this->actingAs($this->owner)
        ->delete("/o/{$this->organization->slug}/invitations/{$foreign->public_id}")
        ->assertNotFound();

    expect(Invitation::withoutGlobalScopes()->find($foreign->id))->not->toBeNull();
});
