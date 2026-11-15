<?php

namespace App\Console\Commands;

use App\Actions\Organizations\PurgeOrganization;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('organizations:purge')]
#[Description('Permanently delete organizations past their deletion grace period, and expired export archives (runs daily).')]
class PurgeOrganizations extends Command
{
    public function handle(PurgeOrganization $purge): int
    {
        $graceDays = (int) config('tenancy.deletion_grace_days');
        $purged = 0;

        Organization::withTrashed()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query
                    ->where('status', OrganizationStatus::PendingDeletion)
                    ->where('deletion_scheduled_at', '<=', now()))
                ->orWhere('deleted_at', '<=', now()->subDays($graceDays)))
            ->each(function (Organization $organization) use ($purge, &$purged): void {
                $purge->handle($organization);
                $purged++;
            });

        $expiredExports = $this->pruneExports();

        $this->components->info("Purged {$purged} organization(s) and {$expiredExports} expired export(s).");

        return self::SUCCESS;
    }

    protected function pruneExports(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDays((int) config('tenancy.export_retention_days'))->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles('exports') as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
