<?php

use Illuminate\Database\Seeder;
use App\Models\SMSTemplates;
use Illuminate\Support\Facades\Config;

class TreatmentSMSTemplatesSeed extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        SMSTemplates::insert(Config::get('organization_setup_data.treatment_sms_templates'));

    }
}
