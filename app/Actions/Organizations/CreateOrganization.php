<?php

namespace App\Actions\Organizations;

use App\Enums\OrganizationRole;
use App\Facades\Audit;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOrganization
{
    /**
     * Create an organization with the given user as its owner and make it
     * the user's current organization.
     */
    public function handle(User $owner, string $name, ?string $slug = null): Organization
    {
        return DB::transaction(function () use ($owner, $name, $slug): Organization {
            $organization = Organization::create([
                'name' => $name,
                'slug' => $slug ?? self::uniqueSlugFor($name),
            ]);

            // App-managed trial: no card needed (Cashier calls this a "generic trial").
            $trialDays = (int) config('billing.trial.days');

            if ($trialDays > 0) {
                $organization->forceFill(['trial_ends_at' => now()->addDays($trialDays)])->save();
            }

            $organization->addMember($owner, OrganizationRole::Owner);

            Audit::record('organization.created', $organization, $organization, [
                'trial_ends_at' => $organization->trial_ends_at?->toIso8601String(),
            ], actor: $owner);
            $owner->switchOrganization($organization);

            return $organization;
        });
    }

    /**
     * Derive an available slug from a name: "Acme Inc" -> "acme-inc", "acme-inc-2", ...
     */
    public static function uniqueSlugFor(string $name): string
    {
        $base = Str::limit(Str::slug($name), 40, '');

        if (strlen($base) < 3) {
            $base = trim($base.'-org', '-');
        }

        $slug = $base;
        $suffix = 2;

        while (Organization::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
