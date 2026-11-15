<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Send the user to their current organization, or to onboarding if they have none.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $organization = $request->user()->homeOrganization();

        if ($organization === null) {
            return to_route('organizations.create');
        }

        return to_route('tenant.dashboard', ['organization' => $organization]);
    }
}
