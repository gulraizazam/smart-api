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
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;

class ExportLead implements FromQuery, WithHeadings, WithMapping, WithEvents
{
    use Exportable;

    private $request;
    private $canViewContact;

    public function __construct($request)
    {
        $this->request = $request;
        $this->canViewContact = Gate::allows('contact');
    }

    /**
     * OPTIMIZED: Use FromQuery instead of FromCollection for memory efficiency
     * Query is executed in chunks automatically by Laravel Excel
     */
    public function query()
    {
        $query = Leads::query()
            ->with([
                'lead_service' => fn($q) => $q->where('status', 1)->with(['service:id,name', 'childservice:id,name']),
                'city:id,name',
                'towns:id,name',
                'region:id,name',
                'lead_status:id,name',
                'user:id,name',
            ])
            ->whereIn('city_id', ACL::getUserCities());

        $this->applyFilters($query);

        // Service filter
        if ($this->request->service_id) {
            $serviceId = $this->request->service_id;
            $query->whereHas('lead_service', fn($q) => $q->where('service_id', $serviceId)->where('status', 1));
        }

        return $query->select([
            'id', 'name', 'phone', 'gender', 'city_id', 'location_id', 
            'region_id', 'lead_status_id', 'created_by', 'created_at'
        ])->orderBy('id', 'DESC');
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query): void
    {
        // Date range filter
        if ($this->request->created_at) {
            $dateRange = explode(' - ', $this->request->created_at);
            $startDate = date('Y-m-d 00:00:00', strtotime($dateRange[0]));
            $endDate = (new DateTime($dateRange[1]))->setTime(23, 59, 59)->format('Y-m-d H:i:s');
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        // Simple exact match filters
        $exactFilters = [
            'id' => 'id',
            'lead_status_id' => 'lead_status_id',
            'city_id' => 'city_id',
            'location_id' => 'location_id',
            'region_id' => 'region_id',
            'created_by' => 'created_by',
            'phone' => 'phone',
            'gender_id' => 'gender',
        ];

        foreach ($exactFilters as $requestKey => $column) {
            if ($this->request->$requestKey) {
                $query->where($column, $this->request->$requestKey);
            }
        }

        // Name filter (LIKE)
        if ($this->request->name) {
            $query->where('name', 'like', '%' . $this->request->name . '%');
        }
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
        $phone = $this->canViewContact ? ($lead->phone ?? 'N/A') : '***********';
        
        // If lead has services, create a row for each service
        if ($lead->lead_service && $lead->lead_service->count() > 0) {
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
                    Carbon::parse($lead->created_at)->format('F j,Y h:i A'),
                    $lead->user->name ?? 'N/A',
                ];
            }
            return $lead_data;
        }

        // Lead without services - single row
        return [[
            $lead->id,
            $lead->name ?? 'N/A',
            $phone,
            $lead->gender == 1 ? 'Male' : 'Female',
            $lead->city->name ?? 'N/A',
            $lead->towns->name ?? 'N/A',
            $lead->region->name ?? 'N/A',
            $lead->lead_status->name ?? 'N/A',
            'N/A',
            'N/A',
            Carbon::parse($lead->created_at)->format('F j,Y h:i A'),
            $lead->user->name ?? 'N/A',
        ]];
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
