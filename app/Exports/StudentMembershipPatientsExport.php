<?php

declare(strict_types=1);
namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentMembershipPatientsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection(): \Illuminate\Support\LazyCollection
    {
        return DB::table('packages')
            ->join('package_services', 'packages.id', '=', 'package_services.package_id')
            ->join('services', 'package_services.service_id', '=', 'services.id')
            ->leftJoin('memberships', 'packages.patient_id', '=', 'memberships.patient_id')
            ->leftJoin('locations', 'packages.location_id', '=', 'locations.id')
            ->where('services.name', 'Student Membership Card')
            ->whereNull('memberships.patient_id')
            ->select('packages.patient_id', 'locations.name as location_name')
            ->distinct()
            ->cursor();
    }

    public function headings(): array
    {
        return [
            'Patient ID',
            'Location Name'
        ];
    }

    public function map($row): array
    {
        return [
            $row->patient_id,
            $row->location_name
        ];
    }
}