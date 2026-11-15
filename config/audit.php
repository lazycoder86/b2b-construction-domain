<?php

return [

    // Audit entries older than this are pruned by `php artisan model:prune` (scheduled daily).
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 365),

];
