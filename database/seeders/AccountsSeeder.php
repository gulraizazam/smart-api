<?php

namespace Database\Seeders;

use App\Models\Accounts;
use Illuminate\Database\Seeder;

class AccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Accounts::create([
            'name' => 'Cutera Aesthetics',
            'email' => 'care@cutera.pk',
            'contact' => '03111113355',
            'resource_person' => 'Cutera Life',
            'suspended' => '0',
        ]);
    }
}
