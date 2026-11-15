<?php

use App\Enums\OrganizationRole;
use App\Facades\Audit;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\Impersonation\Impersonation;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->target = memberOf($this->organization, OrganizationRole::Owner, ['name' => 'Tina Target']);
    $this->admin = platformAdmin(['name' => 'Sam Support']);
});

function impersonate(User $target, string $reason = 'Ticket #123: cannot see invoices')
{
    return test()->post(route('admin.users.impersonate', $target->public_id), ['reason' => $reason]);
}

test('admins can impersonate a user, with a reason, and it is audited', function () {
    impersonate($this->target, 'no')->assertSessionHasErrors('reason');

    impersonate($this->target)->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->target);
    expect(AuditLog::where('action', 'admin.impersonation_started')->sole())
        ->user_id->toBe($this->admin->id)
        ->subject_label->toBe('Tina Target')
        ->metadata->toBe(['reason' => 'Ticket #123: cannot see invoices'])
        // Switching users is not a normal login.
        ->and(AuditLog::where('action', 'auth.login')->count())->toBe(0);
});

test('actions while impersonating are attributed to the impersonator too', function () {
    impersonate($this->target);

    $this->get(route('tenant.dashboard', $this->organization))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('impersonation.impersonator', 'Sam Support'));

    Audit::record('organization.updated', $this->organization);

    expect(AuditLog::where('action', 'organization.updated')->sole())
        ->user_id->toBe($this->target->id)
        ->impersonator_id->toBe($this->admin->id);
});

test('sensitive areas are blocked while impersonating', function (string $method, string $route, array $parameters) {
    impersonate($this->target);

    $this->{$method}(route($route, array_map(fn ($p) => $p === 'org' ? $this->organization : $p, $parameters)))
        ->assertForbidden();
})->with([
    'security settings' => ['get', 'security.edit', []],
    'admin area' => ['get', 'admin.dashboard', []],
    'checkout' => ['post', 'tenant.billing.subscribe', ['org']],
    'delete account' => ['delete', 'profile.destroy', []],
    'delete organization' => ['delete', 'tenant.organization.destroy', ['org']],
    'export organization' => ['post', 'tenant.organization.export', ['org']],
]);

test('stopping returns to the admin account', function () {
    impersonate($this->target);

    $this->post(route('impersonation.stop'))->assertRedirect(route('admin.users.show', $this->target->public_id));

    $this->assertAuthenticatedAs($this->admin);
    expect(AuditLog::where('action', 'admin.impersonation_stopped')->sole()->metadata)->toBe(['reason' => 'stopped'])
        ->and(app(Impersonation::class)->active())->toBeFalse();
});

test('impersonation expires automatically', function () {
    impersonate($this->target);
    $this->travel(config('admin.impersonation_minutes') + 1)->minutes();

    $this->get(route('tenant.dashboard', $this->organization))->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($this->admin);
    expect(AuditLog::where('action', 'admin.impersonation_stopped')->sole()->metadata)->toBe(['reason' => 'expired']);
});

test('admins and yourself cannot be impersonated', function () {
    impersonate(User::factory()->create(['is_platform_admin' => true]))->assertForbidden();
    impersonate($this->admin)->assertForbidden();

    $this->assertAuthenticatedAs($this->admin);
});

test('regular users cannot stop an impersonation that does not exist', function () {
    $this->actingAs($this->target)->post(route('impersonation.stop'))->assertNotFound();
});
