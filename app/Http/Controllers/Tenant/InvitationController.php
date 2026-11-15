<?php

namespace App\Http\Controllers\Tenant;

use App\Actions\Invitations\InviteMember;
use App\Actions\Invitations\ResendInvitation;
use App\Facades\Audit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invitations\StoreInvitationRequest;
use App\Models\Invitation;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class InvitationController extends Controller
{
    public function store(StoreInvitationRequest $request, Organization $organization, InviteMember $invite): RedirectResponse
    {
        $invitation = $invite->handle($organization, $request->user(), $request->validated('email'), $request->role());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $invitation->email])]);

        return back();
    }

    public function resend(Organization $organization, Invitation $invitation, ResendInvitation $resend): RedirectResponse
    {
        Gate::authorize('manage', $invitation);
        abort_if($invitation->isAccepted(), 404);

        $resend->handle($invitation);
        Audit::record('invitation.resent', $organization, $invitation, subjectLabel: $invitation->email);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation resent to :email.', ['email' => $invitation->email])]);

        return back();
    }

    public function destroy(Organization $organization, Invitation $invitation): RedirectResponse
    {
        Gate::authorize('manage', $invitation);
        abort_if($invitation->isAccepted(), 404);

        Audit::record('invitation.cancelled', $organization, $invitation, ['role' => $invitation->role->value], subjectLabel: $invitation->email);
        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return back();
    }
}
