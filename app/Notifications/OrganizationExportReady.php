<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $organizationName, public string $downloadUrl) {}

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
            ->subject(__('Your :organization data export is ready', ['organization' => $this->organizationName]))
            ->line(__('The export you requested is ready to download.'))
            ->action(__('Download export'), $this->downloadUrl)
            ->line(__('The link works for 24 hours and only for owners of :organization.', ['organization' => $this->organizationName]));
    }
}
