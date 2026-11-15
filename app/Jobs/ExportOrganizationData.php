<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationExportReady;
use App\Support\Tenancy\CurrentOrganization;
use App\Support\Tenancy\TenantModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * GDPR-style export: a zip of JSON Lines files with everything the organization owns,
 * stored privately and offered through a signed, expiring link.
 */
class ExportOrganizationData implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public int $organizationId, public int $requestedBy) {}

    public function handle(CurrentOrganization $current): void
    {
        $organization = Organization::find($this->organizationId);
        $requester = User::find($this->requestedBy);

        if ($organization === null || $requester === null) {
            return;
        }

        $exportId = (string) Str::ulid();
        $path = "exports/{$organization->public_id}/{$exportId}.zip";
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($path));

        $current->runAs($organization, fn () => $this->build($organization, $disk->path($path)));

        $requester->notify(new OrganizationExportReady(
            organizationName: $organization->name,
            downloadUrl: URL::temporarySignedRoute(
                'tenant.organization.export.download',
                now()->addHours(24),
                ['organization' => $organization->slug, 'export' => $exportId],
            ),
        ));
    }

    protected function build(Organization $organization, string $zipPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Could not create export archive at [{$zipPath}].");
        }

        $zip->addFromString('organization.json', (string) json_encode([
            'public_id' => $organization->public_id,
            'name' => $organization->name,
            'slug' => $organization->slug,
            'created_at' => $organization->created_at?->toIso8601String(),
            'exported_at' => now()->toIso8601String(),
            'plan' => $organization->entitlements()->toArray(),
        ], JSON_PRETTY_PRINT));

        $this->addLines($zip, 'members.jsonl', $organization->memberships()->with('user')->lazyById()->map(fn (Membership $membership) => [
            'name' => $membership->user->name,
            'email' => $membership->user->email,
            'role' => $membership->role->value,
            'joined_at' => $membership->joined_at?->toIso8601String(),
        ]));

        $this->addLines($zip, 'audit_log.jsonl', $organization->auditLogs()->lazyById()->map(fn (AuditLog $entry) => $entry->toDisplayArray()));

        $this->addLines($zip, 'subscriptions.jsonl', $organization->subscriptions()->get()->map(fn (Model $subscription) => $subscription->only([
            'type', 'stripe_status', 'stripe_price', 'quantity', 'trial_ends_at', 'ends_at', 'created_at',
        ])));

        // Every tenant-owned table, including domain models added later.
        foreach (TenantModels::all() as $model) {
            $this->addLines($zip, Str::snake(Str::pluralStudly(class_basename($model))).'.jsonl', $model::query()->lazyById()->map(fn (Model $record) => $record->toArray()));
        }

        $zip->close();
    }

    /**
     * @param  iterable<mixed>  $rows
     */
    protected function addLines(ZipArchive $zip, string $name, iterable $rows): void
    {
        $lines = '';

        foreach ($rows as $row) {
            $lines .= json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        }

        $zip->addFromString($name, $lines);
    }
}
