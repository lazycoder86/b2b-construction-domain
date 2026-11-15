<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:send-trial-reminders')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('organizations:purge')->dailyAt('03:00')->withoutOverlapping();
// Audit log retention (AuditLog::prunable(), config/audit.php).
Schedule::command('model:prune')->dailyAt('04:00');
