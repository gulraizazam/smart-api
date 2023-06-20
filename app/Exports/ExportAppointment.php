<?php

namespace App\Exports;

use App\Helpers\ACL;
use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

class ExportAppointment implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    private $limit = 1000;

    private $offset = 0;

    public function __construct($limit = 1000, $offset = 0)
    {
        $this->limit = $limit;
        $this->offset = $offset;
    }

    public function collection()
    {
        return Appointments::join('users', function ($join) {
            $join->on('users.id', '=', 'appointments.patient_id')
                ->where('users.user_type_id', '=', config('constants.patient_id'));
        })->whereIn('appointments.city_id', ACL::getUserCities())
            ->whereIn('appointments.location_id', ACL::getUserCentres())
            ->limit($this->limit)->offset($this->offset)
            ->orderBy('appointments.id', 'DESC')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Patient',
            'Phone',
            'Scheduled',
            'Doctor',
            'Region',
            'City',
            'Centre',
            'Service',
            'Status',
            'Type',
            'Consultancy Type',
            'Created At',
            'Created By',
            'Updated By',
            'Reschedule By',
        ];
    }

    public function map($appointment): array
    {
        if ($appointment->consultancy_type == 'in_person') {
            $consultancy_type = 'In Person';
        } elseif ($appointment->consultancy_type == 'virtual') {
            $consultancy_type = 'Virtual';
        } else {
            $consultancy_type = 'N/A';
        }

        if (! Gate::allows('contact')) {
            $phone = '***********';
        } else {
            $phone = $appointment->phone ?? 'N/A';
        }

        return [
            GeneralFunctions::patientSearchStringAdd($appointment->id),
            $appointment->name ?? 'N/A',
            $phone,
            Carbon::parse($appointment->scheduled_date)->format('F j,Y').' '.Carbon::parse($appointment->scheduled_time)->format('h:i A') ?? 'N/A',
            $appointment->doctor->name ?? 'N/A',
            $appointment->region->name ?? 'N/A',
            $appointment->city->name ?? 'N/A',
            $appointment->location->name ?? 'N/A',
            $appointment->service->name ?? 'N/A',
            $appointment->appointment_status->name ?? 'N/A',
            $appointment->appointment_type->name ?? 'N/A',
            $consultancy_type ?? 'N/A',
            Carbon::parse($appointment->created_at)->format('F j,Y h:i A') ?? 'N/A',
            $appointment->user->name ?? 'N/A',
            $appointment->user_updated_by->name ?? 'N/A',
            $appointment->user_converted_by->name ?? 'N/A',
        ];
    }

    /**
     * Write code on Method
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {

                $event->sheet->getDelegate()->getStyle('A1:P1')->getFont()->setBold(true);

                $event->sheet->getDelegate()->getRowDimension('1')->setRowHeight(30);

                $event->sheet->getDelegate()->getColumnDimension('A')->setWidth(11);
                $event->sheet->getDelegate()->getColumnDimension('B')->setWidth(11);
                $event->sheet->getDelegate()->getColumnDimension('C')->setWidth(11);
                $event->sheet->getDelegate()->getColumnDimension('D')->setWidth(11);
                $event->sheet->getDelegate()->getColumnDimension('E')->setWidth(11);
                $event->sheet->getDelegate()->getColumnDimension('F')->setWidth(11);
                $event->sheet->getDelegate()->getColumnDimension('G')->setWidth(11);
                $event->sheet->getDelegate()->getColumnDimension('H')->setWidth(11);
                $event->sheet->getDelegate()->getColumnDimension('I')->setWidth(11);
                $event->sheet->getDelegate()->getColumnDimension('J')->setWidth(11);
                $event->sheet->getDelegate()->getColumnDimension('K')->setWidth(11);
                $event->sheet->getDelegate()->getColumnDimension('L')->setWidth(11);
                $event->sheet->getDelegate()->getColumnDimension('M')->setWidth(11);
                $event->sheet->getDelegate()->getColumnDimension('N')->setWidth(11);
                $event->sheet->getDelegate()->getColumnDimension('O')->setWidth(11);
                $event->sheet->getDelegate()->getColumnDimension('P')->setWidth(11);

            },
        ];
    }
}
