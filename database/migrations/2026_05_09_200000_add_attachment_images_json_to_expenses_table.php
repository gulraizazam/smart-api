<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->json('attachment_images')->nullable()->after('attachment_image');
        });

        DB::table('expenses')
            ->whereNotNull('attachment_image')
            ->where('attachment_image', '!=', '')
            ->orderBy('id')
            ->lazyById()
            ->each(function ($row) {
                DB::table('expenses')->where('id', $row->id)->update([
                    'attachment_images' => json_encode([$row->attachment_image]),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('attachment_images');
        });
    }
};
