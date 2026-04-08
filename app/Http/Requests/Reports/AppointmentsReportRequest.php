<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class AppointmentsReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_range'  => 'required|string',
            'time'        => 'required|integer|in:10,20,30',
            'centre_id'   => 'nullable|integer',
            'created_by'  => 'nullable|integer',
        ];
    }

    public function startDate(): string
    {
        $parts = explode(' - ', $this->input('date_range'));

        return date('Y-m-d 00:00:00', strtotime($parts[0]));
    }

    public function endDate(): string
    {
        $parts = explode(' - ', $this->input('date_range'));

        return date('Y-m-d 23:59:59', strtotime($parts[1]));
    }

    public function timeInterval(): int
    {
        return (int) $this->input('time');
    }

    public function centreId(): ?int
    {
        $value = $this->input('centre_id');

        return $value ? (int) $value : null;
    }

    public function createdBy(): ?int
    {
        $value = $this->input('created_by');

        return $value ? (int) $value : null;
    }
}
