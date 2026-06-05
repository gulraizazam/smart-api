<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Helpers\ACL;
use App\Helpers\Explode_Multi_select;
use App\Services\Reports\Concerns\ParsesDateRange;
use App\Services\Reports\Enums\MediumType;
use App\Services\Reports\Enums\ReportType;
use Illuminate\Foundation\Http\FormRequest;

class GeneralSalesReportRequest extends FormRequest
{
    use ParsesDateRange;

    /**
     * Roles restricted to same-day figures on the General Sales Report.
     * The date range is forced to today for these roles regardless of what
     * the client sends — enforced here (server side) so it can't be bypassed
     * via a crafted API request; the SPA also locks the date picker for them.
     */
    private const TODAY_ONLY_ROLES = ['FDM'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reportTypes = implode(',', array_column(ReportType::cases(), 'value'));

        return [
            'report_type' => "required|in:{$reportTypes}",
            'date_range' => 'nullable|string',
            'medium_type' => 'nullable|in:web,print,pdf,excel',
            'location_id' => 'nullable',
            'location_id_com' => 'nullable|array',
            'location_id_com.*' => 'nullable',
            'region_id' => 'nullable',
            'city_id' => 'nullable',
            'service_id' => 'nullable',
            'doctor_id' => 'nullable',
            'patient_id' => 'nullable',
            'user_id' => 'nullable',
            'appointment_type_id' => 'nullable',
            'gender_id' => 'nullable|string',
            'discount_id' => 'nullable',
            'machine_id' => 'nullable',
            'converted' => 'nullable|string',
        ];
    }

    public function reportType(): ReportType
    {
        return ReportType::from($this->input('report_type', 'collection_by_service'));
    }

    public function mediumType(): MediumType
    {
        return MediumType::tryFrom($this->input('medium_type', 'web')) ?? MediumType::Web;
    }

    public function startDate(): ?string
    {
        if ($this->isRestrictedToToday()) {
            return now()->toDateString();
        }

        return self::parseDateRange($this->date_range)[0];
    }

    public function endDate(): ?string
    {
        if ($this->isRestrictedToToday()) {
            return now()->toDateString();
        }

        return self::parseDateRange($this->date_range)[1];
    }

    /**
     * True when the current user's role is locked to today's figures only
     * (see self::TODAY_ONLY_ROLES). Drives the server-side date clamp above.
     */
    public function isRestrictedToToday(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasAnyRole(self::TODAY_ONLY_ROLES);
    }

    /**
     * Resolve location IDs for the general revenue detail report.
     * Handles the location_id_com field which can be an array with empty values.
     * For non-web medium types, applies Explode_Multi_select parsing.
     */
    public function resolvedLocationComIds(): array
    {
        $locationCom = $this->input('location_id_com', []);

        if (! is_array($locationCom)) {
            $locationCom = [$locationCom];
        }

        // For non-web medium types, the locations may come as comma-separated strings
        if ($this->mediumType() !== MediumType::Web && $locationCom) {
            if (is_array($locationCom) && count($locationCom) > 1) {
                $locationCom = [implode(',', $locationCom)];
            }

            $locationCom = Explode_Multi_select::explode($locationCom);
        }

        // Filter out empty values (legacy "All" sentinel was '') and the new 'all' keyword
        $filtered = array_filter($locationCom, fn ($val) => $val !== '' && $val !== null && $val !== 'all');

        // If no specific locations selected, use all user centres
        if (empty($filtered)) {
            $userCentres = ACL::getUserCentres();

            return is_array($userCentres) ? $userCentres : [$userCentres];
        }

        return array_values($filtered);
    }

    /**
     * Resolve location IDs from the location_id field (used by summary, collection, etc.).
     * Can be array or single value. Returns null when the user intended "all" —
     * downstream callers that need a concrete list should fall back to
     * ACL::getUserCentres() themselves.
     */
    public function resolvedLocationIds(): ?array
    {
        $locationId = $this->input('location_id');

        if ($locationId === null || $locationId === '' || $locationId === []) {
            return null;
        }

        if (is_array($locationId)) {
            $filtered = array_filter($locationId, fn ($val) => $val !== '' && $val !== null && $val !== 'all');

            return empty($filtered) ? null : array_values($filtered);
        }

        if ($locationId === 'all') {
            return null;
        }

        return [$locationId];
    }
}
