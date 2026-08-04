<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lights up the dashboard panels that stay empty on a fresh demo clone:
 * completed appointments in the current window (Consultations/Treatments KPIs
 * + Arrival Rate), a retention cohort that matches the on-screen definition
 * ("treated 30-60 days ago, returned 7+ days later"), and treatment feedback
 * (Branch Feedback Score). Idempotent-ish: skips if it already ran.
 */
class DemoDashboardSeeder extends Seeder
{
    private array $report = [];

    public function run(): void
    {
        $acc = 1;
        $doctorIds = \App\Models\User::where('account_id', $acc)->where('can_perform_consultation', 1)->pluck('id')->all()
            ?: \App\Models\User::where('account_id', $acc)->whereIn('user_type_id', [1, 2, 5])->pluck('id')->all();
        $patientIds = \App\Models\User::where('account_id', $acc)->where('user_type_id', 3)->pluck('id')->all();
        $serviceIds = \App\Models\Services::where('account_id', $acc)->where('parent_id', '<>', 0)->limit(40)->pluck('id')->all()
            ?: \App\Models\Services::where('account_id', $acc)->limit(40)->pluck('id')->all();
        $locationIds = \App\Models\Locations::where('account_id', $acc)->whereNotIn('id', [2, 3, 6])->pluck('id')->all()
            ?: \App\Models\Locations::where('account_id', $acc)->pluck('id')->all();
        $leadIds = \App\Models\Leads::limit(200)->pluck('id')->all();
        $regionId = (int) (\App\Models\Regions::value('id') ?? 1);
        $cityId = (int) (\App\Models\Cities::value('id') ?? 1);
        $creator = 1;

        if (empty($doctorIds) || empty($patientIds) || empty($serviceIds) || empty($locationIds) || empty($leadIds)) {
            $this->command->error('missing reference data; run DemoDataSeeder first');
            return;
        }

        $patientName = fn ($pid) => (string) (\App\Models\User::where('id', $pid)->value('name') ?? 'Patient');
        $patientPhone = fn ($pid) => (string) (\App\Models\User::where('id', $pid)->value('phone') ?? '0000');

        $insertAppt = function (int $typeId, int $statusId, Carbon $date, int $pid, int $did, int $sid, int $lid) use ($acc, $leadIds, $regionId, $cityId) {
            $row = [
                'name' => 'Demo Visit',
                'account_id' => $acc,
                'appointment_type_id' => $typeId,
                'patient_id' => $pid,
                'lead_id' => Arr::random($leadIds),
                'region_id' => $regionId,
                'city_id' => $cityId,
                'service_id' => $sid,
                'doctor_id' => $did,
                'location_id' => $lid,
                'appointment_status_id' => $statusId,
                'scheduled_date' => $date->format('Y-m-d'),
                'scheduled_time' => sprintf('%02d:%02d:00', random_int(9, 17), Arr::random([0, 15, 30, 45])),
                'created_at' => $date, 'updated_at' => $date,
            ];
            return DB::table('appointments')->insertGetId(array_intersect_key($row, array_flip($this->cols('appointments'))));
        };

        // ---- Retention cohort: treated 30-60d ago + a return 7+ days later ----
        $this->safe('retention_cohort', function () use ($insertAppt, $patientIds, $doctorIds, $serviceIds, $locationIds) {
            if (DB::table('appointments')->where('name', 'Demo Visit')->exists()) { return 'skip: already enriched'; }
            $n = 0;
            foreach (array_slice($patientIds, 0, 20) as $pid) {
                $did = Arr::random($doctorIds); $sid = Arr::random($serviceIds); $lid = Arr::random($locationIds);
                $insertAppt(2, 4, now()->subDays(random_int(35, 55)), $pid, $did, $sid, $lid); // first treatment
                $insertAppt(2, 4, now()->subDays(random_int(3, 12)), $pid, $did, $sid, $lid);   // return visit
                $n++;
            }
            return "$n patient cohorts (2 treatments each)";
        });

        // ---- Recent completed visits (KPIs + Arrival Rate incl. today) --------
        $recentApptIds = [];
        $this->safe('recent_visits', function () use (&$recentApptIds, $insertAppt, $patientIds, $doctorIds, $serviceIds, $locationIds) {
            foreach (range(1, 30) as $i) {
                $recentApptIds[] = $insertAppt(
                    Arr::random([1, 1, 2]), 4,
                    now()->subDays(random_int(0, 6)),
                    Arr::random($patientIds), Arr::random($doctorIds), Arr::random($serviceIds), Arr::random($locationIds)
                );
            }
            return count($recentApptIds).' completed visits';
        });

        // ---- Feedback for completed visits (Branch Feedback Score) -------------
        $this->safe('feedback', function () use ($recentApptIds, $patientName, $patientPhone, $doctorIds, $serviceIds, $locationIds, $creator) {
            if (DB::table('feedback')->count() >= 20) { return 'skip: enough feedback'; }
            $comments = ['Great experience, very professional.', 'Friendly staff and clean clinic.', 'Results were excellent.', 'Would recommend to friends.', 'Comfortable and reassuring visit.', 'Very satisfied with the treatment.'];
            $done = 0;
            foreach ($recentApptIds as $aid) {
                $a = DB::table('appointments')->where('id', $aid)->first(['patient_id', 'doctor_id', 'service_id', 'location_id', 'created_at']);
                if (! $a) { continue; }
                DB::table('feedback')->insert(array_intersect_key([
                    'patient_name' => $patientName($a->patient_id),
                    'patient_phone' => $patientPhone($a->patient_id),
                    'patient_id' => $a->patient_id,
                    'doctor_id' => $a->doctor_id,
                    'service_id' => $a->service_id,
                    'appointment_id' => $aid,
                    'location_id' => $a->location_id,
                    'rating' => (string) random_int(7, 10),
                    'comment' => Arr::random($comments),
                    'created_by' => $creator,
                    'created_at' => $a->created_at, 'updated_at' => $a->created_at,
                ], array_flip($this->cols('feedback'))));
                $done++;
            }
            return "$done feedback rows";
        });

        $this->command->info('==== DemoDashboardSeeder ====');
        foreach ($this->report as $k => $v) { $this->command->info(sprintf('  %-18s %s', $k, $v)); }
    }

    private function safe(string $label, \Closure $fn): void
    {
        try { $this->report[$label] = 'OK: '.($fn() ?: 'done'); }
        catch (\Throwable $e) { $this->report[$label] = 'FAIL: '.$e->getMessage(); }
    }

    private function cols(string $table): array
    {
        static $c = [];
        return $c[$table] ??= DB::getSchemaBuilder()->getColumnListing($table);
    }
}
