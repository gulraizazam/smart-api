<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResourceTypes extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        \App\Models\ResourceTypes::truncate();

        \App\Models\ResourceTypes::insert([
            [
                'name' => 'Machine',
                'slug' => 'Machine',
                'active' => 1,
            ],
        ]);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
