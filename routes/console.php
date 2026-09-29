<?php

use Agentoom\Core\Jobs\SyncAiPricingJob;
use Agentoom\Core\Jobs\SyncProviderModelsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('schedules:dispatch')->everyMinute()->withoutOverlapping(3);
Schedule::command('ai:aggregate-metrics')->hourly();
Schedule::command('agentoom:resume-waits')->everyMinute()->withoutOverlapping(3);
Schedule::command('agentoom:analyze-memory')->everyMinute()->withoutOverlapping(3);
Schedule::command('agentoom:discover-tools')->hourly();

Schedule::job(new SyncProviderModelsJob)->everyTwoHours()->withoutOverlapping(30)->onOneServer();
Schedule::job(new SyncAiPricingJob)->everyTwoHours()->withoutOverlapping(30)->onOneServer();
