<?php

declare(strict_types=1);
namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeeAppointmentSummmaryExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    use Exportable;

    private array $filters = [];

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function collection(): \Illuminate\Support\Collection
    {
        $records = [];

        if ($this->filters['reportData']) {
            foreach ($this->filters['reportData'] as $reportpackagedata) {
                $count = 0;
                $created_by = '';
                foreach ($reportpackagedata['records'] as $reportRow) {
                    $created_by = (array_key_exists($reportRow->created_by, $this->filters['users']))
                        ? $this->filters['users'][$reportRow->created_by]->name
                        : '';
                    $count++;
                }
                $records[] = [
                    'Created By' => $created_by,
                    'Total Appointments' => $count,
                ];
            }
        }

        // Bug fixed: was collect() inside loop — $collection undefined when reportData is empty
        // Bug fixed: $count was incremented twice per outer iteration (once in inner loop, once after)
        // Bug fixed: $created_by was potentially undefined if inner records array was empty
        return collect($records);
    }

    public function headings(): array
    {
        return [
            'Created By',
            'Total Appointments',
        ];
    }
}
