<?php

use App\Contracts\Billing\BillingGateway;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Organization;
use App\Notifications\OrganizationDeletionScheduled;
use Illuminate\Support\Facades\Notification;
use Laravel\Pennant\Feature;
use Tests\Fixtures\FakeBillingGateway;

beforeEach(function () {
    Notification::fake();
    app()->instance(BillingGateway::class, $this->billing = new FakeBillingGateway);
    $this->organization = Organization::factory()->create(['slug' => 'doomed-co']);
    $this->owner = memberOf($this->organization, OrganizationRole::Owner);
    $this->member = memberOf($this->organization);
});

function scheduleDeletion(array $input = ['password' => 'password', 'confirmation' => 'doomed-co'])
{
    return test()->delete(route('tenant.organization.destroy', test()->organization), $input);
}

test('owners schedule deletion with their password and the slug', function () {
    $this->actingAs($this->owner);

    scheduleDeletion(['password' => 'wrong', 'confirmation' => 'doomed-co'])->assertSessionHasErrors('password');
    scheduleDeletion(['password' => 'password', 'confirmation' => 'nope'])->assertSessionHasErrors('confirmation');

    scheduleDeletion()->assertRedirect(route('organizations.deletion.show', $this->organization));

    $organization = $this->organization->fresh();

    expect($organization->status)->toBe(OrganizationStatus::PendingDeletion)
        ->and($organization->deletion_scheduled_at->isSameDay(now()->addDays(30)))->toBeTrue()
        ->and($this->billing->callsTo('cancelNow'))->toBe([[$this->organization->id]])
        ->and(AuditLog::where('action', 'organization.deletion_scheduled')->exists())->toBeTrue();

    Notification::assertSentTo($this->owner, OrganizationDeletionScheduled::class);
});

test('only owners can delete an organization', function () {
    $this->actingAs(memberOf($this->organization, OrganizationRole::Admin));

    scheduleDeletion()->assertForbidden();
});

test('members lose access while owners are sent to the restore page', function () {
    $this->actingAs($this->owner);
    scheduleDeletion();

    $this->actingAs($this->member)->get(route('tenant.dashboard', $this->organization))->assertForbidden();

    $this->actingAs($this->owner)->get(route('tenant.dashboard', $this->organization))
        ->assertRedirect(route('organizations.deletion.show', $this->organization));

    $this->actingAs($this->owner)->get(route('organizations.deletion.show', $this->organization))
        ->assertInertia(fn ($page) => $page->component('organizations/pending-deletion'));

    $this->actingAs($this->member)->get(route('organizations.deletion.show', $this->organization))->assertNotFound();
});

test('owners can restore during the grace period', function () {
    $this->actingAs($this->owner);
    scheduleDeletion();

    $this->post(route('organizations.restore', $this->organization))
        ->assertRedirect(route('tenant.dashboard', $this->organization));

    expect($this->organization->fresh())
        ->status->toBe(OrganizationStatus::Active)
        ->deletion_scheduled_at->toBeNull();

    $this->actingAs($this->member)->get(route('tenant.dashboard', $this->organization))->assertOk();
});

test('the purge deletes organizations past their grace period with all their data', function () {
    $this->actingAs($this->owner);
    scheduleDeletion();
    Invitation::factory()->create(['organization_id' => $this->organization->id]);
    $notYet = Organization::factory()->create(['status' => OrganizationStatus::PendingDeletion, 'deletion_scheduled_at' => now()->addDays(40)]);
    $oldTrashed = Organization::factory()->create();
    $oldTrashed->delete();
    Organization::withTrashed()->whereKey($oldTrashed->id)->update(['deleted_at' => now()->subDays(31)]);

    $this->travel(31)->days();
    $this->artisan('organizations:purge')->assertSuccessful();

    expect(Organization::withTrashed()->find($this->organization->id))->toBeNull()
        ->and(Organization::withTrashed()->find($oldTrashed->id))->toBeNull()
        ->and(Organization::find($notYet->id))->not->toBeNull()
        ->and(Invitation::withoutGlobalScopes()->count())->toBe(0)
        ->and($this->member->fresh()->belongsToOrganization($this->organization))->toBeFalse()
        ->and($this->billing->callsTo('deleteCustomer'))->toContain([$this->organization->id])
        // The organization's own audit trail goes with it; a platform-level record remains.
        ->and(AuditLog::where('organization_id', $this->organization->id)->count())->toBe(0)
        ->and(AuditLog::where('action', 'organization.purged')->where('subject_label', $this->organization->name)->exists())->toBeTrue();
});

test('force deleting an organization removes its feature flag values', function () {
    Feature::for($this->organization)->activate('beta_dashboard');
    $this->assertDatabaseCount('features', 1);

    $this->organization->forceDelete();

    $this->assertDatabaseCount('features', 0);
});
