<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\RestoreOrganization;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Outside the tenant routes: a pending-deletion organization is not accessible,
 * but its owners can still see when it will be purged and restore it.
 */
class OrganizationDeletionController extends Controller
{
    public function show(Request $request, Organization $organization): Response|RedirectResponse
    {
        $this->ensureOwner($request, $organization);

        if ($organization->status !== OrganizationStatus::PendingDeletion) {
            return to_route('tenant.dashboard', ['organization' => $organization->slug]);
        }

        return Inertia::render('organizations/pending-deletion', [
            'organization' => [
                'name' => $organization->name,
                'slug' => $organization->slug,
                'purge_at' => $organization->deletion_scheduled_at?->toIso8601String(),
            ],
        ]);
    }

    public function restore(Request $request, Organization $organization, RestoreOrganization $restore): RedirectResponse
    {
        $this->ensureOwner($request, $organization);
        abort_unless($organization->status === OrganizationStatus::PendingDeletion, 404);

        $restore->handle($organization, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':organization was restored.', ['organization' => $organization->name])]);

        return to_route('tenant.dashboard', ['organization' => $organization->slug]);
    }

    protected function ensureOwner(Request $request, Organization $organization): void
    {
        abort_unless($organization->membershipFor($request->user())?->role === OrganizationRole::Owner, 404);
    }
}
