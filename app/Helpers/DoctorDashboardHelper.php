<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DoctorDashboardHelper
{
    /**
     * Doctor role names used across the system.
     * Role IDs are resolved dynamically and cached.
     */
    const DOCTOR_ROLE_NAMES = [
        'Aesthetic Doctor',
        'Consultant',
        'Lifestyle Consultant',
    ];

    /**
     * Cache TTL in seconds (1 hour).
     */
    const CACHE_TTL = 3600;

    /**
     * Get doctor role IDs (cached).
     *
     * @return array
     */
    public static function getDoctorRoleIds(): array
    {
        return Cache::remember('doctor_dashboard_role_ids', self::CACHE_TTL, function () {
            return DB::table('roles')
                ->whereIn('name', self::DOCTOR_ROLE_NAMES)
                ->pluck('id')
                ->toArray();
        });
    }

    /**
     * Get the arrived appointment status ID (cached).
     *
     * @param int $accountId
     * @return int|null
     */
    public static function getArrivedStatusId(int $accountId): ?int
    {
        return Cache::remember("arrived_status_id_{$accountId}", self::CACHE_TTL, function () use ($accountId) {
            $status = DB::table('appointment_statuses')
                ->where('account_id', $accountId)
                ->where('is_arrived', 1)
                ->first();
            return $status ? (int) $status->id : null;
        });
    }

    /**
     * Get the converted appointment status ID (cached).
     *
     * @param int $accountId
     * @return int|null
     */
    public static function getConvertedStatusId(int $accountId): ?int
    {
        return Cache::remember("converted_status_id_{$accountId}", self::CACHE_TTL, function () use ($accountId) {
            $status = DB::table('appointment_statuses')
                ->where('account_id', $accountId)
                ->where('is_converted', 1)
                ->first();
            return $status ? (int) $status->id : null;
        });
    }

    /**
     * Appointment status IDs for consultation appointments (type=1).
     * Status 2 = Arrived, Status 16 = Converted.
     */
    const CONSULTATION_STATUS_IDS = [2, 16];

    /**
     * Appointment status IDs for treatment appointments (type=2).
     * Status 2 = Arrived.
     */
    const TREATMENT_STATUS_IDS = [2];

    /**
     * Get appointment_status_id values for arrived/converted consultations.
     *
     * @return array
     */
    public static function getConsultationStatusIds(): array
    {
        return self::CONSULTATION_STATUS_IDS;
    }

    /**
     * Get appointment_status_id values for arrived treatments.
     *
     * @return array
     */
    public static function getTreatmentStatusIds(): array
    {
        return self::TREATMENT_STATUS_IDS;
    }

    /**
     * Format currency value for display.
     *
     * @param float|int $value
     * @return string
     */
    public static function formatCurrency($value): string
    {
        if ($value >= 1000000) {
            return number_format($value / 1000000, 1) . 'M';
        }
        if ($value >= 1000) {
            return number_format($value / 1000, 1) . 'K';
        }
        return number_format($value, 0);
    }

    /**
     * Calculate MoM (month-over-month) change percentage.
     *
     * @param float $current
     * @param float $previous
     * @return array ['value' => float, 'direction' => 'up'|'down'|'flat']
     */
    public static function calculateMoM(float $current, float $previous): array
    {
        if ($previous == 0) {
            if ($current > 0) {
                return ['value' => 100, 'direction' => 'up'];
            }
            return ['value' => 0, 'direction' => 'flat'];
        }

        $change = (($current - $previous) / $previous) * 100;

        return [
            'value' => round(abs($change), 1),
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
        ];
    }

    /**
     * Get date range for "this month" period.
     *
     * @return array [startDate, endDate]
     */
    public static function getThisMonthRange(): array
    {
        return [
            now()->startOfMonth()->format('Y-m-d'),
            now()->format('Y-m-d'),
        ];
    }

    /**
     * Get date range for "last month" period.
     *
     * @return array [startDate, endDate]
     */
    public static function getLastMonthRange(): array
    {
        return [
            now()->subMonth()->startOfMonth()->format('Y-m-d'),
            now()->subMonth()->endOfMonth()->format('Y-m-d'),
        ];
    }
}
