<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Foundation\Http\FormRequest;

class DoctorRevenueReportRequest extends FormRequest
{
    use ParsesDateRange;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_range' => 'required|string',
            'centre_id' => 'nullable|array',
            'centre_id.*' => 'integer|exists:locations,id',
            'doctor_id' => 'nullable|integer',
            'format' => 'nullable|in:pdf,excel',
        ];
    }

    public function startDate(): string
    {
        return self::parseDateRangeWithTimeBounds($this->input('date_range'))[0];
    }

    public function endDate(): string
    {
        return self::parseDateRangeWithTimeBounds($this->input('date_range'))[1];
    }

    /**
     * @return int[]
     */
    public function centreIds(): array
    {
        $value = $this->input('centre_id');
        if (empty($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map('intval', (array) $value),
            fn (int $id): bool => $id > 0,
        ));
    }

    public function doctorId(): ?int
    {
        $value = $this->input('doctor_id');
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
