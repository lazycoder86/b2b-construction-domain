<?php

return [

    // Platform admins must have TOTP or a passkey to use /admin. Only disable locally.
    'require_mfa' => (bool) env('ADMIN_REQUIRE_MFA', true),

    // Impersonation sessions end automatically after this many minutes.
    'impersonation_minutes' => (int) env('ADMIN_IMPERSONATION_MINUTES', 30),

];
