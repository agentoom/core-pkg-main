<?php

namespace Agentoom\Core\Console\Commands;

use Agentoom\Core\Jobs\RunScheduleJob;
use Agentoom\Core\Models\Schedule;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchSchedules extends Command
{
    protected $signature = 'schedules:dispatch {--now : Run all active schedules immediately regardless of their cron expression} {--sync : Run schedules synchronously (not queued)}';

    protected $description = 'Check active schedules and dispatch any that are due';

    public function handle(): void
    {
        $now = now();
        $isNow = $this->option('now');
        $isSync = $this->option('sync');
        $dispatched = 0;

        Log::debug('DispatchSchedules: starting check. (Force: '.($isNow ? 'yes' : 'no').')');

        Schedule::query()
            ->where('active', true)
            ->each(function (Schedule $schedule) use ($now, $isNow, $isSync, &$dispatched): void {
                $localNow = $now->copy()->setTimezone($schedule->timezone ?? 'UTC');

                // Skip if already dispatched this minute
                if (! $isNow && $schedule->last_execution && $schedule->last_execution->isSameMinute($localNow)) {
                    Log::debug("Schedule #{$schedule->id} already dispatched this minute. Skipping.");

                    return;
                }

                // Skip if schedule is paused (awaiting security approval)
                if (! $isNow) {
                    $latestLog = $schedule->logs()->latest()->first();
                    if ($latestLog && $latestLog->status === 'paused') {
                        Log::debug("Schedule #{$schedule->id} is currently paused (awaiting security approval). Skipping.");

                        return;
                    }
                }

                $shouldDispatch = $isNow;

                if (! $shouldDispatch) {
                    $cron = new CronExpression($schedule->toCronExpression());
                    $shouldDispatch = $cron->isDue($localNow->toDateTimeString());
                }

                if ($shouldDispatch) {
                    $this->info("Dispatched schedule #{$schedule->id} \"{$schedule->name}\" — \"{$schedule->toCronExpression()}\" ({$schedule->timezone})");
                    Log::info("Dispatching schedule #{$schedule->id} \"{$schedule->name}\" (".($isSync ? 'sync' : 'queued').')');

                    if ($isSync) {
                        dispatch_sync(new RunScheduleJob($schedule->id));
                    } else {
                        RunScheduleJob::dispatch($schedule->id);
                    }

                    // If not running synchronously, we should still update something to prevent immediate re-dispatch
                    if (! $isSync) {
                        $schedule->update(['last_execution' => now()]);
                    }

                    $dispatched++;
                }
            });

        $this->info("Done. {$dispatched} schedule(s) dispatched.");

        if (! $isSync && config('queue.default') !== 'sync' && $dispatched > 0) {
            $this->warn("Note: Jobs have been queued. Ensure you have a queue worker running ('sail artisan queue:work').");
        }
    }
}
