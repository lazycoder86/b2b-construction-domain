<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationDeletionScheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $organizationName,
        public string $purgeOn,
        public string $restoreUrl,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':organization is scheduled for deletion', ['organization' => $this->organizationName]))
            ->line(__(':organization and all of its data will be permanently deleted on :date. Members have lost access and the subscription was cancelled.', ['organization' => $this->organizationName, 'date' => $this->purgeOn]))
            ->action(__('Restore organization'), $this->restoreUrl)
            ->line(__('If this was intended, no action is needed.'));
    }
}
