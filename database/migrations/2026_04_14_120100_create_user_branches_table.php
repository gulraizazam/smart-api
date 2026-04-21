<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot linking users to branches (locations) for Management Dashboard scoping.
 *
 * Semantics used by ResourceScopeResolver:
 *   - user has zero rows  ⇒ company-wide access (subject to permission)
 *   - user has one+ rows  ⇒ regional access limited to those branches
 *
 * Independent from `doctor_has_locations` (which is an operational allocation
 * concept for schedulers); this pivot is purely for dashboard visibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_branches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('location_id');
            $table->timestamps();

            $table->unique(['account_id', 'user_id', 'location_id'], 'ub_account_user_location_unique');
            $table->index(['account_id', 'user_id'], 'ub_account_user');
            $table->index(['account_id', 'location_id'], 'ub_account_location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_branches');
    }
};
