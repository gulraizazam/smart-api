<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Foundation\Http\FormRequest;

class AppointmentsReportRequest extends FormRequest
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
            'time' => 'required|integer|in:10,20,30',
            'centre_id' => 'nullable|array',
            'centre_id.*' => 'integer|exists:locations,id',
            'created_by' => 'nullable|integer',
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

    public function timeInterval(): int
    {
        return (int) $this->input('time');
    }

    /**
     * @return int[]|null
     */
    public function centreIds(): ?array
    {
        $value = $this->input('centre_id');

        if (empty($value)) {
            return null;
        }

        return array_map('intval', (array) $value);
    }

    public function createdBy(): ?int
    {
        $value = $this->input('created_by');

        return $value ? (int) $value : null;
    }
}
