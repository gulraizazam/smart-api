<?php

namespace App\Exports;

use DateTime;
use App\Helpers\ACL;
use App\Models\Leads;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class ExportLead implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    private $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection()
    {
      
        $where = [];
        if ($this->request->created_at && $this->request->created_at != '') {
            $date_range = explode(' - ', $this->request->created_at);
            $start_date_time = date('Y-m-d H:i:s', strtotime($date_range[0]));
            $end_date_string = new DateTime($date_range[1]);
            $end_date_string->setTime(23, 59, 0);
            $end_date_time = $end_date_string->format('Y-m-d H:i:s');
        } else {
            $start_date_time = null;
            $end_date_time = null;
        }
        if ($this->request->id != null || $this->request->id != '') {
            $where[] = [['id' => $this->request->id]];
        }
        if ($this->request->lead_status_id != null || $this->request->lead_status_id != '') {
            $where[] = [['lead_status_id' => $this->request->lead_status_id]];
        }
        if ($this->request->city_id != null || $this->request->city_id != '') {
            $where[] = [['city_id' => $this->request->city_id]];
        }
        if ($this->request->location_id != null || $this->request->location_id != '') {
            $where[] = [['location_id' => $this->request->location_id]];
        }
        if ($this->request->region_id != null || $this->request->region_id != '') {
            $where[] = [['region_id' => $this->request->region_id]];
        }
        if ($this->request->created_by != null || $this->request->created_by != '') {
            $where[] = [['created_by' => $this->request->created_by]];
        }
        if ($this->request->phone != null || $this->request->phone != '') {
            $where[] = [['phone' => $this->request->phone]];
        }
        if ($this->request->gender_id != null || $this->request->gender_id != '') {
            $where[] = [['gender' => $this->request->gender_id]];
        }
        if ($this->request->name != null || $this->request->name != '') {
            $where[] = ['name', 'like', '%'.$this->request->name.'%'];
        }
        if ($this->request->created_at && $this->request->created_at != '') {
            $where[] = ['created_at', '>=', $start_date_time];
            $where[] = ['created_at', '<=', $end_date_time];
        }
        $result_query = Leads::whereIn('city_id', ACL::getUserCities());
        if (count($where)) {
            $result_query->where($where);
        }
        if ($this->request->service_id != null || $this->request->service_id != '') {
            $service_id = $this->request->service_id;
            $result_query->with(['lead_service' => function ($q) use ($service_id) {
                $q->where(['service_id' => $service_id, 'status' => 1]);
            }]);
        } else {
            $result_query->with(['lead_service' => function ($q) {
                $q->where(['status' => 1]);
            }]);
        }
        $result = $result_query->select('*', 'leads.created_by as lead_created_by', 'leads.id as lead_id', 'leads.created_at as lead_created_at')
            ->orderBy('id', 'DESC')->latest()->get()->unique('phone');

        return $result;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Full Name',
            'Phone',
            'Gender',
            'City',
            'Centre',
            'Region',
            'Lead Status',
            'Service',
            'Treatment',
            'Created At',
            'Created By',
        ];
    }

    public function map($lead): array
    {
       
        if (! Gate::allows('contact')) {
            $phone = '***********';
        } else {
            $phone = $lead->phone ?? 'N/A';
        }
        $lead_data = [];
        foreach ($lead->lead_service as $service) {
            $lead_data[] = [
                $lead->id,
                $lead->name ?? 'N/A',
                $phone,
                $lead->gender == 1 ? 'Male' : 'Female',
                $lead->city->name ?? 'N/A',
                $lead->towns->name ?? 'N/A',
                $lead->region->name ?? 'N/A',
                $lead->lead_status->name ?? 'N/A',
                $service->service->name ?? 'N/A',
                $service->childservice->name ?? 'Empty',
                Carbon::parse($lead->lead_created_at)->format('F j,Y h:i A') ?? 'N/A',
                $lead->user->name,
            ];
        }

        return $lead_data;
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
