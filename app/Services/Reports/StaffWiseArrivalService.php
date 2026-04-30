<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\AppointmentsDailyStats;
use App\Models\AppointmentStatuses;
use App\Models\Locations;
use App\Models\RoleHasUsers;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class StaffWiseArrivalService
{
    /**
     * Compute the staff-wise arrival aggregate for the given filters.
     *
     * Mirrors the legacy FinanceArrivalReportController@staffWiseArrivalReport
     * set-based logic: every two consecutive AppointmentsDailyStats rows for
     * the same appointment count as one "set" — a set is "arrived" if any of
     * its rows carries an arrived/converted status. Walk-ins are sets where
     * the recording user is in the FDM role.
     *
     * @param  int[]  $centreIds  Empty array means "all user-permitted centres".
     */
    public function generate(
        string $startDate,
        string $endDate,
        array $centreIds,
        ?int $createdBy = null,
    ): array {
        $accountId = Auth::user()->account_id;
        $effectiveCentres = empty($centreIds)
            ? \App\Helpers\ACL::getUserCentres()
            : $centreIds;

        $arrivedStatusIds = $this->getArrivedConvertedStatusIds($accountId);
        $fdmUserIds = RoleHasUsers::where(['role_id' => 4])->pluck('user_id')->toArray();

        $records = AppointmentsDailyStats::query()
            ->select('id', 'appointment_id', 'appointment_status_id', 'user_id')
            ->whereIn('centre_id', $effectiveCentres)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->when($createdBy, fn ($q, $uid) => $q->where('user_id', $uid))
            ->orderBy('appointment_id')
            ->orderBy('id')
            ->get();

        // Group by appointment_id so we can pair rows into sets.
        $grouped = [];
        foreach ($records as $row) {
            $grouped[$row->appointment_id][] = $row;
        }

        $totalSets = 0;
        $arrivedSets = 0;
        $walkinSets = 0;

        foreach ($grouped as $rows) {
            $count = count($rows);
            $setCount = (int) ceil($count / 2);

            for ($i = 0; $i < $setCount; $i++) {
                $set = array_slice($rows, $i * 2, 2);

                $hasArrived = false;
                $isWalkin = false;
                foreach ($set as $row) {
                    if (in_array($row->appointment_status_id, $arrivedStatusIds, true)) {
                        $hasArrived = true;
                        if (! empty($fdmUserIds) && in_array($row->user_id, $fdmUserIds, true)) {
                            $isWalkin = true;
                        }
                        break;
                    }
                }

                $totalSets++;
                if ($hasArrived) {
                    $arrivedSets++;
                    if ($isWalkin) {
                        $walkinSets++;
                    }
                }
            }
        }

        // Walk-in count is suppressed when filtering by a specific user —
        // the legacy view treats per-user breakdowns as already-attributed.
        $walkin = $createdBy === null ? $walkinSets : 0;

        // Display labels for the response — UI shows the active user/centre.
        $userName = $createdBy
            ? (User::query()->where('id', $createdBy)->value('name') ?? '')
            : '';
        $centreLabel = empty($centreIds)
            ? 'All centres'
            : Locations::query()->whereIn('id', $centreIds)->pluck('name')->implode(', ');

        $totalWithoutWalkin = max($totalSets - $walkin, 0);
        $arrivedWithoutWalkin = max($arrivedSets - $walkin, 0);
        $arrivalRatio = $totalSets > 0 ? round(($arrivedSets / $totalSets) * 100, 2) : 0.0;
        $arrivalRatioWithoutWalkin = $totalWithoutWalkin > 0
            ? round(($arrivedWithoutWalkin / $totalWithoutWalkin) * 100, 2)
            : 0.0;

        return [
            'total_scheduled' => $totalSets,
            'total_scheduled_without_walkin' => $totalWithoutWalkin,
            'arrived' => $arrivedSets,
            'arrived_without_walkin' => $arrivedWithoutWalkin,
            'walkin_customers' => $walkin,
            'arrival_ratio' => $arrivalRatio,
            'arrival_ratio_without_walkin' => $arrivalRatioWithoutWalkin,
            'user_name' => $userName,
            'centre_label' => $centreLabel,
        ];
    }

    /**
     * @return array<int>
     */
    private function getArrivedConvertedStatusIds(int $accountId): array
    {
        $arrivedId = AppointmentStatuses::query()
            ->where(['account_id' => $accountId, 'is_arrived' => 1])
            ->value('id') ?? 2;

        $convertedId = AppointmentStatuses::query()
            ->where(['account_id' => $accountId, 'is_converted' => 1])
            ->value('id');

        return $convertedId ? [(int) $arrivedId, (int) $convertedId] : [(int) $arrivedId];
    }
}
