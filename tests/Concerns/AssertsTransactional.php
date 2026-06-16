<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Closure;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;
use Throwable;

/**
 * Helpers for proving that a multi-statement operation rolls back cleanly
 * when one of its statements fails.
 *
 * The ALLURA financial-spine audit found several controllers that wrote
 * cash advances + appointment updates without `DB::transaction()`, leaving
 * the database in a torn state when something later in the flow threw.
 * These helpers catch the same pattern in tests by:
 *
 *   1. Snapshotting the relevant tables before the operation.
 *   2. Forcing an exception at a specific point inside the operation.
 *   3. Re-snapshotting and asserting nothing changed.
 */
trait AssertsTransactional
{
    /**
     * Run `$callback` and assert that it ALL committed: `$tables` are
     * counted before and after, and the difference must equal the
     * `$expectedDelta`.
     *
     * @param  array<string, int>  $expectedDelta  table => row count delta
     */
    protected function assertCommittedDelta(array $expectedDelta, Closure $callback): void
    {
        $before = $this->snapshotCounts(array_keys($expectedDelta));

        $callback();

        $after = $this->snapshotCounts(array_keys($expectedDelta));

        foreach ($expectedDelta as $table => $delta) {
            $actual = $after[$table] - $before[$table];
            Assert::assertSame(
                $delta,
                $actual,
                "Expected `{$table}` to gain {$delta} rows, observed {$actual}. "
                . 'Either the operation skipped a write or extra writes leaked from a different test fixture.'
            );
        }
    }

    /**
     * Run `$callback` inside a try/catch, expect it to throw, and assert
     * that ZERO rows were persisted to `$tables` (i.e. the rollback was
     * complete). Use after deliberately forcing an exception inside the
     * operation under test (e.g. by passing an invalid id, mocking a
     * service to throw, etc.).
     *
     * @param  list<string>  $tables
     */
    protected function assertRollsBackOnFailure(array $tables, Closure $callback): void
    {
        $before = $this->snapshotCounts($tables);

        $threw = false;
        try {
            $callback();
        } catch (Throwable) {
            $threw = true;
        }

        Assert::assertTrue(
            $threw,
            'Expected the callback to throw so that the transaction would roll back, but no exception was raised. '
            . 'Either the test fixture is wrong, or the code is silently swallowing the error.'
        );

        $after = $this->snapshotCounts($tables);

        foreach ($tables as $table) {
            Assert::assertSame(
                $before[$table],
                $after[$table],
                "Rollback failed: `{$table}` row count changed from {$before[$table]} to {$after[$table]}. "
                . 'The operation is missing DB::transaction() somewhere in the call chain.'
            );
        }
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    private function snapshotCounts(array $tables): array
    {
        $snapshot = [];
        foreach ($tables as $table) {
            $snapshot[$table] = DB::table($table)->count();
        }

        return $snapshot;
    }
}
