<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * config:cache (the api.cutera.pk deploy speed-up) is only safe if env() is
 * called EXCLUSIVELY inside config/*.php. An env() call in app/ or database/
 * silently returns its DEFAULT once config is cached. This guard keeps those
 * directories env()-free so the optimisation can't be quietly re-broken
 * (audit 2026-06 moved 5 reads into config/activity_log.php + config/services.php).
 */
class ConfigCacheSafetyTest extends TestCase
{
    public function test_no_direct_env_calls_in_app_or_database(): void
    {
        $offenders = [];

        $files = (new Finder())
            ->files()
            ->name('*.php')
            ->in([base_path('app'), base_path('database')]);

        foreach ($files as $file) {
            // The env() helper, not ->env( / ::env( / getenv(.
            if (preg_match('/(?<![\w>:])env\s*\(/', (string) $file->getContents())) {
                $offenders[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "env() must be called only inside config/*.php — it returns its default once config is cached. "
            . "Move these to a config file and read via config():\n  " . implode("\n  ", $offenders)
        );
    }
}
