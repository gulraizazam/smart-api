<?php

namespace App\Console\Commands;

use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecentActivities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recent:activities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'last 3 month activities';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Activity::whereDate('created_at', '<=', Carbon::now()->subMonths(3))->delete();
    }
}
