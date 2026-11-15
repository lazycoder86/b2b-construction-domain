<?php

namespace App\Http\Controllers;

use App\Actions\Invitations\AcceptInvitation;
use App\Facades\Audit;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public side of invitations: the link in the email (/invitations/{token}).
 */
class InvitationAcceptanceController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->find($token);
        $user = $request->user();

        if ($invitation !== null && ($user === null || ! $user->hasVerifiedEmail())) {
            // Come back here after login, registration or email verification.
            redirect()->setIntendedUrl($request->fullUrl());
            $request->session()->put('invitation_email', $invitation->email);
        }

        $status = match (true) {
            $invitation === null => 'invalid',
            $invitation->isAccepted() => 'accepted',
            $invitation->isExpired() => 'expired',
            ! $invitation->organization->isActive() => 'invalid',
            $user === null => 'guest',
            ! $invitation->isFor($user) => 'wrong_account',
            ! $user->hasVerifiedEmail() => 'unverified',
            default => 'ready',
        };

        $response = Inertia::render('invitations/show', [
            'status' => $status,
            'token' => $token,
            'invitation' => $invitation === null ? null : [
                'organization' => $invitation->organization->name,
                'role' => $invitation->role->label(),
                'invited_by' => $invitation->inviter?->name,
                'email' => $invitation->email,
            ],
        ])->toResponse($request);

        // The token is in the URL: never leak it through the Referer header.
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    public function accept(Request $request, string $token, AcceptInvitation $accept): RedirectResponse
    {
        $invitation = $this->find($token) ?? abort(404);

        $membership = $accept->handle($invitation, $request->user());
        $request->session()->forget('invitation_email');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Welcome to :organization!', ['organization' => $invitation->organization->name])]);

        return to_route('tenant.dashboard', ['organization' => $membership->organization]);
    }

    public function decline(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->find($token) ?? abort(404);

        abort_unless($invitation->isFor($request->user()) && ! $invitation->isAccepted(), 403);

        Audit::record('invitation.declined', $invitation->organization, $invitation, subjectLabel: $invitation->email);
        $invitation->delete();
        $request->session()->forget('invitation_email');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation declined.')]);

        return to_route('dashboard');
    }

    /**
     * Soft-deleted organizations don't load, so their invitations count as invalid.
     */
    protected function find(string $token): ?Invitation
    {
        $invitation = Invitation::findByToken($token);

        return $invitation?->organization !== null ? $invitation : null;
    }
}
