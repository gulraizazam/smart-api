<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentMembershipPatientsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return DB::table('plans')
            ->join('package_services', 'plans.package_id', '=', 'package_services.package_id')
            ->join('services', 'package_services.service_id', '=', 'services.id')
            ->leftJoin('memberships', 'plans.patient_id', '=', 'memberships.patient_id')
            ->where('services.name', 'Student Membership') // Adjust field name if needed
            ->whereNull('memberships.patient_id') // Not in memberships table
            ->select('plans.patient_id')
            ->distinct()
            ->get();
    }

    public function headings(): array
    {
        return [
            'Patient ID'
        ];
    }

    public function map($row): array
    {
        return [
            $row->patient_id
        ];
    }
}