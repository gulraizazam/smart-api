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
        if (! Accounts::where('email', 'care@smartaesthetics.pk')->exists()) {
            Accounts::create([
                'name' => 'Smart Aesthetics',
                'email' => 'care@smartaesthetics.pk',
                'contact' => '03403402222',
                'resource_person' => 'Smart Life',
                'suspended' => '0',
            ]);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
