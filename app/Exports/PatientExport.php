<?php

namespace App\Exports;

use App\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PatientExport implements FromCollection, WithHeadings,ShouldAutoSize
{
    use Exportable;

    private $filters = array();

    public function __construct($filters)
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        //dd($this->filters['leads']);
        $leads = $this->filters['leads'];
        $Cities = $this->filters['Cities'];
        $lead_status = $this->filters['lead_status'];
        $services = $this->filters['services'];
        $todaydate = $this->filters['todaydate'];
        $users = $this->filters['users'];
        $count = 1;
        foreach($this->filters['leads'] as $lead) {
            $records[] = array(
                '#' => $count++,
                'name' => $lead->name,
                'phone' => $lead->patient->phone,
                'city' => $Cities[$lead->city_id]->name,
                'lead_status' => $lead_status[$lead->lead_status_id]->name,
                'service' => $services[$lead->service_id]->name,
                'user' => $users[$lead->created_by]->name,
            );
            $collection = collect($records);
        }
        return $collection;
    }

    public function headings(): array
    {
        return [
            '#',
            'Full Name',
            'Phone',
            'City',
            'Lead Status',
            'Service',
            'Created By',
        ];
    }

}