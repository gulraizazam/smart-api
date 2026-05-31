<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_bundle_price_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_bundle_id');
            $table->unsignedInteger('service_id');
            $table->unsignedInteger('sessions');
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->decimal('old_service_price', 11, 2);
            $table->decimal('new_service_price', 11, 2);
            $table->decimal('old_bundle_price', 11, 2);
            $table->decimal('new_bundle_price', 11, 2);
            $table->unsignedInteger('changed_by')->nullable();
            $table->unsignedInteger('account_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('service_bundle_id')->references('id')->on('service_bundles')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');

            $table->index(['service_bundle_id', 'created_at'], 'idx_sbph_bundle_created');
            $table->index(['service_id', 'created_at'], 'idx_sbph_service_created');
            $table->index(['account_id', 'created_at'], 'idx_sbph_account_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_bundle_price_history');
    }
};
