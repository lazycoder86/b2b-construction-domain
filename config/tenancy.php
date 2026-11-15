<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    |
    | Number of days an invitation link stays valid. Resending an invitation
    | issues a new link and restarts the clock.
    |
    */

    'invitation_expiry_days' => (int) env('INVITATION_EXPIRY_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Deletion & Exports
    |--------------------------------------------------------------------------
    |
    | Owners can schedule an organization for deletion; it can be restored for
    | this many days, then `organizations:purge` (daily) deletes it for good,
    | including soft-deleted organizations older than the same window.
    | Data export archives are kept for `export_retention_days`.
    |
    */

    'deletion_grace_days' => (int) env('ORGANIZATION_DELETION_GRACE_DAYS', 30),

    'export_retention_days' => (int) env('ORGANIZATION_EXPORT_RETENTION_DAYS', 7),

];
