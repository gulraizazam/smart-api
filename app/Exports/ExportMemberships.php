<?php

namespace App\Exports;


use App\Models\Packages;
use App\Models\Services;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class ExportMemberships implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    private $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection()
    {

        $where = [];
        $whereMembership = [];
        if ($this->request->date_range && $this->request->date_range != '') {
            $date_range = explode(' - ', $this->request->date_range);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        if ($this->request->location_id != null || $this->request->location_id != '') {
            $where[] = ['packages.location_id', '=', $this->request->location_id];
        }
        if ($this->request->membership_type_id != null || $this->request->membership_type_id != '') {
            $whereMembership[] = [['membership_type_id' => $this->request->membership_type_id]];
        }


        $serviceIds = Services::where('name', 'like', '%Gold Membership Card%')
            ->orWhere('name', 'like', '%Student Membership Card%')
            ->pluck('id')->toArray();

        $packagesWithServices = Packages::with([
            'user',
            'packageservice.service',
            'location',
            'user.membership.membershipType'
        ])
            ->whereHas('packageservice', function ($query) use ($serviceIds) {
                $query->whereIn('service_id', $serviceIds);
            })
            ->where($where)
            ->when(isset($this->request->membership_type_id), function ($query) use ($whereMembership) {
                if ($this->request->membership_type_id === "no_membership") {
                    $query->whereDoesntHave('user.membership');
                } else {
                    $query->whereHas('user.membership', function ($query) use ($whereMembership) {
                        $query->where($whereMembership);
                    });
                }
            })
            ->when($start_date && $end_date, function ($query) use ($start_date, $end_date) {
                $query->whereHas('user.membership', function ($query) use ($start_date, $end_date) {
                    $query->whereBetween('assigned_at', [$start_date, $end_date]);
                });
            })
            ->get();

        $users = $packagesWithServices->map(function ($package) use ($serviceIds) {
            $user = $package->user;
            $service = $package->packageservice->whereIn('service_id', $serviceIds)->first();
            $serviceName = $service->service->name;
            $location = $package->location;
            $membership = $user->membership;
            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'location' => $location->name,
                'service_name' => $serviceName,
                'service_status' => $service->is_consumed ? 'Consumed' : 'Not Consumed',
                'membership_code' => $membership ? $membership->code : 'No membership',
                'membership_type' => $membership ? $membership->membershipType->name : 'No membership',
                'membership_type_id' => $membership ? $membership->membershipType->id : 0,
                'assigned_at' => $membership ? $membership->assigned_at : null,
            ];
        });

        return $users;
    }

    public function headings(): array
    {
        return [
            'Patient ID',
            'Patient Name',
            'Location',
            'Membership Code',
            'Membership Type',
            'Service Status',

        ];
    }

    public function map($users): array
    {


        $user_data = [];


        $user_data[] = [
            $users['user_id'],
            $users['user_name'] ?? 'N/A',
            $users['location'] ?? 'N/A',
            $users['membership_code'] ?? 'N/A',
            $users['membership_type'] ?? 'N/A',
            $users['service_status'] ?? 'N/A',
        ];


        return $user_data;
    }

    /**
     * Write code on Method
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getStyle('A1:K1')->getFont()->setBold(true);
                $event->sheet->getDelegate()->getRowDimension('1')->setRowHeight(30);
                $event->sheet->getDelegate()->getColumnDimension('A')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('B')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('C')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('D')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('E')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('F')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('G')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('H')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('I')->setWidth(40);
                $event->sheet->getDelegate()->getColumnDimension('J')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('K')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('L')->setWidth(20);
            },
        ];
    }
}
