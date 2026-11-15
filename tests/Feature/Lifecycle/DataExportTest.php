<?php

use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Organization;
use App\Notifications\OrganizationExportReady;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->organization = Organization::factory()->create(['name' => 'Export Co']);
    $this->owner = memberOf($this->organization, OrganizationRole::Owner, ['email' => 'owner@export.test']);
    Invitation::factory()->create(['organization_id' => $this->organization->id, 'email' => 'invitee@export.test']);
});

test('owners can export all organization data as a zip and download it via a signed link', function () {
    $this->actingAs($this->owner)
        ->post(route('tenant.organization.export', $this->organization))
        ->assertSessionHasNoErrors();

    $url = null;
    Notification::assertSentTo($this->owner, OrganizationExportReady::class, function (OrganizationExportReady $notification) use (&$url) {
        $url = $notification->downloadUrl;

        return true;
    });

    $files = Storage::disk('local')->allFiles("exports/{$this->organization->public_id}");
    expect($files)->toHaveCount(1);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($files[0]));
    $members = $zip->getFromName('members.jsonl');
    $invitations = $zip->getFromName('invitations.jsonl');
    $organization = json_decode((string) $zip->getFromName('organization.json'), true);
    $zip->close();

    expect($organization['name'])->toBe('Export Co')
        ->and($members)->toContain('owner@export.test')
        ->and($invitations)->toContain('invitee@export.test')
        ->and($invitations)->not->toContain('token_hash');

    $this->actingAs($this->owner)->get($url)->assertOk()->assertDownload();

    expect(AuditLog::whereIn('action', ['organization.export_requested', 'organization.export_downloaded'])->count())->toBe(2);
});

test('export links must be signed and are for owners only', function () {
    $this->actingAs($this->owner)->post(route('tenant.organization.export', $this->organization));
    $exportId = basename(Storage::disk('local')->allFiles("exports/{$this->organization->public_id}")[0], '.zip');

    $this->actingAs($this->owner)
        ->get(route('tenant.organization.export.download', [$this->organization, $exportId]))
        ->assertForbidden();

    $signed = URL::temporarySignedRoute('tenant.organization.export.download', now()->addHour(), ['organization' => $this->organization->slug, 'export' => $exportId]);

    $this->actingAs(memberOf($this->organization, OrganizationRole::Admin))->get($signed)->assertForbidden();
});

test('admins cannot request exports', function () {
    $this->actingAs(memberOf($this->organization, OrganizationRole::Admin))
        ->post(route('tenant.organization.export', $this->organization))
        ->assertForbidden();
});

test('old export archives are removed by the daily purge', function () {
    $this->actingAs($this->owner)->post(route('tenant.organization.export', $this->organization));

    $this->travel(8)->days();
    $this->artisan('organizations:purge')->assertSuccessful();

    expect(Storage::disk('local')->allFiles('exports'))->toBe([]);
});

test('users can download their personal data', function () {
    $this->actingAs($this->owner)
        ->get(route('profile.export'))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="personal-data.json"')
        ->assertJsonPath('profile.email', 'owner@export.test')
        ->assertJsonPath('organizations.0.organization', 'Export Co')
        ->assertJsonPath('organizations.0.role', 'owner');

    expect(AuditLog::where('action', 'user.data_exported')->exists())->toBeTrue();
});
