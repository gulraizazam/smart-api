<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserTypes as UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserTypes extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        User::truncate();

        $data = $this->types();
        UserType::insert($data);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function types()
    {
        return [
            [
                'name' => 'Administrator',
                'type' => 'application user',
                'account_id' => 1,
                'active' => 1,
            ],
            [
                'name' => 'Application User',
                'type' => 'application user',
                'account_id' => 1,
                'active' => 1,
            ],
            [
                'name' => 'Patient',
                'type' => 'patient',
                'account_id' => 1,
                'active' => 1,
            ],
            [
                'name' => 'Practitioner',
                'type' => 'practitioner',
                'account_id' => 1,
                'active' => 1,
            ],
        ];
    }
}
