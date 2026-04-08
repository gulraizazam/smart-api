<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Services\Reports\Enums\MediumType;
use App\Services\Reports\Enums\OperationsReportType;
use Illuminate\Foundation\Http\FormRequest;

class OperationsReportRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        return true;
    }

    #[\Override]
    public function rules(): array
    {
        $reportTypes = implode(',', array_column(OperationsReportType::cases(), 'value'));

        return [
            'report_type'        => "required|in:{$reportTypes}",
            'date_range'         => 'nullable|string',
            'medium_type'        => 'nullable|in:web,print,pdf,excel',
            'location_id'        => 'nullable',
            'service_id'         => 'nullable',
            'appointment_type_id' => 'nullable',
            'agent_id'           => 'nullable',
            'user_id'            => 'nullable',
            'region_id'          => 'nullable',
            'city_id'            => 'nullable',
            'patient_id'         => 'nullable',
            'type'               => 'nullable|string',
            'consultancy_type'   => 'nullable|string',
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
        if ($this->date_range) {
            $parts = explode(' - ', $this->date_range);

            return date('Y-m-d', strtotime($parts[0]));
        }

        return null;
    }

    public function endDate(): ?string
    {
        if ($this->date_range) {
            $parts = explode(' - ', $this->date_range);

            return date('Y-m-d', strtotime($parts[1]));
        }

        return null;
    }
}
