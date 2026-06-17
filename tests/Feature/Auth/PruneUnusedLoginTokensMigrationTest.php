<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the one-time cleanup migration that removes the never-used 'login'
 * PATs the old login flow minted on every cookie-mode login (576 piled up on
 * prod). The delete MUST be scoped precisely: never-used 'login' tokens go;
 * a used 'login' token (a real bearer client) and any device-named token stay.
 */
class PruneUnusedLoginTokensMigrationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_prunes_only_never_used_login_tokens(): void
    {
        $user = User::factory()->admin()->create(['active' => 1]);

        // The legacy dead population: name='login', never used.
        $dead = $user->createToken('login')->accessToken;

        // A 'login' token that WAS used (a hypothetical real bearer client) — keep.
        $used = $user->createToken('login')->accessToken;
        DB::table('personal_access_tokens')->where('id', $used->id)->update(['last_used_at' => now()]);

        // A device-named token (the new opt-in path), never used yet — keep.
        $device = $user->createToken('iphone-15')->accessToken;

        $migration = require database_path(
            'migrations/2026_06_17_180000_prune_unused_login_personal_access_tokens.php',
        );
        $migration->up();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $dead->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $used->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $device->id]);
    }
}
