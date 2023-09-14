<?php

namespace App\Exports;

use App\Helpers\ACL;
use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

class ExportConsultancies implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    private $limit = 10000;

    private $offset = 0;

    public function __construct($limit, $offset, $request)
    {
        $this->limit = $limit;
        $this->offset = $offset;
        $this->request = $request;
    }

    public function collection()
    {
        DB::enableQueryLog();
        $where = [];
        if ($this->request->filter_date_from) {
            $where[] = [
                'appointments.scheduled_date',
                '>=',
                $this->request->filter_date_from,
            ];
        }

        if ($this->request->filter_date_to) {
            $where[] = [
                'appointments.scheduled_date',
                '<=',
                $this->request->filter_date_to,
            ];
        }
        if ($this->request->appointmenttype) {
            $where[] = [
                'appointment_type_id',
                '=',
                $this->request->appointmenttype,
            ];
        }
        if ($this->request->filter_doctor_id) {
            $where[] = [
                'doctor_id',
                '=',
                $this->request->filter_doctor_id,
            ];
        }
        if ($this->request->filter_status_id) {
            $where[] = [
                'match' => [
                    'base_appointment_status_id' => $this->request->filter_status_id,
                ],
            ];
        }
        if ($this->request->filter_created_by_id) {
            $where[] = [
                'created_by',
                '=',
                $this->request->filter_created_by_id,
            ];
        }
        if ($this->request->filter_center_id) {
            $where[] = [
                'location_id',
                '=',
                $this->request->filter_center_id,
            ];
        }
        if ($this->request->filter_patient_id) {
            $where[] = [
                'patient_id',
                '=',
                $this->request->filter_patient_id,
            ];
        }
        if ($this->request->filter_city_id) {
            $where[] = [
                'city_id',
                '=',
                $this->request->filter_city_id,
            ];
        }
        if ($this->request->filter_region_id) {
            $where[] = [
                'region_id',
                '=',
                $this->request->filter_region_id,
            ];
        }
        if ($this->request->filter_region_id) {
            $where[] = [
                'region_id',
                '=',
                $this->request->filter_region_id,
            ];
        }
        if ($this->request->filter_consultancytype_id) {
            $where[] = [
                'consultancy_type',
                '=',
                $this->request->filter_consultancytype_id,
            ];
        }
        if ($this->request->filter_updated_by_id) {
            $where[] = [
                'updated_by',
                '=',
                $this->request->filter_updated_by_id,
            ];
        }
        if ($this->request->filter_rescheduled_by_id) {
            $where[] = [
                'converted_by',
                '=',
                $this->request->filter_rescheduled_by_id,
            ];
        }
        if ($this->request->filter_created_from_id) {
            $where[] = [
                'appointments.created_at',
                '>=',
                $this->request->filter_created_from_id.' 00:00:00',
            ];
        }
        if ($this->request->filter_created_to_id) {
            $where[] = [
                'appointments.created_at',
                '<=',
                $this->request->filter_created_to_id.' 23:59:59',
            ];
        }
        
        if ($this->request->filter_service_id && $this->request->filter_service_id != 13) {
            $where[] = [
                'appointments.service_id',
                '=',
                $this->request->filter_service_id,
            ];
        }
        if ($this->request->filter_phone) {
            $phone = substr($this->request->filter_phone, 1);
            $where[] = [
                'users.phone',
                '=',
                $phone,
            ];
        }
       
        $results = Appointments::join('users', 'users.id', '=', 'appointments.patient_id')
        ->select('appointments.*','users.name','users.phone')
            ->where(['users.user_type_id' => config('constants.patient_id')])
            ->whereIn('appointments.city_id', ACL::getUserCities())
            ->whereIn('appointments.location_id', ACL::getUserCentres())
            ->where($where)
            ->when(count($where) >! 1, function($q){
                return $q->take($this->limit);
            })
            ->orderBy('scheduled_time','asc')
            ->get();
        
           
        return $results;
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
            //GeneralFunctions::patientSearchStringAdd($appointment->id),
            'C-'.$appointment->patient_id,
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
