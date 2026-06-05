<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\Patients;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The DB-level auto-fill trigger for `users.phone_normalized`.
 *
 * The whole point of the trigger (over crm3's Eloquent saving hook) is to
 * cover writes that DON'T go through crm3's models — above all the legacy
 * crm2 app, which shares this database and inserts/updates patients with raw
 * SQL. We simulate that here with raw query-builder writes (`DB::table`),
 * which fire NO model events, and assert phone_normalized still tracks phone.
 *
 * Production source of truth:
 * database/migrations/2026_06_05_180000_add_users_phone_normalized_autofill_trigger.php;
 * mirrored into the test DB by RefreshTestDatabase::installUsersPhoneNormalizedTrigger.
 */
class PhoneNormalizedTriggerTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // Seeds user_types (the users.user_type_id FK target) + the account
        // reference rows a patient row needs — same as the sibling
        // PatientCreationPolicyTest.
        $this->seedFinancialFixtures();
    }

    public function test_raw_insert_bypassing_the_model_hook_still_fills_phone_normalized(): void
    {
        // factory()->raw() builds a complete, valid attribute set WITHOUT
        // saving — so a DB::table()->insert fires no saving hook. Only the
        // DB trigger can populate phone_normalized on this path.
        $attrs = Patients::factory()->raw(['phone' => '0307-716 8463']);
        unset($attrs['phone_normalized']);

        $id = DB::table('users')->insertGetId($attrs);

        $row = DB::table('users')->where('id', $id)->first();
        $this->assertSame(
            '03077168463',
            $row->phone_normalized,
            'trigger must derive digits-only phone_normalized even when no crm3 model hook ran (crm2 insert path).',
        );
    }

    public function test_raw_insert_with_a_blank_phone_leaves_normalized_null_without_erroring(): void
    {
        $attrs = Patients::factory()->raw(['phone' => null]);
        unset($attrs['phone_normalized']);

        // The trigger must not throw on a NULL phone — a crm2 insert with no
        // phone has to succeed, with phone_normalized simply left NULL.
        $id = DB::table('users')->insertGetId($attrs);

        $row = DB::table('users')->where('id', $id)->first();
        $this->assertNull($row->phone_normalized);
    }

    public function test_raw_update_of_phone_recomputes_phone_normalized(): void
    {
        // Seed via the model (hook fills it), then RAW-update the phone — the
        // raw update bypasses the hook, so a correct phone_normalized after it
        // proves the BEFORE UPDATE trigger fired (the crm2 update path).
        $patient = Patients::factory()->create(['phone' => '3001112222']);

        DB::table('users')->where('id', $patient->id)->update(['phone' => '0321-999 8888']);

        $row = DB::table('users')->where('id', $patient->id)->first();
        $this->assertSame('03219998888', $row->phone_normalized);
    }

    public function test_trigger_matches_the_eloquent_hook_for_the_same_number(): void
    {
        // The trigger and the crm3 saving hook must agree, byte for byte, or a
        // patient's normalized value would flip depending on which app touched
        // it last. Same phone -> same stored normalized via both paths.
        $viaHook = Patients::factory()->create(['phone' => '0300 123 4567']);

        $rawAttrs = Patients::factory()->raw(['phone' => '0300 123 4567']);
        unset($rawAttrs['phone_normalized']);
        $rawId = DB::table('users')->insertGetId($rawAttrs);

        $viaTrigger = DB::table('users')->where('id', $rawId)->value('phone_normalized');
        $viaHookValue = DB::table('users')->where('id', $viaHook->id)->value('phone_normalized');

        $this->assertSame($viaHookValue, $viaTrigger);
        $this->assertSame('03001234567', $viaTrigger);
    }
}
