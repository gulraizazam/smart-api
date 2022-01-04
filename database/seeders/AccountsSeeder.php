<?php

namespace Database\Seeders;

use App\Models\Accounts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        Accounts::truncate();
        if (!Accounts::where('email', 'care@cutera.pk')->exists()) {
            Accounts::create([
                'name' => 'Cutera Aesthetics',
                'email' => 'care@cutera.pk',
                'contact' => '03111113355',
                'resource_person' => 'Cutera Life',
                'suspended' => '0',
            ]);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
