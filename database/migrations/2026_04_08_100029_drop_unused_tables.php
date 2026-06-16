<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop unused tables that have 0 rows and no application code.
     *
     * account_subscriptions — subscription feature never implemented
     * announcements        — announcement feature never implemented
     * notifications        — generic notifications table, unused (app uses cashflow_notifications)
     * public_holidays      — holiday feature never implemented
     */
    public function up(): void
    {
        // Drop account_subscriptions (has FKs to accounts and plans — drop constraints first)
        Schema::dropIfExists('account_subscriptions');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('public_holidays');
    }

    public function down(): void
    {
        // Tables can be recreated from the original migrations if needed:
        // 2021_12_30_124857_create_account_subscriptions_table.php
        // 2021_12_30_124756_create_announcements_table.php
        // 2021_12_30_125754_create_notifications_table.php
        // 2021_12_30_131317_create_public_holidays_table.php
    }
};
