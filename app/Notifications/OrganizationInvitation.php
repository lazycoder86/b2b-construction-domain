<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Plain values only: the token is not stored, so it can't be re-read
     * from the model once the job is serialized.
     */
    public function __construct(
        public string $organizationName,
        public ?string $inviterName,
        public string $roleLabel,
        public string $acceptUrl,
        public string $expiresAt,
    ) {
        $this->afterCommit();
    }

    public static function for(Invitation $invitation, string $plainToken): self
    {
        return new self(
            organizationName: $invitation->organization->name,
            inviterName: $invitation->inviter?->name,
            roleLabel: $invitation->role->label(),
            acceptUrl: route('invitations.show', $plainToken),
            expiresAt: $invitation->expires_at->toFormattedDayDateString(),
        );
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $intro = $this->inviterName !== null
            ? __(':inviter has invited you to join :organization as :role.', ['inviter' => $this->inviterName, 'organization' => $this->organizationName, 'role' => $this->roleLabel])
            : __('You have been invited to join :organization as :role.', ['organization' => $this->organizationName, 'role' => $this->roleLabel]);

        return (new MailMessage)
            ->subject(__('You are invited to join :organization', ['organization' => $this->organizationName]))
            ->line($intro)
            ->action(__('Accept invitation'), $this->acceptUrl)
            ->line(__('This invitation expires on :date.', ['date' => $this->expiresAt]))
            ->line(__('If you were not expecting this invitation, you can ignore this email.'));
    }
}
