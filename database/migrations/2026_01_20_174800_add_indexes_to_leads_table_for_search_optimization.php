<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            // Add composite index for active + account_id (most common filter)
            $table->index(['active', 'account_id'], 'idx_leads_active_account');
            
            // Add index for phone searches (prefix matching)
            $table->index('phone', 'idx_leads_phone');
            
            // Add index for name searches (prefix matching)
            $table->index('name', 'idx_leads_name');
            
            // Add composite index for phone + active + account_id (optimized phone search)
            $table->index(['phone', 'active', 'account_id'], 'idx_leads_phone_active_account');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('idx_leads_active_account');
            $table->dropIndex('idx_leads_phone');
            $table->dropIndex('idx_leads_name');
            $table->dropIndex('idx_leads_phone_active_account');
        });
    }
};
