<?php

use Illuminate\Database\Seeder;
use App\Models\SMSTemplates;
use Illuminate\Support\Facades\Config;

class PlanSMSTemplatesSeed extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        SMSTemplates::insert(Config::get('organization_setup_data.plan_sms_templates'));
    }
}
