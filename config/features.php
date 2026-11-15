<?php

/*
|--------------------------------------------------------------------------
| Feature Flags (Laravel Pennant)
|--------------------------------------------------------------------------
|
| Flags are for rolling out changes safely, not for plan entitlements (see
| config/billing.php for those). They are scoped to the organization:
|
|     Feature::active('beta_dashboard');                 // current organization
|     Feature::for($organization)->active('beta_dashboard');
|
| `default` applies until a platform admin turns a flag on/off for an
| organization in /admin. Active flags are shared with the frontend as
| `currentOrganization.flags`.
|
*/

return [

    'flags' => [
        'beta_dashboard' => [
            'description' => 'Example flag: preview the redesigned dashboard.',
            'default' => false,
        ],
    ],

];
