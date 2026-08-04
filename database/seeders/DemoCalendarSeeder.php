<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Fills TODAY's consultation + treatment calendars so the diary views look
 * active in the demo deck. Guarded so re-runs don't pile up.
 */
class DemoCalendarSeeder extends Seeder
{
    public function run(): void
    {
        $acc = 1;
        $today = now()->format('Y-m-d');
        $existing = DB::table('appointments')->where('scheduled_date', $today)->where('name', 'Demo Visit')->count();
        if ($existing >= 8) {
            $this->command->info("skip: $existing demo appointments already on $today");
            return;
        }

        $doctorIds = \App\Models\User::where('account_id', $acc)->where('can_perform_consultation', 1)->pluck('id')->all();
        $patientIds = \App\Models\User::where('account_id', $acc)->where('user_type_id', 3)->pluck('id')->all();
        $serviceIds = \App\Models\Services::where('account_id', $acc)->where('parent_id', '<>', 0)->limit(40)->pluck('id')->all()
            ?: \App\Models\Services::where('account_id', $acc)->limit(40)->pluck('id')->all();
        $locationIds = \App\Models\Locations::where('account_id', $acc)->whereNotIn('id', [2, 3, 6])->pluck('id')->all()
            ?: \App\Models\Locations::where('account_id', $acc)->pluck('id')->all();
        $leadIds = \App\Models\Leads::limit(200)->pluck('id')->all();
        $regionId = (int) (\App\Models\Regions::value('id') ?? 1);
        $cityId = (int) (\App\Models\Cities::value('id') ?? 1);

        if (empty($doctorIds) || empty($patientIds) || empty($serviceIds) || empty($locationIds) || empty($leadIds)) {
            $this->command->error('missing reference data; run DemoDataSeeder first');
            return;
        }
        $docs = array_slice($doctorIds, 0, 3);
        $lid = Arr::random($locationIds);
        $cols = DB::getSchemaBuilder()->getColumnListing('appointments');

        // Time slots across the working day, alternating consultation/treatment.
        $slots = [['09:00', 45], ['09:30', 45], ['10:15', 60], ['11:15', 45], ['12:00', 60],
            ['13:30', 45], ['14:15', 60], ['15:15', 45], ['16:00', 60], ['16:45', 45], ['17:15', 45]];
        $statuses = [1, 2, 4]; // booked / arrived / completed
        $made = 0;
        foreach ($slots as $i => [$start, $dur]) {
            $type = ($i % 2 === 0) ? 1 : 2;
            $row = [
                'name' => 'Demo Visit',
                'account_id' => $acc,
                'appointment_type_id' => $type,
                'patient_id' => Arr::random($patientIds),
                'lead_id' => Arr::random($leadIds),
                'region_id' => $regionId,
                'city_id' => $cityId,
                'service_id' => Arr::random($serviceIds),
                'doctor_id' => $docs[$i % count($docs)],
                'location_id' => $lid,
                'appointment_status_id' => Arr::random($statuses),
                'scheduled_date' => $today,
                'scheduled_time' => $start.':00',
                'created_at' => now(), 'updated_at' => now(),
            ];
            DB::table('appointments')->insert(array_intersect_key($row, array_flip($cols)));
            $made++;
        }
        $this->command->info("seeded $made appointments on $today across ".count($docs).' doctors');
    }
}
