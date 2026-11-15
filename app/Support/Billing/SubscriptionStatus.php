<?php

namespace App\Support\Billing;

enum SubscriptionStatus: string
{
    case Active = 'active';
    // Payment failed; Stripe is retrying. Keep access during the dunning period.
    case PastDue = 'past_due';
    // Cancelled but paid until the end of the period.
    case Cancelling = 'cancelling';
}
