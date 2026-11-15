<?php

namespace App\Http\Controllers\Tenant;

use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrganizationSecurityController extends Controller
{
    public function update(Request $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('manageSecurity', $organization);

        $validated = $request->validate([
            'require_two_factor' => ['required', 'boolean'],
        ]);

        $require = (bool) $validated['require_two_factor'];

        // Don't let owners lock themselves out.
        if ($require && ! $request->user()->hasMultiFactorAuthentication()) {
            throw ValidationException::withMessages([
                'require_two_factor' => __('Set up two-factor authentication or a passkey for your own account first.'),
            ]);
        }

        $organization->forceFill(['require_two_factor' => $require])->save();

        if ($organization->wasChanged('require_two_factor')) {
            Audit::record('organization.security_updated', $organization, $organization, ['require_two_factor' => $require]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Security settings updated.')]);

        return back();
    }
}
