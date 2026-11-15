<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Members\RemoveMember;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LeaveOrganizationController extends Controller
{
    public function __invoke(Organization $organization, CurrentOrganization $current, RemoveMember $removeMember): RedirectResponse
    {
        $removeMember->handle($current->membership() ?? abort(404));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You left :organization.', ['organization' => $organization->name])]);

        // URL defaults point at the organization we just left; go through the home redirect.
        return redirect()->route('dashboard', [], 303);
    }
}
