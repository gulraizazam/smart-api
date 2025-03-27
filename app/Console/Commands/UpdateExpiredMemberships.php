<?php

namespace App\Console\Commands;

use App\Models\Membership;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class UpdateExpiredMemberships extends Command
{
    protected $signature = 'memberships:expire';
    protected $description = 'Update expired memberships and set status to 0';

    public function handle()
    {
        $count = Membership::where('end_date', '<', Carbon::today())
            ->where('active', 1)
            ->update(['active' => 0]);

        $this->info("$count memberships updated successfully.");
    }
}
