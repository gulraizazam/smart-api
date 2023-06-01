<?php

use App\Models\Patients;
use App\Models\Leads;
use App\Models\LeadsServices;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Calculation\LookupRef\Offset;

class LeadOldRecordUpdate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $old_leads = Leads::with('patient:id,name,email,phone,gender,referred_by')
            ->select('id', 'patient_id', 'service_id', 'child_service_id')
            ->groupBy('patient_id')
            ->orderBy('id', 'ASC')->get();
        foreach($old_leads as $data){
            $lead = Leads::where(['patient_id' => $data->patient_id])->update([
                'name' => isset($data->patient) ? $data->patient->name : null,
                'email' => isset($data->patient) ? $data->patient->email : null,
                'phone' => isset($data->patient) ? $data->patient->phone : null,
                'gender' => isset($data->patient) ? $data->patient->gender : null,
                'referred_by' => isset($data->patient) ? $data->patient->referred_by : null,
            ]);
            $lead_service = LeadsServices::updateOrCreate([
                'lead_id' => $data->id,
                'service_id' => $data->service_id,
                'child_service_id' => $data->child_service_id,
            ], [
                'lead_id' => $data->id,
                'service_id' => $data->service_id,
                'child_service_id' => $data->child_service_id ?? null,
                'status' => 1
            ]);
            LeadsServices::where('id', '!=', $lead_service->id)->where(['lead_id' => $data->id])->update([
                'status' => 0
            ]);
        }
    }
}
