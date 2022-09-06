<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Telecomprovider;

class TelecomproviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Telecomprovider::insert([
            1 => array(
                'id' => 1,
                'name' => 'Mobilink',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            2 => array(
                'id' => 2,
                'name' => 'Telenor',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            3 => array(
                'id' => 3,
                'name' => 'Ufone',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            4 => array(
                'id' => 4,
                'name' => 'Warid',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),

            5 => array(
                'id' => 5,
                'name' => 'Zong',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
            6 => array(
                'id' => 6,
                'name' => 'SCOM',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ),
        ]);
    }
}
