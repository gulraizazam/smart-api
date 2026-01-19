<?php

namespace App\Exports;

use DateTime;
use App\Helpers\ACL;
use App\Models\Leads;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class ExportLead implements FromCollection, WithHeadings, WithEvents
{
    private $request;
    private $canViewContact;

    public function __construct($request)
    {
        $this->request = $request;
        $this->canViewContact = Gate::allows('contact');
    }

    /**
     * OPTIMIZED: Added eager loading to prevent N+1 queries
     */
    public function collection()
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

        $leads = $query->orderBy('id', 'DESC')->get();

        // Transform leads to rows (one row per service)
        $rows = collect();
        foreach ($leads as $lead) {
            $phone = $this->canViewContact ? ($lead->phone ?? 'N/A') : '***********';
            
            if ($lead->lead_service && $lead->lead_service->count() > 0) {
                foreach ($lead->lead_service as $service) {
                    $rows->push([
                        'id' => $lead->id,
                        'name' => $lead->name ?? 'N/A',
                        'phone' => $phone,
                        'gender' => $lead->gender == 1 ? 'Male' : 'Female',
                        'city' => $lead->city->name ?? 'N/A',
                        'centre' => $lead->towns->name ?? 'N/A',
                        'region' => $lead->region->name ?? 'N/A',
                        'lead_status' => $lead->lead_status->name ?? 'N/A',
                        'service' => $service->service->name ?? 'N/A',
                        'treatment' => $service->childservice->name ?? 'Empty',
                        'created_at' => Carbon::parse($lead->created_at)->format('F j,Y h:i A'),
                        'created_by' => $lead->user->name ?? 'N/A',
                    ]);
                }
            } else {
                $rows->push([
                    'id' => $lead->id,
                    'name' => $lead->name ?? 'N/A',
                    'phone' => $phone,
                    'gender' => $lead->gender == 1 ? 'Male' : 'Female',
                    'city' => $lead->city->name ?? 'N/A',
                    'centre' => $lead->towns->name ?? 'N/A',
                    'region' => $lead->region->name ?? 'N/A',
                    'lead_status' => $lead->lead_status->name ?? 'N/A',
                    'service' => 'N/A',
                    'treatment' => 'N/A',
                    'created_at' => Carbon::parse($lead->created_at)->format('F j,Y h:i A'),
                    'created_by' => $lead->user->name ?? 'N/A',
                ]);
            }
        }

        return $rows;
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

    /**
     * Style the sheet
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
