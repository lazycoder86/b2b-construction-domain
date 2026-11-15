<?php

namespace App\Http\Controllers\Tenant;

use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizations\UpdateOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationSettingsController extends Controller
{
    public function edit(Request $request, Organization $organization): Response
    {
        Gate::authorize('view', $organization);

        return Inertia::render('tenant/settings/general', [
            'organization' => [
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'canUpdate' => $request->user()->can('update', $organization),
            'canExport' => $request->user()->can('export', $organization),
            'canDelete' => $request->user()->can('delete', $organization),
            'deletionGraceDays' => (int) config('tenancy.deletion_grace_days'),
            'security' => [
                'require_two_factor' => $organization->require_two_factor,
                'can_manage' => $request->user()->can('manageSecurity', $organization),
                'user_has_mfa' => $request->user()->hasMultiFactorAuthentication(),
            ],
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $original = $organization->only(['name', 'slug']);
        $organization->update($request->validated());

        $changes = array_intersect_key($organization->getChanges(), $original);

        if ($changes !== []) {
            Audit::record('organization.updated', $organization, $organization, [
                'from' => array_intersect_key($original, $changes),
                'to' => $changes,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization updated.')]);

        // The slug may have changed, so build the URL from the fresh value.
        return to_route('tenant.settings.edit', ['organization' => $organization->slug]);
    }
}
