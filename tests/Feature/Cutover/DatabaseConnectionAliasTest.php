<?php

declare(strict_types=1);

namespace Tests\Feature\Cutover;

use Tests\TestCase;

/**
 * Go-live de-risking §5.1 regression guard.
 *
 * config/database.php renamed the primary connection mysql→mariadb and dropped
 * the 'mysql' key. Production's .env was provisioned long ago with
 * DB_CONNECTION=mysql, so without a back-compat 'mysql' alias every request
 * 500s with "Database connection [mysql] not configured" the moment the shared
 * backend deploys — before the SPA is even live. This test fails if the alias
 * is removed before prod's .env is confirmed migrated to DB_CONNECTION=mariadb.
 */
class DatabaseConnectionAliasTest extends TestCase
{
    public function test_mysql_connection_alias_exists_and_mirrors_mariadb(): void
    {
        $mysql = config('database.connections.mysql');
        $mariadb = config('database.connections.mariadb');

        $this->assertIsArray($mysql, 'A "mysql" connection alias must exist (prod .env uses DB_CONNECTION=mysql).');
        $this->assertSame('mariadb', $mysql['driver'], 'The alias must use the mariadb driver (same engine).');

        // Same DB target as the canonical connection — they resolve from the
        // same env, so a request under DB_CONNECTION=mysql hits the same database.
        $this->assertSame($mariadb['database'], $mysql['database']);
        $this->assertSame($mariadb['host'], $mysql['host']);
        $this->assertSame($mariadb['port'], $mysql['port']);
    }
}
