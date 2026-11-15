<?php

namespace App\Actions\Invitations;

use App\Actions\Members\Concerns\LocksOrganization;
use App\Enums\OrganizationRole;
use App\Facades\Audit;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OrganizationInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteMember
{
    use LocksOrganization;

    /**
     * Authorization (InvitationPolicy::invite) is the caller's job.
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization, User $inviter, string $email, OrganizationRole $role): Invitation
    {
        $email = Str::lower(trim($email));

        return DB::transaction(function () use ($organization, $inviter, $email, $role): Invitation {
            $this->lock($organization);

            if ($organization->members()->whereRaw('lower(users.email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages(['email' => __('This person is already a member.')]);
            }

            $open = $organization->invitations()->whereNull('accepted_at')->whereRaw('lower(email) = ?', [$email])->first();

            if ($open !== null && ! $open->isExpired()) {
                throw ValidationException::withMessages(['email' => __('This person has already been invited. Resend the invitation instead.')]);
            }

            // An expired invitation is replaced by the new one.
            $open?->delete();

            if (! $organization->hasAvailableSeat()) {
                throw ValidationException::withMessages(['email' => __('Your organization has no seats left.')]);
            }

            $invitation = new Invitation(['email' => $email, 'role' => $role, 'invited_by' => $inviter->id]);
            $invitation->organization_id = $organization->id;
            $token = $invitation->regenerateToken();
            $invitation->save();

            $invitation->setRelation('organization', $organization)->setRelation('inviter', $inviter);

            Audit::record('member.invited', $organization, $invitation, ['role' => $role->value], actor: $inviter, subjectLabel: $email);
            Notification::route('mail', $email)->notify(OrganizationInvitation::for($invitation, $token));

            return $invitation;
        });
    }
}
