<?php

namespace App\Console;

use App\Console\Commands\CreateAdmin;
use App\Console\Commands\DeliverNotSentAppointment;
use App\Console\Commands\DeliverOnAppointmentBook;
use App\Console\Commands\HandleHeavyLifting;
use App\Console\Commands\InactiveDiscounts;
use App\Console\Commands\InactivePackages;
use App\Console\Commands\MoveBackup;
use App\Console\Commands\MySQLDump;
use App\Console\Commands\MySQLDumpRemover;
use App\Console\Commands\SecondMessageOfAppointment;
use App\Console\Commands\SyncAppointments;
use App\Console\Commands\ThirdMessageBeforeAppointment;
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
        //CreateAdmin::class,
        SecondMessageOfAppointment::class,
        ThirdMessageBeforeAppointment::class,
        DeliverNotSentAppointment::class,
        DeliverOnAppointmentBook::class,

        /**
         * MySQL daily backup command
         */
        MySQLDump::class,
        MySQLDumpRemover::class,
        MoveBackup::class,

        /**
         * Sync Appointments into Elastic Search
         */
        SyncAppointments::class,
        HandleHeavyLifting::class,
        InactiveDiscounts::class,
        InactivePackages::class,

    ];

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $timeZone = 'Asia/Karachi';
        /*
          * 2nd message one day before appointment at 8PM
          */
        $schedule->command('appointment:2nd-message-on-appointment-day')
            ->dailyAt('19:55')->timezone($timeZone);

        /*
         * 	3rd message 2 hours before appointment
         */
        // $schedule->command('appointment:3rd-message-before-appointment')
        //     ->everyThirtyMinutes();

        /*
         * Deliver SMS which failed to sent
         */
        // $schedule->command('appointment:deliver-not-sent-sms')
        //     ->everyFifteenMinutes();

        /*
         * Deliver SMS on time of booking
         */
        $schedule->command('appointment:deliver-on-appointment-book')
            ->withoutOverlapping()
            ->everyMinute();

        /*
         * Handle heavy lifting of jobs
         */
        // $schedule->command('cms:handle-heavy-lifting')
        //     ->withoutOverlapping()
        //     ->everyMinute();

        /*
         * Run daily backup command
         */
        // $schedule->command('db:backup')
        //     ->dailyAt('23:59')->timezone($timeZone);

        /*
         * Run old daily backup remover command
         */
        // $schedule->command('db:backup-old-remove')
        //     ->dailyAt('23:55')->timezone($timeZone);

        /*
         * Inactive all the discounts which has previous day equals to the end date of the discount
         */

        $schedule->command('discounts:inactive')
            ->dailyAt('01:00')->timezone($timeZone);

        /*
         * Inactive all the bundles which has previous day equals to the end date of the bundle
         */

        $schedule->command('bundles:inactive')
            ->dailyAt('01:00')->timezone($timeZone);
        /*
         * Take backup of DATABASE and APPLICATION both
         * */

        // $schedule->command('backup:run')
        //     ->dailyAt('01:30')->timezone($timeZone);

        /*
         * Moving backup from ROLES-PERMISSION MANAGER to BACKUPS
         * */

        // $schedule->command('move:backup')
        //     ->dailyAt('02:30')->timezone($timeZone);

        /*
         * Appointment and Treatment daily sate created
         * */

        $schedule->command('appointments:daily-stats')
            ->dailyAt('23:50')->timezone($timeZone);

        /*
         * Last 3 months actvities
         * */

        // $schedule->command('recent:activities')
        //     ->weekly()->timezone($timeZone);

        /*
         * Cash Flow: Daily Digest Email (default 08:00 AM PKT, configurable via cashflow_settings)
         */
        $schedule->job(new \App\Jobs\SendCashflowDailyDigest)
            ->dailyAt('08:00')->timezone($timeZone);

        /*
         * Cash Flow: Monthly Report Email (1st of every month at 09:00 AM)
         */
        $schedule->job(new \App\Jobs\SendCashflowMonthlyReport)
            ->monthlyOn(1, '09:00')->timezone($timeZone);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
