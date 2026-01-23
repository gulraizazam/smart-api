<?php

namespace App\Helpers;

use App\Models\Leads;
use App\Models\LeadStatuses;
use App\Models\LeadsServices;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class LeadHelper
{
    /**
     * Format phone number for display
     */
    public static function formatPhone(string $phone, bool $hasPermission = true): string
    {
        if (!$hasPermission) {
            return '***********';
        }
        return GeneralFunctions::prepareNumber4Call($phone);
    }

    /**
     * Get gender label from ID
     */
    public static function getGenderLabel(int $genderId): string
    {
        return $genderId === 1 ? 'Male' : 'Female';
    }

    /**
     * Parse gender from string input
     */
    public static function parseGender(string $gender): int
    {
        $gender = strtolower(trim($gender));
        return $gender === 'female' ? 2 : 1;
    }

    /**
     * Get default lead status for account
     */
    public static function getDefaultStatus(?int $accountId = null): ?LeadStatuses
    {
        $accountId = $accountId ?? Auth::user()->account_id;
        
        return Cache::remember("default_lead_status_{$accountId}", 3600, function () use ($accountId) {
            return LeadStatuses::where([
                'account_id' => $accountId,
                'is_default' => 1,
            ])->first();
        });
    }

    /**
     * Get junk lead status for account
     */
    public static function getJunkStatus(?int $accountId = null): ?LeadStatuses
    {
        $accountId = $accountId ?? Auth::user()->account_id;
        
        return Cache::remember("junk_lead_status_{$accountId}", 3600, function () use ($accountId) {
            return LeadStatuses::where([
                'account_id' => $accountId,
                'is_junk' => 1,
            ])->first();
        });
    }

    /**
     * Get converted lead status for account
     */
    public static function getConvertedStatus(?int $accountId = null): ?LeadStatuses
    {
        $accountId = $accountId ?? Auth::user()->account_id;
        
        return Cache::remember("converted_lead_status_{$accountId}", 3600, function () use ($accountId) {
            return LeadStatuses::where([
                'account_id' => $accountId,
                'is_converted' => 1,
            ])->first();
        });
    }

    /**
     * Check if lead status can be changed
     */
    public static function canChangeStatus(Leads $lead): bool
    {
        if (!$lead->lead_status_id) {
            return true;
        }

        $currentStatus = LeadStatuses::find($lead->lead_status_id);
        
        if (!$currentStatus) {
            return true;
        }

        return !($currentStatus->is_arrived || $currentStatus->is_converted);
    }

    /**
     * Get active services for a lead
     */
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

    /**
     * Get lead status hierarchy (parent and child)
     */
    public static function getStatusHierarchy(int $statusId): array
    {
        $status = LeadStatuses::find($statusId);
        
        if (!$status) {
            return ['parent' => null, 'child' => null];
        }

        if ($status->parent_id == 0) {
            return ['parent' => $status, 'child' => null];
        }

        $parent = LeadStatuses::find($status->parent_id);
        return ['parent' => $parent, 'child' => $status];
    }

    /**
     * Build lead filter cache key
     */
    public static function getFilterCacheKey(int $userId, string $filename): string
    {
        return "lead_filters_{$userId}_{$filename}";
    }

    /**
     * Clear all lead-related caches for an account
     */
    public static function clearAccountCache(?int $accountId = null): void
    {
        $accountId = $accountId ?? Auth::user()->account_id;
        
        $cacheKeys = [
            "default_lead_status_{$accountId}",
            "junk_lead_status_{$accountId}",
            "converted_lead_status_{$accountId}",
            "lead_form_lookup_{$accountId}",
            "lead_filters_{$accountId}",
            "lead_import_lookup_{$accountId}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Validate phone number format
     */
    public static function isValidPhone(string $phone): bool
    {
        $cleanPhone = GeneralFunctions::cleanNumber($phone);
        $length = strlen($cleanPhone);
        return $length >= 10 && $length <= 13;
    }

    /**
     * Check if lead exists by phone
     */
    public static function existsByPhone(string $phone, ?int $accountId = null): bool
    {
        $accountId = $accountId ?? Auth::user()->account_id;
        $cleanPhone = GeneralFunctions::cleanNumber($phone);
        
        return Leads::where('phone', $cleanPhone)
            ->where('account_id', $accountId)
            ->exists();
    }

    /**
     * Get lead by phone
     */
    public static function getByPhone(string $phone, ?int $accountId = null): ?Leads
    {
        $accountId = $accountId ?? Auth::user()->account_id;
        $cleanPhone = GeneralFunctions::cleanNumber($phone);
        
        return Leads::where('phone', $cleanPhone)
            ->where('account_id', $accountId)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Format date for display
     */
    public static function formatDate($date, string $format = 'F j, Y h:i A'): string
    {
        if (!$date) {
            return 'N/A';
        }
        
        return \Carbon\Carbon::parse($date)->format($format);
    }

}
