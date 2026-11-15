<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $organizationName,
        public string $billingUrl,
        public ?string $amountDue = null,
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
            ->error()
            ->subject(__('Payment failed for :organization', ['organization' => $this->organizationName]))
            ->line($this->amountDue !== null
                ? __('We could not collect the payment of :amount for :organization.', ['amount' => $this->amountDue, 'organization' => $this->organizationName])
                : __('We could not collect the latest payment for :organization.', ['organization' => $this->organizationName]))
            ->line(__('We will retry automatically. To avoid losing access to paid features, please update your payment method.'))
            ->action(__('Update payment method'), $this->billingUrl);
    }
}
