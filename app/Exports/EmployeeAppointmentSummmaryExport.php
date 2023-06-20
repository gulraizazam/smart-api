<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeAppointmentSummmaryExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    use Exportable;

    private $filters = [];

    public function __construct($filters)
    {
        $this->filters = $filters;

    }

    public function collection()
    {
        if ($this->filters['reportData']) {
            $count = 0;
            foreach ($this->filters['reportData'] as $reportpackagedata) {
                foreach ($reportpackagedata['records'] as $reportRow) {
                    $created_by = (array_key_exists($reportRow->created_by, $this->filters['users'])) ? $this->filters['users'][$reportRow->created_by]->name : '';
                    $count++;

                }
                $records[] = [
                    'Created By' => $created_by,
                    'Total Appointments ' => $count,
                ];
                $collection = collect($records);
                $count++;
            }
        }

        return $collection;
    }

    public function headings(): array
    {
        return [
            'Created By',
            'Total Appointments',
        ];
    }
}
