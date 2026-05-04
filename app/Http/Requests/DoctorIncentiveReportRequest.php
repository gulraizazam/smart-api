<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Foundation\Http\FormRequest;

class DoctorIncentiveReportRequest extends FormRequest
{
    use ParsesDateRange;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'location_id' => 'nullable|array',
            'location_id.*' => 'integer|exists:locations,id',
            'doctor_id' => 'nullable|integer|exists:users,id',
            'date_range' => 'nullable|string',
        ];
    }

    /**
     * @return int[]|null
     */
    public function locationIds(): ?array
    {
        $value = $this->input('location_id');
        if (empty($value)) {
            return null;
        }
        $ids = array_values(array_filter(array_map('intval', (array) $value), fn (int $id): bool => $id > 0));

        return empty($ids) ? null : $ids;
    }

    public function doctorId(): ?int
    {
        $value = $this->input('doctor_id');

        return ($value !== null && $value !== '') ? (int) $value : null;
    }

    public function startDate(): ?string
    {
        return self::parseDateRange($this->input('date_range'))[0];
    }

    public function endDate(): ?string
    {
        return self::parseDateRange($this->input('date_range'))[1];
    }
}
