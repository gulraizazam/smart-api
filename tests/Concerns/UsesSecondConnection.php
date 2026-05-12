<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Trait for concurrency tests that need to simulate two simultaneous
 * users without `pcntl_fork`. We open a SECOND connection to the same
 * test database and use it as the "other user". The second connection
 * is intentionally NOT wrapped in the test transaction (which means
 * commits on it ARE visible across the boundary), so the test must be
 * extra careful to clean up after itself.
 *
 * Why not pcntl_fork:
 *   - pcntl_fork is unavailable on Windows.
 *   - Forks within PHPUnit corrupt the test runner's IPC pipes.
 *   - Two PDO connections to the same database are sufficient to test
 *     `lockForUpdate()` semantics, which is what we actually care about.
 *
 * Why a different connection name:
 *   - Laravel binds the test transaction to a single connection name
 *     (the `default`). A query on a different connection name lives
 *     outside that transaction.
 *
 * IMPORTANT: tests using this trait MUST clean up the rows they inserted
 * via the second connection. They will NOT be rolled back automatically.
 */
trait UsesSecondConnection
{
    private ?Connection $secondConnection = null;

    /**
     * Boot a second connection by re-using the `mariadb_testing` config but
     * giving it a fresh PDO via Laravel's connection factory. Returns the
     * `Connection` instance so the test can use ->table(), ->statement(),
     * ->select(), etc.
     */
    protected function secondConnection(): Connection
    {
        if ($this->secondConnection !== null) {
            return $this->secondConnection;
        }

        // Register a clone of the testing connection under a new name so
        // Laravel's connection manager treats it as independent.
        $base = config('database.connections.mariadb_testing');
        config(['database.connections.mariadb_testing_secondary' => $base]);

        // Forget any cached PDO so the next resolve opens a fresh socket.
        DB::purge('mariadb_testing_secondary');

        $this->secondConnection = DB::connection('mariadb_testing_secondary');

        return $this->secondConnection;
    }

    /**
     * PHPUnit teardown hook — close the secondary PDO so the test runner
     * does not leak file descriptors when the suite is large.
     */
    protected function tearDownSecondConnection(): void
    {
        if ($this->secondConnection !== null) {
            DB::purge('mariadb_testing_secondary');
            $this->secondConnection = null;
        }
    }
}
