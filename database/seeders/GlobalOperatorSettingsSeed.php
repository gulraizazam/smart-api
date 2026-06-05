<?php

use Illuminate\Database\Seeder;

class GlobalOperatorSettingsSeed extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\GlobalOperatorSettings::insert([
            1 => [
                'id' => 1,
                'operator_name' => 'Telenor Corporate SMS',
                'username' => config('services.sms.telenor.username', ''),
                'password' => config('services.sms.telenor.password', ''),
                'mask' => '',
                'test_mode' => '',
                'url' => 'https://telenorcsms.com.pk:27677',
                'string_1' => 'N/A',
                'string_2' => 'N/A',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            2 => [
                'id' => 2,
                'operator_name' => 'Jazz Corporate SMS',
                'username' => config('services.sms.jazz.username', ''),
                'password' => config('services.sms.jazz.password', ''),
                'mask' => '',
                'test_mode' => '',
                'url' => 'https://enterprise.jazzcmt.com',
                'string_1' => 'N/A',
                'string_2' => 'N/A',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
        ]);

    }
}
