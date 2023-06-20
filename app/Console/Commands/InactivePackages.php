<?php

namespace App\Console\Commands;

use App\Models\Bundles;
use Carbon\Carbon;
use Illuminate\Console\Command;

class InactivePackages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bundles:inactive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inactive Bundles at every night 1 AM';

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
     * @return mixed
     */
    public function handle()
    {
        try {
            $today = Carbon::now()->subDay(1)->toDateString();

            if (Bundles::whereDate('end', '<=', $today)->count()) {
                Bundles::whereDate('end', '<=', $today)->update(['active' => 0]);
            }

            return true;

        } catch (\Exception $exception) {
            return $exception->getMessage().'------'.$exception->getFile();
        }

    }
}
