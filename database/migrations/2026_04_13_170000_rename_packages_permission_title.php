<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('name', 'packages_manage')
            ->update(['title' => 'Bundles / Packages']);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'packages_manage')
            ->update(['title' => 'Packages']);
    }
};
