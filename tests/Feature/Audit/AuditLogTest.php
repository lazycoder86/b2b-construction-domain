<?php

use App\Enums\OrganizationRole;
use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->organization = Organization::factory()->create();
    $this->owner = memberOf($this->organization, OrganizationRole::Owner, ['name' => 'Olive Owner', 'email' => 'olive@example.com']);
});

function lastAudit(string $action): ?AuditLog
{
    return AuditLog::where('action', $action)->latest('id')->first();
}

test('member management is audited with actor, subject and details', function () {
    $this->actingAs($this->owner);
    $member = memberOf($this->organization);

    $this->post(route('tenant.invitations.store', $this->organization), ['email' => 'new@example.com', 'role' => 'admin']);
    $this->patch(route('tenant.members.update', [$this->organization, $member]), ['role' => 'viewer']);
    $this->delete(route('tenant.members.destroy', [$this->organization, $member]));

    expect(lastAudit('member.invited'))
        ->organization_id->toBe($this->organization->id)
        ->user_id->toBe($this->owner->id)
        ->actor_email->toBe('olive@example.com')
        ->subject_label->toBe('new@example.com')
        ->metadata->toBe(['role' => 'admin'])
        ->ip_address->toBe('127.0.0.1');

    expect(lastAudit('member.role_changed'))
        ->subject_label->toBe($member->name)
        ->metadata->toBe(['from' => 'member', 'to' => 'viewer']);

    expect(lastAudit('member.removed')->metadata)->toBe(['role' => 'viewer']);
});

test('leaving is recorded differently from being removed', function () {
    $member = memberOf($this->organization);

    $this->actingAs($member)->delete(route('tenant.membership.destroy', $this->organization));

    expect(lastAudit('member.left')?->user_id)->toBe($member->id)
        ->and(lastAudit('member.removed'))->toBeNull();
});

test('organization changes record before and after values', function () {
    $this->actingAs($this->owner)->patch(route('tenant.settings.update', $this->organization), ['name' => 'Renamed', 'slug' => $this->organization->slug]);

    expect(lastAudit('organization.updated')->metadata)->toBe([
        'from' => ['name' => $this->organization->name],
        'to' => ['name' => 'Renamed'],
    ]);
});

test('logins, failed logins and logouts are platform-level entries', function () {
    $this->post(route('login.store'), ['email' => 'olive@example.com', 'password' => 'wrong']);
    $this->post(route('login.store'), ['email' => 'olive@example.com', 'password' => 'password']);
    $this->post(route('logout'));

    $failed = lastAudit('auth.login_failed');

    expect($failed->organization_id)->toBeNull()
        ->and($failed->metadata)->toBe(['email' => 'olive@example.com'])
        ->and(json_encode($failed->toArray()))->not->toContain('wrong')
        ->and(lastAudit('auth.login')?->user_id)->toBe($this->owner->id)
        ->and(lastAudit('auth.logout')?->user_id)->toBe($this->owner->id);
});

test('entries keep the actor snapshot after the user is deleted', function () {
    $this->actingAs($this->owner);
    Audit::record('organization.updated', $this->organization);

    $this->owner->memberships()->delete();
    $this->owner->delete();

    expect(lastAudit('organization.updated'))
        ->user_id->toBeNull()
        ->actor_email->toBe('olive@example.com');
});

test('entries are append-only', function () {
    $entry = Audit::record('organization.updated', $this->organization);

    expect(fn () => $entry->update(['action' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);
});

test('old entries are pruned after the retention period', function () {
    config(['audit.retention_days' => 30]);
    $old = Audit::record('organization.updated', $this->organization);
    AuditLog::whereKey($old->id)->toBase()->update(['created_at' => now()->subDays(31)]);
    $recent = Audit::record('organization.updated', $this->organization);

    $this->artisan('model:prune', ['--model' => [AuditLog::class]])->assertSuccessful();

    expect(AuditLog::find($old->id))->toBeNull()
        ->and(AuditLog::find($recent->id))->not->toBeNull();
});

test('owners and admins can read their organization audit log, filtered', function () {
    $this->actingAs($this->owner);
    Audit::record('member.invited', $this->organization, metadata: ['role' => 'member']);
    Audit::record('billing.plan_changed', $this->organization);
    Audit::record('member.invited', Organization::factory()->create());

    $this->get(route('tenant.audit-log.index', $this->organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/audit-log')
            ->has('entries.data', 2)
            ->where('entries.data.0.action', 'billing.plan_changed')
            ->where('entries.data.0.actor', 'Olive Owner')
            ->missing('entries.data.0.id'));

    $this->get(route('tenant.audit-log.index', [$this->organization, 'action' => 'member']))
        ->assertInertia(fn ($page) => $page->has('entries.data', 1));

    $this->actingAs(memberOf($this->organization, OrganizationRole::Admin))
        ->get(route('tenant.audit-log.index', $this->organization))
        ->assertOk();
});

test('members cannot read the audit log', function () {
    $this->actingAs(memberOf($this->organization))
        ->get(route('tenant.audit-log.index', $this->organization))
        ->assertForbidden();
});

test('Stripe webhooks are audited without a user actor', function () {
    $this->organization->forceFill(['stripe_id' => 'cus_audit'])->save();

    $this->postJson(route('cashier.webhook'), [
        'id' => 'evt_audit',
        'type' => 'invoice.payment_failed',
        'data' => ['object' => ['id' => 'in_1', 'customer' => 'cus_audit', 'amount_due' => 2900, 'currency' => 'usd']],
    ])->assertOk();

    expect(lastAudit('billing.payment_failed'))
        ->user_id->toBeNull()
        ->organization_id->toBe($this->organization->id)
        ->metadata->toMatchArray(['amount' => '$29.00', 'stripe_event' => 'evt_audit']);
});

test('account deletion leaves a platform-level entry', function () {
    $user = User::factory()->create(['email' => 'gone@example.com']);

    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password']);

    expect(lastAudit('user.deleted'))
        ->user_id->toBeNull()
        ->actor_email->toBe('gone@example.com');
});
