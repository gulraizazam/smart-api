<?php

use App\Models\Leads;
use App\Models\LeadsServices;
use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

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

        $output = new ConsoleOutput();
        $progress = new ProgressBar($output, count($old_leads));
        $progress->start();

        foreach ($old_leads as $data) {
            Leads::where(['patient_id' => $data->patient_id])->update([
                'name' => isset($data->patient) ? $data->patient->name : null,
                'email' => isset($data->patient) ? $data->patient->email : null,
                'phone' => isset($data->patient) ? $data->patient->phone : null,
                'gender' => isset($data->patient) ? $data->patient->gender : null,
                'referred_by' => isset($data->patient) ? $data->patient->referred_by : null,
            ]);
            $leads = Leads::where(['patient_id' => $data->patient_id])->get();
            foreach ($leads as $lead) {
                if ($lead->service_id != null) {
                    $lead_service = LeadsServices::updateOrCreate([
                        'lead_id' => $lead->id,
                        'service_id' => $lead->service_id,
                        'child_service_id' => $lead->child_service_id,
                    ], [
                        'lead_id' => $lead->id,
                        'service_id' => $lead->service_id,
                        'child_service_id' => ($lead->child_service_id != 0) ? $lead->child_service_id : null,
                        'status' => 1,
                    ]);
                }
                LeadsServices::where('id', '!=', $lead_service->id)->where(['lead_id' => $lead->id])->update([
                    'status' => 0,
                ]);
                $progress->advance();
            }
        }
        $progress->finish();
    }
}
