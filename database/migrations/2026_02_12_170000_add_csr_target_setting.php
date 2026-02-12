<?php

use Illuminate\Database\Migrations\Migration;
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
        // Get all account IDs
        $accounts = DB::table('accounts')->pluck('id');

        foreach ($accounts as $accountId) {
            // Check if setting already exists
            $exists = DB::table('settings')
                ->where('slug', 'sys-csr-target')
                ->where('account_id', $accountId)
                ->exists();

            if (!$exists) {
                DB::table('settings')->insert([
                    'name' => 'CSR Daily Target',
                    'slug' => 'sys-csr-target',
                    'data' => '10',
                    'account_id' => $accountId,
                    'active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('settings')->where('slug', 'sys-csr-target')->delete();
    }
};
