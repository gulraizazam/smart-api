<?php

namespace App\Console\Commands;

use App\Models\Bundles;
use App\Models\Discounts;
use Illuminate\Console\Command;

class CheckExpired extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set status to inactive when end date passed';
    /**
     * User model.
     *
     * @var object
     */

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
     * @return void
     */
    public function handle()
    {
        Discounts::whereDate('end', '<', now())->whereActive('1')->update([
            'active' => 0,
        ]);
        Bundles::whereDate('end', '<', now())->whereActive('1')->update([
            'active' => 0,
        ]);
    }
}
