<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * HOLE-1 regression guard for the Blade cutover.
 *
 * `appointment:3rd-message-before-appointment` and `memberships:expire` used
 * to run ONLY via the legacy web URLs (GET /3rd-message-before-appointment and
 * GET /check-memberships in routes/web.php), triggered by an external pinger.
 * Those routes are deleted at the Blade cutover. If either command is not in
 * the scheduler, the 3rd reminder SMS and membership expiry silently stop in
 * production — invisible until memberships stop expiring (which also stalls the
 * referral expiry cascade). This test fails the moment either is dropped from
 * withSchedule() in bootstrap/app.php.
 *
 * Asserted against the real `schedule:list` output (built by the console kernel
 * exactly as production builds it) rather than app(Schedule::class), whose
 * withSchedule callbacks are only applied in console context, not in tests.
 */
class ScheduledCommandsTest extends TestCase
{
    public function test_cutover_critical_commands_are_scheduled(): void
    {
        // Force a wide virtual terminal so schedule:list doesn't truncate the
        // long command signatures we assert on.
        $previousColumns = getenv('COLUMNS');
        putenv('COLUMNS=250');

        try {
            Artisan::call('schedule:list', ['--no-ansi' => true]);
            $output = Artisan::output();
        } finally {
            putenv($previousColumns === false ? 'COLUMNS' : "COLUMNS={$previousColumns}");
        }

        foreach ([
            'appointment:3rd-message-before-appointment',
            'memberships:expire',
            // FIX H: the Management Dashboard daily-metrics rollup has no other
            // dispatcher; without this schedule the SPA dashboard metrics go stale.
            'management-dashboard:rebuild-metrics',
        ] as $signature) {
            $this->assertStringContainsString(
                $signature,
                $output,
                "Command [{$signature}] must appear in `schedule:list` — it has no "
                . 'web-URL fallback after the Blade cutover (HOLE-1).',
            );
        }
    }
}
