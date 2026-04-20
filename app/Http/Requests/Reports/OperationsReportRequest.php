<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Helpers\ACL;
use App\Services\Reports\Concerns\ParsesDateRange;
use App\Services\Reports\Enums\MediumType;
use App\Services\Reports\Enums\OperationsReportType;
use Illuminate\Foundation\Http\FormRequest;

class OperationsReportRequest extends FormRequest
{
    use ParsesDateRange;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reportTypes = implode(',', array_column(OperationsReportType::cases(), 'value'));

        return [
            'report_type' => "required|in:{$reportTypes}",
            'date_range' => 'nullable|string',
            'medium_type' => 'nullable|in:web,print,pdf,excel',
            'location_id' => 'nullable',
            'service_id' => 'nullable',
            'appointment_type_id' => 'nullable',
            'agent_id' => 'nullable',
            'user_id' => 'nullable',
            'region_id' => 'nullable',
            'city_id' => 'nullable',
            'patient_id' => 'nullable',
            'type' => 'nullable|string',
            'consultancy_type' => 'nullable|string',
        ];
    }

    public function reportType(): OperationsReportType
    {
        return OperationsReportType::from($this->input('report_type', 'dar_report'));
    }

    public function mediumType(): MediumType
    {
        return MediumType::tryFrom($this->input('medium_type', 'web')) ?? MediumType::Web;
    }

    public function startDate(): ?string
    {
        return self::parseDateRange($this->date_range)[0];
    }

    public function endDate(): ?string
    {
        return self::parseDateRange($this->date_range)[1];
    }

    /**
     * Normalise `location_id` so the "all" sentinel and empty selections both
     * resolve to the user's permitted centres before downstream services see
     * the value.
     */
    public function validated($key = null, $default = null): mixed
    {
        $data = parent::validated();

        $raw = $data['location_id'] ?? null;
        $ids = empty($raw)
            ? []
            : array_values(array_filter(
                array_map('intval', (array) $raw),
                fn (int $id): bool => $id > 0,
            ));

        if (empty($ids)) {
            $ids = array_map('intval', ACL::getUserCentres());
        }

        $data['location_id'] = $ids;

        if ($key !== null) {
            return data_get($data, $key, $default);
        }

        return $data;
    }
}
