<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        if (config('app.is_demo')) {
            $schedule->command('migrate:fresh --seed')->everyFifteenMinutes();
            $schedule->command('image:seed')->everyFifteenMinutes();
        }

        if (!config('schedule_integrations.enabled', true)) {
            return;
        }

        $times = (array) config('schedule_integrations.times', ['06:00', '18:00']);
        $tz    = config('schedule_integrations.timezone') ?: config('app.timezone');
        $lock  = (int) config('schedule_integrations.overlap_lock_minutes', 360);

        foreach ($times as $time) {
            $time = trim((string) $time);
            if ($time === '') {
                continue;
            }

            $schedule->command('integrations:run-scheduled')
                ->dailyAt($time)
                ->timezone($tz)
                ->withoutOverlapping($lock)
                ->appendOutputTo(storage_path('logs/integrations-scheduled.log'));
        }
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
