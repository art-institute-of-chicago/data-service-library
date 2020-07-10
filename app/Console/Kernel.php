<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\LibraryDownload::class,
        Commands\LibraryImport::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

        $schedule->command('library:import --quiet')
            ->monthlyOn(1, '01:' .(config('app.env') == 'production' ? '00' : '15'))
            ->before(function () {
                Artisan::call('library:download', ['--quiet' => 'default']);
            })
            ->sendOutputTo(storage_path('logs/import-last-run.log'));

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        // $this->load(__DIR__.'/Commands');
    }
}
