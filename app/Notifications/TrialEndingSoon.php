<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEndingSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $organizationName,
        public string $planName,
        public string $endsOn,
        public string $billingUrl,
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
            ->subject(__('Your :plan trial for :organization ends soon', ['plan' => $this->planName, 'organization' => $this->organizationName]))
            ->line(__('The :plan trial for :organization ends on :date.', ['plan' => $this->planName, 'organization' => $this->organizationName, 'date' => $this->endsOn]))
            ->line(__('Choose a plan to keep your :plan features. Otherwise the organization moves to the free plan.', ['plan' => $this->planName]))
            ->action(__('Choose a plan'), $this->billingUrl);
    }
}
