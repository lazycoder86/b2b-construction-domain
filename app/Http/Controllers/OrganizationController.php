<?php

namespace App\Http\Controllers;

use App\Actions\Organizations\CreateOrganization;
use App\Http\Requests\Organizations\StoreOrganizationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('organizations/create', [
            'isFirstOrganization' => ! $request->user()->memberships()->exists(),
        ]);
    }

    public function store(StoreOrganizationRequest $request, CreateOrganization $createOrganization): RedirectResponse
    {
        $organization = $createOrganization->handle(
            $request->user(),
            $request->validated('name'),
            $request->validated('slug'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organization created.')]);

        return to_route('tenant.dashboard', ['organization' => $organization]);
    }
}
