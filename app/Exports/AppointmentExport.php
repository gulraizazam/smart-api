<?php

namespace App\Exports;

use App\Appointments;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class AppointmentExport implements FromCollection, WithHeadings,ShouldAutoSize
{
    use Exportable;

    private $filters = array();

    public function __construct($filters)
    {
        $this->filters = $filters;

    }
    public function collection()
    {

        foreach($this->filters['reportData'] as $reportRow) {
            $records[] = array(
                'ID' => $reportRow->patient_id,
                'Client' => $reportRow->patient->name,
                'Phone' => $reportRow->patient->phone,
                'Email' => $reportRow->patient->email,
                'Scheduled' => ($reportRow->scheduled_date) ? \Carbon\Carbon::parse($reportRow->scheduled_date, null)->format('M j, Y') . ' at ' . \Carbon\Carbon::parse($reportRow->scheduled_time, null)->format('h:i A') : '-',
                'Doctor' => (array_key_exists($reportRow->doctor_id, $this->filters['doctors'])) ? $this->filters['doctors'][$reportRow->doctor_id]->name : '',
                'City' => (array_key_exists($reportRow->city_id, $this->filters['cities'])) ? $this->filters['cities'][$reportRow->city_id]->name : '',
                'Centre' => (array_key_exists($reportRow->location_id, $this->filters['locations'])) ? $this->filters['locations'][$reportRow->location_id]->name : '',
                'Status' => (array_key_exists($reportRow->base_appointment_status_id, $this->filters['appointment_statuses'])) ? $this->filters['appointment_statuses'][$reportRow->base_appointment_status_id]->name : '',
                'Type' => (array_key_exists($reportRow->appointment_type_id, $this->filters['appointment_types'])) ? $this->filters['appointment_types'][$reportRow->appointment_type_id]->name : '',
                'Created At' => \Carbon\Carbon::parse($reportRow->created_at)->format('M j, Y H:i A'),
                'Created By' => (array_key_exists($reportRow->created_by, $this->filters['users'])) ? $this->filters['users'][$reportRow->created_by]->name : '',
            );
            $collection = collect($records);
        }
        return $collection;
    }

    public function headings(): array
    {
        return [
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
            'Created By'
        ];
    }

}

