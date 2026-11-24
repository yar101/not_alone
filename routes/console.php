<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('content-packs:prune-rejected')->daily();
Schedule::command('orders:release-idol-holds')->everyMinute()->withoutOverlapping();
Schedule::command('orders:auto-complete')->hourly()->withoutOverlapping();

// Mandarin BaaS Automated Tasks
Schedule::command('orders:schedule-weekly-payouts')->weeklyOn(1, '06:00')->withoutOverlapping();
Schedule::command('payouts:reconcile')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('self-employed:monitor-receipts')->weekly()->withoutOverlapping();
Schedule::command('agent-reports:generate')->monthlyOn(1, '01:00')->withoutOverlapping();
