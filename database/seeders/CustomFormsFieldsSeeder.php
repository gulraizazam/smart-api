<?php

use App\Models\CustomFormFields;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomFormsFieldsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Eloquent::unguard();
        $sql = base_path('database/seeds/custom_form_fields.sql');
        DB::unprepared(file_get_contents($sql));
    }
}
