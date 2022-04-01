<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\InvoiceStatuses;
use Illuminate\Support\Facades\Config;

class InvoiceStatusesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        InvoiceStatuses::insert(Config::get('organization_setup_data.invoice_statuses'));
    }
}
