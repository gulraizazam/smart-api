<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Telecomprovidernumber;

class TelecomprovidernumberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Telecomprovidernumber::insert([
            1 => array(
                'id' => 1,
                'pre_fix' => '0300',
                'telecomprovider_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            2 => array(
                'id' => 2,
                'pre_fix' => '0301',
                'telecomprovider_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            3 => array(
                'id' => 3,
                'pre_fix' => '0302',
                'telecomprovider_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            4 => array(
                'id' => 4,
                'pre_fix' => '0303',
                'telecomprovider_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            5 => array(
                'id' => 5,
                'pre_fix' => '0304',
                'telecomprovider_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            6 => array(
                'id' => 6,
                'pre_fix' => '0305',
                'telecomprovider_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            7 => array(
                'id' => 7,
                'pre_fix' => '0306',
                'telecomprovider_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            8 => array(
                'id' => 8,
                'pre_fix' => '0307',
                'telecomprovider_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            9 => array(
                'id' => 9,
                'pre_fix' => '0308',
                'telecomprovider_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            10 => array(
                'id' => 10,
                'pre_fix' => '0309',
                'telecomprovider_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            11 => array(
                'id' => 11,
                'pre_fix' => '3000',
                'telecomprovider_id' => '1',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            12 => array(
                'id' => 12,
                'pre_fix' => '0340',
                'telecomprovider_id' => '2',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            13 => array(
                'id' => 13,
                'pre_fix' => '0341',
                'telecomprovider_id' => '2',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            14 => array(
                'id' => 14,
                'pre_fix' => '0342',
                'telecomprovider_id' => '2',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            15 => array(
                'id' => 15,
                'pre_fix' => '0343',
                'telecomprovider_id' => '2',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            16 => array(
                'id' => 16,
                'pre_fix' => '0344',
                'telecomprovider_id' => '2',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            17 => array(
                'id' => 17,
                'pre_fix' => '0345',
                'telecomprovider_id' => '2',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            18 => array(
                'id' => 18,
                'pre_fix' => '0346',
                'telecomprovider_id' => '2',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            19 => array(
                'id' => 19,
                'pre_fix' => '0347',
                'telecomprovider_id' => '2',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            20 => array(
                'id' => 20,
                'pre_fix' => '0330',
                'telecomprovider_id' => '3',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            21 => array(
                'id' => 21,
                'pre_fix' => '0331',
                'telecomprovider_id' => '3',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            22 => array(
                'id' => 22,
                'pre_fix' => '0332',
                'telecomprovider_id' => '3',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            23 => array(
                'id' => 23,
                'pre_fix' => '0333',
                'telecomprovider_id' => '3',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            24 => array(
                'id' => 24,
                'pre_fix' => '0334',
                'telecomprovider_id' => '3',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            25 => array(
                'id' => 25,
                'pre_fix' => '0335',
                'telecomprovider_id' => '3',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            26 => array(
                'id' => 26,
                'pre_fix' => '0336',
                'telecomprovider_id' => '3',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            27 => array(
                'id' => 27,
                'pre_fix' => '0337',
                'telecomprovider_id' => '3',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            28 => array(
                'id' => 28,
                'pre_fix' => '0320',
                'telecomprovider_id' => '4',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            29 => array(
                'id' => 29,
                'pre_fix' => '0321',
                'telecomprovider_id' => '4',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            30 => array(
                'id' => 30,
                'pre_fix' => '0322',
                'telecomprovider_id' => '4',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            31 => array(
                'id' => 31,
                'pre_fix' => '0323',
                'telecomprovider_id' => '4',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            32 => array(
                'id' => 32,
                'pre_fix' => '0324',
                'telecomprovider_id' => '4',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            33 => array(
                'id' => 33,
                'pre_fix' => '0325',
                'telecomprovider_id' => '4',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            34 => array(
                'id' => 34,
                'pre_fix' => '0311',
                'telecomprovider_id' => '5',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),

            35 => array(
                'id' => 35,
                'pre_fix' => '0312',
                'telecomprovider_id' => '5',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            36 => array(
                'id' => 36,
                'pre_fix' => '0313',
                'telecomprovider_id' => '5',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            37 => array(
                'id' => 37,
                'pre_fix' => '0314',
                'telecomprovider_id' => '5',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            38 => array(
                'id' => 38,
                'pre_fix' => '0315',
                'telecomprovider_id' => '5',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            39 => array(
                'id' => 39,
                'pre_fix' => '0355',
                'telecomprovider_id' => '6',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
        ]);
    }
}
