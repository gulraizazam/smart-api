<?php

namespace Database\Seeders;

use App\Models\AuditTrailActions;
use Illuminate\Database\Seeder;

class AuditTrailActionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $actions = $this->actions();
        foreach ($actions as $action) {
            if (! AuditTrailActions::where($action)->exists()) {
                AuditTrailActions::create($action);
            }
        }
    }

    private function actions()
    {
        return [
            [
                'name' => 'create',
            ],
            [
                'name' => 'edit',
            ],
            [
                'name' => 'delete',
            ],
            [
                'name' => 'inactive',
            ],
            [
                'name' => 'active',
            ],
            [
                'name' => 'cancel',
            ],
        ];
    }
}
