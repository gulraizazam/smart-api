<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the first post-crm2-cutoff cleanup: nine empty, unreferenced, FK-free
 * legacy tables were dropped by migrations 2026_04_08_100029_drop_unused_tables
 * and 2026_04_08_100030_drop_dead_tables_batch2.
 *
 * Goes red if either drop migration is reverted (the schema dump still creates
 * these tables, so they would reappear after a fresh migrate). Each table was
 * verified on prod before dropping: EXACT COUNT(*) = 0, zero incoming foreign
 * keys, zero live code references, and no later migration touches them.
 */
class DeadTablesDroppedTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const DROPPED_TABLES = [
        'account_subscriptions',
        'announcements',
        'notifications',
        'public_holidays',
        'rooms',
        'taxes',
        'purchases',
        'purchase_details',
        'refunds',
    ];

    public function test_dead_legacy_tables_are_dropped(): void
    {
        foreach (self::DROPPED_TABLES as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "`{$table}` should be dropped by the post-crm2-cutoff cleanup but still exists.",
            );
        }
    }
}
