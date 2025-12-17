<?php

namespace Database\Seeders;

use App\Models\SMSTemplates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConsultancyWhatsAppTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get all accounts
        $accounts = DB::table('accounts')->get();

        foreach ($accounts as $account) {
            // Check if template already exists for this account
            $existingTemplate = SMSTemplates::where([
                'slug' => 'consultancy_whatsapp',
                'account_id' => $account->id
            ])->first();

            if (!$existingTemplate) {
                SMSTemplates::create([
                    'name' => 'Consultancy WhatsApp Message',
                    'content' => 'Dear #patient_name#, your consultation at Cutera is scheduled for #appointment_time#, today.

Location: ##centre_google_map## . We look forward to seeing you on time.

For any assistance, please communicate here.',
                    'slug' => 'consultancy_whatsapp',
                    'active' => 1,
                    'account_id' => $account->id,
                    'created_at' => \Carbon\Carbon::now(),
                    'updated_at' => \Carbon\Carbon::now(),
                ]);
            }
        }
    }
}
