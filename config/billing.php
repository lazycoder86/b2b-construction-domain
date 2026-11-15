<?php

/*
|--------------------------------------------------------------------------
| Plans & Entitlements
|--------------------------------------------------------------------------
|
| The single source of truth for what each plan includes. App code never
| checks plan names; it asks the organization's entitlements:
|
|     $organization->entitlements()->allows('csv_export');
|     $organization->entitlements()->limit('seats'); // null = unlimited
|
| `features` are on/off capabilities; `limits` are numeric caps (null = unlimited).
| Feature keys are examples for the template; rename them per product.
|
| Stripe price ids are read from the environment. Set `per_seat` on a plan to
| bill per member: the subscription quantity follows the member count.
|
*/

return [

    // Plan every organization falls back to without a trial or subscription.
    'default_plan' => 'free',

    'trial' => [
        // New organizations get this plan for this many days, no card required. 0 disables trials.
        'days' => (int) env('BILLING_TRIAL_DAYS', 14),
        'plan' => 'pro',
        // Owners are emailed this many days before the trial ends.
        'reminder_days_before' => 3,
    ],

    // Cashier subscription "type" used for the organization's plan.
    'subscription_type' => 'default',

    // Dunning: past_due subscriptions keep access while Stripe retries the payment
    // (Smart Retries, configured in the Stripe dashboard). When Stripe gives up it
    // cancels the subscription or marks it unpaid, and access falls back to the free plan.
    //
    // Stripe keys live in config/cashier.php (STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET).

    'plans' => [

        'free' => [
            'name' => 'Free',
            'description' => 'For trying things out with a small team.',
            'prices' => [],
            'features' => [],
            'limits' => [
                'seats' => 3,
            ],
        ],

        'pro' => [
            'name' => 'Pro',
            'description' => 'For growing teams.',
            'prices' => [
                // amount in the smallest currency unit (cents)
                'monthly' => ['amount' => 2900, 'stripe_price' => env('STRIPE_PRICE_PRO_MONTHLY')],
                'yearly' => ['amount' => 29000, 'stripe_price' => env('STRIPE_PRICE_PRO_YEARLY')],
            ],
            'per_seat' => false,
            'features' => ['csv_export', 'csv_import'],
            'limits' => [
                'seats' => 15,
            ],
        ],

        'business' => [
            'name' => 'Business',
            'description' => 'For organizations that need scale and integrations.',
            'prices' => [
                'monthly' => ['amount' => 9900, 'stripe_price' => env('STRIPE_PRICE_BUSINESS_MONTHLY')],
                'yearly' => ['amount' => 99000, 'stripe_price' => env('STRIPE_PRICE_BUSINESS_YEARLY')],
            ],
            'per_seat' => false,
            'features' => ['csv_export', 'csv_import', 'api_access', 'webhooks'],
            'limits' => [
                'seats' => null,
            ],
        ],

    ],

];
