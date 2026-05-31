<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Update all existing consultancy_whatsapp templates with the new content
        DB::table('sms_templates')
            ->where('slug', 'consultancy_whatsapp')
            ->update([
                'content' => 'Dear #patient_name#, your consultation at Cutera is scheduled for #appointment_time#, today.

Location: ##centre_google_map## . We look forward to seeing you on time.

For any assistance, please communicate here.',
                'updated_at' => now()
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert to old template content
        DB::table('sms_templates')
            ->where('slug', 'consultancy_whatsapp')
            ->update([
                'content' => 'Dear #patient_name#, your consultation at Cutera is scheduled for #appointment_time#, today. We look forward to seeing you on time.

For any assistance, please communicate here.',
                'updated_at' => now()
            ]);
    }
};
