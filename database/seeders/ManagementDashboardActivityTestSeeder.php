<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds 2 rows per "notable" activity type — the curated set surfaced by
 * App\Services\Dashboard\Metrics\ActivityPulseMetric on the Overview /
 * Today's Activities feed. Rows are dated today at staggered times so the
 * feed has something to render and order.
 *
 * Picks the first account, one of its locations, one of its patients, and
 * the first user on the account as the actor — good enough for manual
 * testing without hard-coding IDs.
 *
 * Run:  php artisan db:seed --class=ManagementDashboardActivityTestSeeder
 * Clean:
 *   DB::table('activities')
 *     ->whereDate('created_at', today())
 *     ->where('description', 'like', '[Seeded test] %')->delete();
 */
final class ManagementDashboardActivityTestSeeder extends Seeder
{
    /**
     * Notable activity types mirrored from ActivityPulseMetric::NOTABLE_TYPES
     * plus the (label, amount-range) metadata needed to make seeded rows look
     * like the real feed. `amount` is null for non-monetary events.
     *
     * @var list<array{type: string, action: string, label: string, amount_min: ?int, amount_max: ?int}>
     */
    private const TYPES = [
        ['type' => 'payment_received',      'action' => 'Payment Received',       'label' => 'received a payment',            'amount_min' => 5000,  'amount_max' => 150000],
        ['type' => 'refund_made',           'action' => 'Refund Made',             'label' => 'processed a refund',            'amount_min' => 2000,  'amount_max' => 40000],
        ['type' => 'feedback_added',        'action' => 'Feedback Added',          'label' => 'recorded patient feedback',     'amount_min' => null,  'amount_max' => null],
        ['type' => 'membership_assigned',   'action' => 'Membership Assigned',     'label' => 'assigned a membership',         'amount_min' => 10000, 'amount_max' => 60000],
        ['type' => 'membership_renewed',    'action' => 'Membership Renewed',      'label' => 'renewed a membership',          'amount_min' => 10000, 'amount_max' => 60000],
        ['type' => 'membership_cancelled',  'action' => 'Membership Cancelled',    'label' => 'cancelled a membership',        'amount_min' => null,  'amount_max' => null],
        ['type' => 'appointment_cancelled', 'action' => 'Appointment Cancelled',   'label' => 'cancelled an appointment',      'amount_min' => null,  'amount_max' => null],
        ['type' => 'package_created',       'action' => 'Package Created',         'label' => 'created a package',             'amount_min' => 20000, 'amount_max' => 300000],
        ['type' => 'voucher_refunded',      'action' => 'Voucher Refunded',        'label' => 'refunded a voucher',            'amount_min' => 1000,  'amount_max' => 15000],
        ['type' => 'invoice_cancelled',     'action' => 'Invoice Cancelled',       'label' => 'cancelled an invoice',          'amount_min' => null,  'amount_max' => null],
    ];

    public function run(): void
    {
        $ctx = $this->resolveContext();
        if ($ctx === null) {
            $this->command->warn('Skipped: could not find an account with at least 1 user + 1 location.');

            return;
        }

        ['account_id' => $accountId, 'user_id' => $userId, 'centre_ids' => $centreIds, 'patient_ids' => $patientIds] = $ctx;

        $now = Carbon::now();
        $created = 0;

        foreach (self::TYPES as $i => $meta) {
            for ($n = 0; $n < 2; $n++) {
                // Stagger creation times so the feed has sortable rows.
                $at = $now->copy()->subMinutes(($i * 7) + ($n * 3));

                $centreId = $centreIds[array_rand($centreIds)];
                $patientId = $patientIds !== [] ? $patientIds[array_rand($patientIds)] : null;
                $amount = $meta['amount_min'] !== null
                    ? random_int($meta['amount_min'], $meta['amount_max'])
                    : null;
                $planId = random_int(10000, 99999);

                $description = '[Seeded test] '.$meta['label']
                    .' ('.($n + 1).'/2).'
                    .($amount !== null ? ' Amount: PKR '.number_format($amount).'.' : '');

                // `amount` / `plan_id` sit outside the Activity model's
                // $fillable allow-list, so a regular Model::create() drops
                // them silently. forceFill() respects casts (description is
                // still encrypted at rest) but bypasses mass-assignment.
                $activity = new Activity;
                $activity->forceFill([
                    'account_id' => $accountId,
                    'activity_type' => $meta['type'],
                    'action' => $meta['action'],
                    'description' => $description,
                    'patient' => $patientId !== null ? ('Test Patient #'.$patientId) : 'Test Patient',
                    'patient_id' => $patientId,
                    'centre_id' => $centreId,
                    'created_by' => $userId,
                    'user_id' => $userId,
                    'plan_id' => $planId,
                    'amount' => $amount,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
                $activity->save();

                $created++;
            }
        }

        $this->command->info(
            sprintf(
                'Seeded %d activity rows (2 × %d notable types) for account %d, actor user %d.',
                $created,
                count(self::TYPES),
                $accountId,
                $userId,
            ),
        );
    }

    /**
     * Pick a real account + user + centres + patients to satisfy FK columns.
     * Returns null if the DB has no viable combination (fresh install, etc.).
     *
     * @return array{account_id: int, user_id: int, centre_ids: list<int>, patient_ids: list<int>}|null
     */
    private function resolveContext(): ?array
    {
        $accountId = (int) DB::table('users')->min('account_id');
        if ($accountId === 0) {
            return null;
        }

        $userId = (int) DB::table('users')->where('account_id', $accountId)->min('id');
        if ($userId === 0) {
            return null;
        }

        $centreIds = DB::table('locations')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->where('name', 'not like', 'All %')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($centreIds === []) {
            return null;
        }

        $patientIds = DB::table('users')
            ->where('account_id', $accountId)
            ->where('user_type_id', 3)
            ->limit(20)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return [
            'account_id' => $accountId,
            'user_id' => $userId,
            'centre_ids' => array_values($centreIds),
            'patient_ids' => array_values($patientIds),
        ];
    }
}
