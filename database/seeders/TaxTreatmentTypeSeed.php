<?php

use App\Models\Bundles;
use App\Models\TaxTreatmentType;
use Illuminate\Database\Seeder;

class TaxTreatmentTypeSeed extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TaxTreatmentType::insert([
            1 => [
                'id' => 1,
                'name' => 'Both',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            2 => [
                'id' => 2,
                'name' => 'Is exclusive',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
            3 => [
                'id' => 3,
                'name' => 'Is Inclusive',
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ],
        ]);

        /*Service information updated according to treatment tax type, default set as both*/

        $services_info = \App\Models\Services::get();

        foreach ($services_info as $service) {

            $service->update(['tax_treatment_type_id' => 1]);

            $bundleWithService = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
                ->where([
                    'bundles.account_id' => 1,
                    'bundles.type' => 'single',
                    'bundle_has_services.service_id' => $service->id,
                ])->first();

            Bundles::where([
                'id' => $bundleWithService->id,
            ])->update([
                'tax_treatment_type_id' => 1,
            ]);
        }
        Bundles::where([
            'type' => 'multiple',
        ])->update([
            'tax_treatment_type_id' => 1,
        ]);
    }
}
