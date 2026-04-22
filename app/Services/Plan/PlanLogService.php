<?php

declare(strict_types=1);

namespace App\Services\Plan;

use App\Helpers\Financelog;
use App\Models\AuditTrailChanges;
use App\Models\AuditTrails;
use App\Models\PackageAdvances;
use App\Models\User;
use Illuminate\Support\Facades\Config;

/**
 * Shared plan audit log builder.
 *
 * Mirrors Admin\Patients\PackagesController::planlog — both the web and API
 * controllers call this class so log formatting cannot drift.
 */
class PlanLogService
{
    /** @var array<int,string> */
    private const ACTION_MAP = [
        1 => 'Create',
        2 => 'Edit',
        3 => 'Delete',
        4 => 'Inactive',
        5 => 'Active',
        6 => 'Cancel',
    ];

    /** @var array<int,string> */
    private const TABLE_MAP = [
        25 => 'Finance',
    ];

    /**
     * Build a patient-scoped plan audit log.
     *
     * @return array{patient:?object,plan_id:int,finance_log:array<int,array<string,mixed>>}
     */
    public function buildPatientPlanLog(int $planId, int $patientId): array
    {
        $patient = User::finduser($patientId);

        $financeLog = [];

        $findIds = PackageAdvances::withTrashed()
            ->where('package_id', '=', $planId)
            ->pluck('id')
            ->toArray();

        $findIds[] = $planId;

        $auditTrails = AuditTrails::whereIn('table_record_id', $findIds)
            ->where('audit_trail_table_name', '=', Config::get('constants.package_advance_table_name_log'))
            ->with('user:id,name')
            ->orderBy('created_at', 'asc')
            ->get();

        $count = 1;

        foreach ($auditTrails as $auditTrail) {
            $financeLog[$auditTrail->id] = [
                'sr no' => $count++,
                'id' => $auditTrail->id,
                'action' => self::ACTION_MAP[$auditTrail->audit_trail_action_name] ?? null,
                'table' => self::TABLE_MAP[$auditTrail->audit_trail_table_name] ?? null,
                'user_id' => $auditTrail->user?->getAttribute('name'),
                'created_at_orignal' => $auditTrail->created_at,
                'updated_at_orignal' => $auditTrail->updated_at,
                'detail_log' => [],
            ];

            $auditTrailChanges = AuditTrailChanges::where('audit_trail_id', '=', $auditTrail->id)->get();

            foreach ($auditTrailChanges as $changes) {
                if ((self::ACTION_MAP[$auditTrail->audit_trail_action_name] ?? null) === 'Delete') {
                    if ($changes->field_name === 'cash_amount' || $changes->field_name === 'deleted_at') {
                        $result = Financelog::Calculate_Val_advance($changes);
                        $financeLog[$auditTrail->id][$changes->field_name] = $result;
                    }
                } else {
                    $result = Financelog::Calculate_Val_advance($changes);
                    $financeLog[$auditTrail->id][$changes->field_name] = $result;
                }
            }

            if (! isset($financeLog[$auditTrail->id]['cash_flow'])) {
                $detailChanges = AuditTrailChanges::where('audit_trail_id', '=', $financeLog[$auditTrail->id]['id'])->get();

                foreach ($detailChanges as $detail) {
                    $result = Financelog::Calculate_Val($detail);
                    $financeLog[$auditTrail->id]['detail_log'][$detail->id] = [
                        'field_name' => $detail->field_name,
                        'field_before' => $result['before'],
                        'field_after' => $result['after'],
                    ];
                }
            }
        }

        foreach ($financeLog as $key => $log) {
            if (
                ($log['sr no'] ?? null) === 1
                && ($log['cash_flow'] ?? null) === 'out'
                && ($log['payment_mode_id'] ?? null) === 'Settle Amount'
            ) {
                unset($financeLog[$key]);
            }
        }

        return [
            'patient' => $patient,
            'plan_id' => $planId,
            'finance_log' => $financeLog,
        ];
    }
}
