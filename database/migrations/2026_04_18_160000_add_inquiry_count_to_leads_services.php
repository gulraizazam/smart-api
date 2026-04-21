<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `inquiry_count` to `leads_services`: how many times the same client
 * has re-inquired about this (category, service) pair.
 *
 * Paired with LeadService::createLeadService() which now upserts on the
 * unique (lead_id, service_id, child_service_id) tuple and increments
 * this counter instead of appending a duplicate row. Restores the
 * engagement signal that the row-level dedupe migration collapsed, while
 * keeping the table normalised.
 *
 * Unsigned small-int — one client will never exceed 65k inquiries; using
 * smallint instead of int saves 2 bytes per row across ~588k rows.
 *
 * Existing rows default to 1 (each surviving row represents at least
 * one real inquiry). Historical duplicate counts cannot be recovered —
 * they were collapsed by the prior dedupe migration — but going-forward
 * counts will accrue correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leads_services')) {
            return;
        }

        if (Schema::hasColumn('leads_services', 'inquiry_count')) {
            return;
        }

        Schema::table('leads_services', function (Blueprint $table): void {
            $table->unsignedSmallInteger('inquiry_count')
                ->default(1)
                ->after('status')
                ->comment('Times the client re-inquired this (category, service) pair');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('leads_services')) {
            return;
        }

        if (! Schema::hasColumn('leads_services', 'inquiry_count')) {
            return;
        }

        Schema::table('leads_services', function (Blueprint $table): void {
            $table->dropColumn('inquiry_count');
        });
    }
};
