<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Notifications\OrganizationInvitation;
use Illuminate\Support\Facades\Notification;

class ResendInvitation
{
    /**
     * Issue a new link (the old one stops working), restart the expiry and email it again.
     */
    public function handle(Invitation $invitation): void
    {
        $token = $invitation->regenerateToken();
        $invitation->save();

        Notification::route('mail', $invitation->email)->notify(OrganizationInvitation::for($invitation, $token));
    }
}
