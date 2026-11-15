<?php

namespace App\Actions\Invitations;

use App\Actions\Members\Concerns\LocksOrganization;
use App\Facades\Audit;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    use LocksOrganization;

    /**
     * @throws ValidationException
     */
    public function handle(Invitation $invitation, User $user): Membership
    {
        $this->ensureCanAccept($invitation, $user);

        return DB::transaction(function () use ($invitation, $user): Membership {
            $organization = $invitation->organization;
            $this->lock($organization);

            $existing = $organization->membershipFor($user);

            if ($existing !== null) {
                $invitation->markAccepted();
                Audit::record('invitation.accepted', $organization, $invitation, ['role' => $existing->role->value, 'already_member' => true], actor: $user, subjectLabel: $invitation->email);

                return $existing;
            }

            // The pending invitation already holds a seat; only fail if members alone fill the plan.
            $limit = $organization->seatLimit();

            if ($limit !== null && $organization->memberships()->count() >= $limit) {
                throw ValidationException::withMessages(['invitation' => __('This organization has no seats left. Ask an admin to free one up.')]);
            }

            $membership = $organization->addMember($user, $invitation->role, $invitation->inviter);
            $invitation->markAccepted();
            $user->switchOrganization($organization);

            Audit::record('invitation.accepted', $organization, $invitation, ['role' => $invitation->role->value], actor: $user, subjectLabel: $invitation->email);

            return $membership;
        });
    }

    /**
     * @throws ValidationException
     */
    public function ensureCanAccept(Invitation $invitation, User $user): void
    {
        $error = match (true) {
            $invitation->isAccepted() => __('This invitation has already been used.'),
            $invitation->isExpired() => __('This invitation has expired. Ask for a new one.'),
            ! $invitation->organization->isActive() => __('This organization is not active.'),
            ! $invitation->isFor($user) => __('This invitation was sent to :email. Sign in with that address to accept it.', ['email' => $invitation->email]),
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['invitation' => $error]);
        }
    }
}
