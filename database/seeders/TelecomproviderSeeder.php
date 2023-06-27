<?php

use App\Models\Telecomprovider;
use Illuminate\Database\Seeder;

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
            1 => [
                'id' => 1,
                'name' => 'Mobilink',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            2 => [
                'id' => 2,
                'name' => 'Telenor',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            3 => [
                'id' => 3,
                'name' => 'Ufone',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            4 => [
                'id' => 4,
                'name' => 'Warid',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],

            5 => [
                'id' => 5,
                'name' => 'Zong',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            6 => [
                'id' => 6,
                'name' => 'SCOM',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
        ]);
    }
}
