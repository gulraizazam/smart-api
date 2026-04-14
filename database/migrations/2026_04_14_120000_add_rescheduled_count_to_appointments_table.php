<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            if (! Schema::hasColumn('appointments', 'rescheduled_count')) {
                $table->unsignedInteger('rescheduled_count')->default(0)->after('first_scheduled_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            if (Schema::hasColumn('appointments', 'rescheduled_count')) {
                $table->dropColumn('rescheduled_count');
            }
        });
    }
};
