<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Services\Schedule\ExceptionDayRotaCloner;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * ExceptionDayRotaCloner — copies the previous working day's rota onto a
 * newly-working exception date (e.g. a Sunday opened via a Business
 * Working Days exception), account-wide.
 *
 * The hard requirement from the brief: "make sure schedule of any other
 * day won't disturb at any cost." These tests pin exactly that — only the
 * exception date is written, real schedules are never clobbered, and off
 * resources stay off.
 */
class ExceptionDayRotaClonerTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private ExceptionDayRotaCloner $cloner;

    private const SATURDAY = '2026-06-06';
    private const SUNDAY = '2026-06-07'; // exception date
    private const FRIDAY = '2026-06-05';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->cloner = app(ExceptionDayRotaCloner::class);
    }

    private function makeRota(int $accountId = 1, int $resourceId = 101, int $locationId = 1): int
    {
        return (int) DB::table('resource_has_rota')->insertGetId([
            'start' => self::FRIDAY,
            'end' => '2026-06-30',
            'account_id' => $accountId,
            'location_id' => $locationId,
            'resource_id' => $resourceId,
            'resource_type_id' => 2, // doctor
            'active' => 1,
            'is_consultancy' => 1,
            'is_treatment' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeRotaDay(int $rotaId, string $date, ?string $start, ?string $end, ?string $startOff = null, ?string $endOff = null): int
    {
        return (int) DB::table('resource_has_rota_days')->insertGetId([
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'start_off' => $startOff,
            'end_off' => $endOff,
            'start_timestamp' => $start ? "{$date} {$start}:00" : null,
            'end_timestamp' => $end ? "{$date} {$end}:00" : null,
            'active' => 1,
            'resource_has_rota_id' => $rotaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function sundayRow(int $rotaId): ?object
    {
        return DB::table('resource_has_rota_days')
            ->where('resource_has_rota_id', $rotaId)
            ->whereDate('date', self::SUNDAY)
            ->whereNull('deleted_at')
            ->first();
    }

    public function test_creates_exception_day_rota_from_the_previous_day(): void
    {
        $rota = $this->makeRota();
        $this->makeRotaDay($rota, self::SATURDAY, '09:00', '17:00', '13:00', '14:00');

        $count = $this->cloner->cloneFromMostRecentWorkingDay(1, Carbon::parse(self::SUNDAY));

        $this->assertSame(1, $count);
        $sunday = $this->sundayRow($rota);
        $this->assertNotNull($sunday, 'A Sunday rota day must be created.');
        $this->assertSame('09:00', substr((string) $sunday->start_time, 0, 5));
        $this->assertSame('17:00', substr((string) $sunday->end_time, 0, 5));
        $this->assertSame('13:00', substr((string) $sunday->start_off, 0, 5));
        $this->assertSame('14:00', substr((string) $sunday->end_off, 0, 5));
    }

    public function test_fills_an_empty_off_placeholder_row_for_the_exception_day(): void
    {
        $rota = $this->makeRota();
        $this->makeRotaDay($rota, self::SATURDAY, '10:00', '18:00');
        // The off-weekday placeholder the rota generator materialises.
        $placeholderId = $this->makeRotaDay($rota, self::SUNDAY, null, null);

        $count = $this->cloner->cloneFromMostRecentWorkingDay(1, Carbon::parse(self::SUNDAY));

        $this->assertSame(1, $count);
        $sunday = $this->sundayRow($rota);
        $this->assertSame($placeholderId, (int) $sunday->id, 'The existing placeholder row must be filled, not duplicated.');
        $this->assertSame('10:00', substr((string) $sunday->start_time, 0, 5));
        $this->assertSame('18:00', substr((string) $sunday->end_time, 0, 5));
    }

    public function test_never_clobbers_a_real_existing_schedule_on_the_exception_day(): void
    {
        $rota = $this->makeRota();
        $this->makeRotaDay($rota, self::SATURDAY, '09:00', '17:00');
        // A real schedule already exists for the exception day.
        $this->makeRotaDay($rota, self::SUNDAY, '11:00', '15:00');

        $count = $this->cloner->cloneFromMostRecentWorkingDay(1, Carbon::parse(self::SUNDAY));

        $this->assertSame(0, $count, 'A real existing schedule must be left untouched.');
        $sunday = $this->sundayRow($rota);
        $this->assertSame('11:00', substr((string) $sunday->start_time, 0, 5));
        $this->assertSame('15:00', substr((string) $sunday->end_time, 0, 5));
    }

    public function test_copies_nothing_when_there_is_no_working_day_in_the_window(): void
    {
        $rota = $this->makeRota();
        // The only nearby day is off (null times); nothing to carry over.
        $this->makeRotaDay($rota, self::SATURDAY, null, null);

        $count = $this->cloner->cloneFromMostRecentWorkingDay(1, Carbon::parse(self::SUNDAY));

        $this->assertSame(0, $count);
        $this->assertNull($this->sundayRow($rota), 'With no working day in the window, nothing is created.');
    }

    public function test_walks_back_past_an_off_previous_day_to_the_most_recent_working_day(): void
    {
        $rota = $this->makeRota();
        // Immediately-previous day (Saturday) is off; Friday was worked.
        $this->makeRotaDay($rota, self::SATURDAY, null, null);
        $this->makeRotaDay($rota, self::FRIDAY, '08:00', '16:00');

        $count = $this->cloner->cloneFromMostRecentWorkingDay(1, Carbon::parse(self::SUNDAY));

        $this->assertSame(1, $count);
        $sunday = $this->sundayRow($rota);
        $this->assertNotNull($sunday, 'It should fall back to Friday when Saturday is off.');
        $this->assertSame('08:00', substr((string) $sunday->start_time, 0, 5));
        $this->assertSame('16:00', substr((string) $sunday->end_time, 0, 5));
    }

    public function test_prefers_the_most_recent_of_several_working_days(): void
    {
        $rota = $this->makeRota();
        $this->makeRotaDay($rota, '2026-06-03', '07:00', '15:00'); // Wednesday (older)
        $this->makeRotaDay($rota, self::SATURDAY, '09:00', '17:00'); // most recent

        $this->cloner->cloneFromMostRecentWorkingDay(1, Carbon::parse(self::SUNDAY));

        $sunday = $this->sundayRow($rota);
        $this->assertSame('09:00', substr((string) $sunday->start_time, 0, 5), 'Saturday (most recent) wins over Wednesday.');
        $this->assertSame('17:00', substr((string) $sunday->end_time, 0, 5));
    }

    public function test_does_not_look_back_beyond_the_window(): void
    {
        $rota = $this->makeRota();
        // 40 days before the exception — outside the 31-day look-back window.
        $stale = Carbon::parse(self::SUNDAY)->subDays(40)->toDateString();
        $this->makeRotaDay($rota, $stale, '09:00', '17:00');

        $count = $this->cloner->cloneFromMostRecentWorkingDay(1, Carbon::parse(self::SUNDAY));

        $this->assertSame(0, $count, 'A working day older than the look-back window is ignored.');
        $this->assertNull($this->sundayRow($rota));
    }

    public function test_does_not_disturb_other_dates(): void
    {
        $rota = $this->makeRota();
        $this->makeRotaDay($rota, self::FRIDAY, '08:00', '16:00');
        $this->makeRotaDay($rota, self::SATURDAY, '09:00', '17:00');

        $this->cloner->cloneFromMostRecentWorkingDay(1, Carbon::parse(self::SUNDAY));

        // Friday and Saturday rows are exactly as they were.
        $friday = DB::table('resource_has_rota_days')->where('resource_has_rota_id', $rota)->whereDate('date', self::FRIDAY)->first();
        $saturday = DB::table('resource_has_rota_days')->where('resource_has_rota_id', $rota)->whereDate('date', self::SATURDAY)->first();
        $this->assertSame('08:00', substr((string) $friday->start_time, 0, 5));
        $this->assertSame('16:00', substr((string) $friday->end_time, 0, 5));
        $this->assertSame('09:00', substr((string) $saturday->start_time, 0, 5));
        $this->assertSame('17:00', substr((string) $saturday->end_time, 0, 5));
        // Exactly one Friday and one Saturday row remain (no duplication).
        $this->assertSame(1, DB::table('resource_has_rota_days')->where('resource_has_rota_id', $rota)->whereDate('date', self::FRIDAY)->count());
        $this->assertSame(1, DB::table('resource_has_rota_days')->where('resource_has_rota_id', $rota)->whereDate('date', self::SATURDAY)->count());
    }

    public function test_is_scoped_to_the_callers_account(): void
    {
        $myRota = $this->makeRota(accountId: 1, resourceId: 101);
        $this->makeRotaDay($myRota, self::SATURDAY, '09:00', '17:00');

        $otherRota = $this->makeRota(accountId: 999, resourceId: 202);
        $this->makeRotaDay($otherRota, self::SATURDAY, '09:00', '17:00');

        $count = $this->cloner->cloneFromMostRecentWorkingDay(1, Carbon::parse(self::SUNDAY));

        $this->assertSame(1, $count, 'Only the caller account\'s rota should be cloned.');
        $this->assertNotNull($this->sundayRow($myRota));
        $this->assertNull($this->sundayRow($otherRota), 'A different account\'s rota must not be touched.');
    }

    public function test_copies_all_shifts_for_a_split_shift_day(): void
    {
        $rota = $this->makeRota();
        // Saturday morning + afternoon split shift.
        $this->makeRotaDay($rota, self::SATURDAY, '09:00', '13:00');
        $this->makeRotaDay($rota, self::SATURDAY, '14:00', '18:00');

        $count = $this->cloner->cloneFromMostRecentWorkingDay(1, Carbon::parse(self::SUNDAY));

        $this->assertSame(2, $count, 'Both shifts must be carried over.');
        $sundayShifts = DB::table('resource_has_rota_days')
            ->where('resource_has_rota_id', $rota)
            ->whereDate('date', self::SUNDAY)
            ->whereNull('deleted_at')
            ->orderBy('start_time')
            ->get();
        $this->assertCount(2, $sundayShifts);
        $this->assertSame('09:00', substr((string) $sundayShifts[0]->start_time, 0, 5));
        $this->assertSame('13:00', substr((string) $sundayShifts[0]->end_time, 0, 5));
        $this->assertSame('14:00', substr((string) $sundayShifts[1]->start_time, 0, 5));
        $this->assertSame('18:00', substr((string) $sundayShifts[1]->end_time, 0, 5));
    }

    public function test_clones_across_all_centres(): void
    {
        $centreA = $this->makeRota(accountId: 1, resourceId: 101, locationId: 1);
        $centreB = $this->makeRota(accountId: 1, resourceId: 202, locationId: 2);
        $this->makeRotaDay($centreA, self::SATURDAY, '09:00', '17:00');
        $this->makeRotaDay($centreB, self::SATURDAY, '08:00', '16:00');

        $count = $this->cloner->cloneFromMostRecentWorkingDay(1, Carbon::parse(self::SUNDAY));

        $this->assertSame(2, $count, 'Every centre with a previous-day schedule gets cloned.');
        $this->assertSame('09:00', substr((string) $this->sundayRow($centreA)->start_time, 0, 5));
        $this->assertSame('08:00', substr((string) $this->sundayRow($centreB)->start_time, 0, 5));
    }
}
