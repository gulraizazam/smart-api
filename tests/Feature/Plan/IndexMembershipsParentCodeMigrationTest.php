<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins 2026_06_04_130000_index_memberships_parent_code.
 *
 * Asserts the composite index (parent_membership_code, is_referral) is
 * created, the run is idempotent, and down() drops it.
 *
 * NOTE: index DDL implicit-commits, so per-test transactional isolation
 * does not apply — setUp scrubs to a known "absent" state and tearDown
 * restores the migrated state so the shared schema is left as the
 * migrations define it.
 */
class IndexMembershipsParentCodeMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const INDEX = 'idx_memberships_parent_referral';

    private function indexExists(): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            ['memberships', self::INDEX]
        );

        return ! empty($rows);
    }

    private function dropIndex(): void
    {
        try {
            DB::statement('ALTER TABLE memberships DROP INDEX '.self::INDEX);
        } catch (\Throwable $e) {
            // already absent
        }
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_06_04_130000_index_memberships_parent_code.php');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropIndex();
    }

    protected function tearDown(): void
    {
        // Leave the schema as the migrations define it (index present).
        $this->migration()->up();
        parent::tearDown();
    }

    public function test_index_is_added_and_run_is_idempotent(): void
    {
        $this->assertFalse($this->indexExists(), 'precondition: index dropped by setUp');

        $this->migration()->up();
        $this->assertTrue($this->indexExists(), 'up() must create the composite index');

        // Idempotent — a second run is a clean no-op.
        $this->migration()->up();
        $this->assertTrue($this->indexExists());
    }

    public function test_down_drops_the_index(): void
    {
        $this->migration()->up();
        $this->assertTrue($this->indexExists());

        $this->migration()->down();
        $this->assertFalse($this->indexExists(), 'down() must drop the index');
    }
}
