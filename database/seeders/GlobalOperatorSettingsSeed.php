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
            1 => array(
                'id' => 1,
                'operator_name'=> 'Telenor Corporate SMS',
                'username'=> '923458232889',
                'password'=> '3646',
                'mask'=> '',
                'test_mode'=> '',
                'url'=> 'https://telenorcsms.com.pk:27677',
                'string_1'=> 'N/A',
                'string_2'=> 'N/A',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            2 => array(
                'id' => 2,
                'operator_name'=> 'Jazz Corporate SMS',
                'username'=> '03011156718',
                'password'=> 'iMtWluiQ',
                'mask'=> '',
                'test_mode'=> '',
                'url'=> 'https://enterprise.jazzcmt.com',
                'string_1'=> 'N/A',
                'string_2'=> 'N/A',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
        ]);

    }
}
