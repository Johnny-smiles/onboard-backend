<?php

namespace App\Console;

use App\Jobs\ProcessCaptureReminders;
use App\Jobs\ProcessPhotoPublications;
use App\Jobs\RefreshSocialTokens;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Process capture reminders every 5 minutes
        $schedule->job(new ProcessCaptureReminders())
            ->everyFiveMinutes()
            ->name('capture-reminders:process')
            ->withoutOverlapping();

        // Process due photo publications every 5 minutes
        $schedule->job(new ProcessPhotoPublications())
            ->everyFiveMinutes()
            ->name('photo-publications:process')
            ->withoutOverlapping();

        // Refresh social media tokens every hour
        $schedule->job(new RefreshSocialTokens())
            ->hourly()
            ->name('social-tokens:refresh')
            ->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
