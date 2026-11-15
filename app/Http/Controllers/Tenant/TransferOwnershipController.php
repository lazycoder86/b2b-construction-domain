<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Members\TransferOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\Members\TransferOwnershipRequest;
use App\Models\Organization;
use App\Models\User;
use App\Support\Tenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TransferOwnershipController extends Controller
{
    public function __invoke(TransferOwnershipRequest $request, Organization $organization, User $member, CurrentOrganization $current, TransferOwnership $transfer): RedirectResponse
    {
        $transfer->handle(
            $current->membership() ?? abort(404),
            $organization->membershipFor($member) ?? abort(404),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is now an owner. You are now an admin.', ['name' => $member->name])]);

        return back();
    }
}
