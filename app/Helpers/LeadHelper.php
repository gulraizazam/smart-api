<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Leads;
use App\Models\LeadsServices;
use App\Models\LeadStatuses;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class LeadHelper
{
    private const CACHE_TTL = 3600;

    public static function formatPhone(string $phone, bool $hasPermission = true): string
    {
        if (! $hasPermission) {
            return '***********';
        }

        return GeneralFunctions::prepareNumber4Call($phone);
    }

    public static function getGenderLabel(int $genderId): string
    {
        return $genderId === 1 ? 'Male' : 'Female';
    }

    public static function parseGender(string $gender): int
    {
        return strtolower(trim($gender)) === 'female' ? 2 : 1;
    }

    public static function getDefaultStatus(?int $accountId = null): ?LeadStatuses
    {
        $accountId ??= Auth::user()->account_id;

        return Cache::remember("default_lead_status_{$accountId}", self::CACHE_TTL, fn (): ?LeadStatuses => LeadStatuses::where([
            'account_id' => $accountId,
            'is_default' => 1,
        ])->first());
    }

    public static function getJunkStatus(?int $accountId = null): ?LeadStatuses
    {
        $accountId ??= Auth::user()->account_id;

        return Cache::remember("junk_lead_status_{$accountId}", self::CACHE_TTL, fn (): ?LeadStatuses => LeadStatuses::where([
            'account_id' => $accountId,
            'is_junk' => 1,
        ])->first());
    }

    public static function getConvertedStatus(?int $accountId = null): ?LeadStatuses
    {
        $accountId ??= Auth::user()->account_id;

        return Cache::remember("converted_lead_status_{$accountId}", self::CACHE_TTL, fn (): ?LeadStatuses => LeadStatuses::where([
            'account_id' => $accountId,
            'is_converted' => 1,
        ])->first());
    }

    public static function canChangeStatus(Leads $lead): bool
    {
        if (! $lead->lead_status_id) {
            return true;
        }

        $currentStatus = LeadStatuses::find($lead->lead_status_id);

        return ! $currentStatus || ! ($currentStatus->is_arrived || $currentStatus->is_converted);
    }

    public static function getActiveServices(int $leadId): array
    {
        $services = LeadsServices::with(['service:id,name', 'childservice:id,name'])
            ->where('lead_id', $leadId)
            ->where('status', 1)
            ->get();

        $result = [
            'services' => [],
            'child_services' => [],
        ];

        foreach ($services as $ls) {
            if ($ls->service) {
                $result['services'][] = $ls->service->name;
            }
            if ($ls->childservice) {
                $result['child_services'][] = $ls->childservice->name;
            }
        }

        return $result;
    }

    public static function getStatusHierarchy(int $statusId): array
    {
        $status = LeadStatuses::find($statusId);

        if (! $status) {
            return ['parent' => null, 'child' => null];
        }

        if ($status->parent_id == 0) {
            return ['parent' => $status, 'child' => null];
        }

        return ['parent' => LeadStatuses::find($status->parent_id), 'child' => $status];
    }

    public static function getFilterCacheKey(int $userId, string $filename): string
    {
        return "lead_filters_{$userId}_{$filename}";
    }

    public static function clearAccountCache(?int $accountId = null): void
    {
        $accountId ??= Auth::user()->account_id;
        $userId = Auth::id();

        $keys = [
            "default_lead_status_{$accountId}",
            "junk_lead_status_{$accountId}",
            "converted_lead_status_{$accountId}",
            "lead_form_lookup_{$accountId}",
            "lead_form_lookup_cities_user_{$userId}",
            "lead_filters_user_{$userId}",
            "lead_import_lookup_{$accountId}",
        ];

        array_map(Cache::forget(...), $keys);
    }

    public static function isValidPhone(string $phone): bool
    {
        $cleanPhone = GeneralFunctions::cleanNumber($phone);
        $length = strlen($cleanPhone);

        return $length >= 10 && $length <= 13;
    }

    public static function existsByPhone(string $phone, ?int $accountId = null): bool
    {
        $accountId ??= Auth::user()->account_id;

        return Leads::where('phone', GeneralFunctions::cleanNumber($phone))
            ->where('account_id', $accountId)
            ->exists();
    }

    public static function getByPhone(string $phone, ?int $accountId = null): ?Leads
    {
        $accountId ??= Auth::user()->account_id;

        return Leads::where('phone', GeneralFunctions::cleanNumber($phone))
            ->where('account_id', $accountId)
            ->orderByDesc('id')
            ->first();
    }

    public static function formatDate(mixed $date, string $format = 'F j, Y h:i A'): string
    {
        if (! $date) {
            return 'N/A';
        }

        return Carbon::parse($date)->format($format);
    }
}
