<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSystemTargetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('system_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->string('target_key', 50)->index();
            $table->decimal('target_value', 12, 2)->default(0);
            $table->string('label')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'target_key'], 'system_targets_unique');

            $table->foreign('account_id', 'system_targets_account')
                ->references('id')->on('accounts');
        });

        // Seed default system-wide targets
        \Illuminate\Support\Facades\DB::table('system_targets')->insert([
            [
                'account_id' => 1,
                'target_key' => 'conversion_pct',
                'target_value' => 50.00,
                'label' => 'Conversion Rate (%)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => 1,
                'target_key' => 'avg_conversion_revenue',
                'target_value' => 15000.00,
                'label' => 'Avg Conversion Revenue',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => 1,
                'target_key' => 'feedback_score',
                'target_value' => 9.50,
                'label' => 'Feedback Score',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_id' => 1,
                'target_key' => 'upselling_target',
                'target_value' => 0.00,
                'label' => 'Upselling Target',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('system_targets');
    }
}
