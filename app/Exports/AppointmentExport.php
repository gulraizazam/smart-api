<?php

declare(strict_types=1);
namespace App\Exports;

use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AppointmentExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    use Exportable;

    private readonly bool $canViewContact;

    public function __construct(
        private readonly array $filters,
    ) {
        $this->canViewContact = Gate::allows('contact');
    }

    public function collection(): \Illuminate\Support\Collection
    {
        $records = [];

        foreach ($this->filters['reportData'] as $reportRow) {
            $record = [
                'ID' => $reportRow->patient_id,
                'Client' => $reportRow->patient->name,
            ];

            if ($this->canViewContact) {
                $record['Phone'] = $reportRow->patient->phone ?? 'N/A';
            }

            $record['Email'] = $reportRow->patient->email;
            $record['Scheduled'] = ($reportRow->scheduled_date) ? \Carbon\Carbon::parse($reportRow->scheduled_date, null)->format('M j, Y').' at '.\Carbon\Carbon::parse($reportRow->scheduled_time, null)->format('h:i A') : '-';
            $record['Doctor'] = (array_key_exists($reportRow->doctor_id, $this->filters['doctors'])) ? $this->filters['doctors'][$reportRow->doctor_id]->name : '';
            $record['City'] = (array_key_exists($reportRow->city_id, $this->filters['cities'])) ? $this->filters['cities'][$reportRow->city_id]->name : '';
            $record['Centre'] = (array_key_exists($reportRow->location_id, $this->filters['locations'])) ? $this->filters['locations'][$reportRow->location_id]->name : '';
            $record['Status'] = (array_key_exists($reportRow->base_appointment_status_id, $this->filters['appointment_statuses'])) ? $this->filters['appointment_statuses'][$reportRow->base_appointment_status_id]->name : '';
            $record['Type'] = (array_key_exists($reportRow->appointment_type_id, $this->filters['appointment_types'])) ? $this->filters['appointment_types'][$reportRow->appointment_type_id]->name : '';
            $record['Created At'] = \Carbon\Carbon::parse($reportRow->created_at)->format('M j, Y H:i A');
            $record['Created By'] = (array_key_exists($reportRow->created_by, $this->filters['users'])) ? $this->filters['users'][$reportRow->created_by]->name : '';

            $records[] = $record;
        }

        // Bug fixed: was assign collect() inside loop — $collection undefined when data is empty
        return collect($records);
    }

    public function headings(): array
    {
        $headings = [
            'ID',
            'Client',
            'Phone',
            'Email',
            'Scheduled',
            'Doctor',
            'City',
            'Centre',
            'Status',
            'Type',
            'Created At',
            'Created By',
        ];

        if (! $this->canViewContact) {
            $headings = array_values(array_filter($headings, fn (string $h): bool => $h !== 'Phone'));
        }

        return $headings;
    }
}
