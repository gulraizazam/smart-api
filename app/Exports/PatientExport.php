<?php

declare(strict_types=1);
namespace App\Exports;

use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PatientExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    use Exportable;

    private readonly bool $canViewContact;

    public function __construct(
        private readonly array $filters,
    ) {
        $this->canViewContact = Gate::allows('patients.list.view_contact');
    }

    public function collection(): \Illuminate\Support\Collection
    {
        $Cities = $this->filters['Cities'];
        $lead_status = $this->filters['lead_status'];
        $services = $this->filters['services'];
        $users = $this->filters['users'];
        $count = 1;
        $records = [];

        foreach ($this->filters['leads'] as $lead) {
            $record = [
                '#' => $count++,
                'name' => $lead->name,
            ];

            if ($this->canViewContact) {
                $record['phone'] = $lead->patient->phone ?? 'N/A';
            }

            $record['city'] = $Cities[$lead->city_id]->name;
            $record['lead_status'] = $lead_status[$lead->lead_status_id]->name;
            $record['service'] = $services[$lead->service_id]->name;
            $record['user'] = $users[$lead->created_by]->name;

            $records[] = $record;
        }

        // Bug fixed: was collect() inside loop — $collection undefined when leads is empty
        return collect($records);
    }

    public function headings(): array
    {
        $headings = [
            '#',
            'Full Name',
            'Phone',
            'City',
            'Lead Status',
            'Service',
            'Created By',
        ];

        if (! $this->canViewContact) {
            $headings = array_values(array_filter($headings, fn (string $h): bool => $h !== 'Phone'));
        }

        return $headings;
    }
}
