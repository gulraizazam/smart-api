<?php

declare(strict_types=1);

namespace App\Exports;

use App\Helpers\ACL;
use App\Models\Leads;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class ExportLead implements FromCollection, WithHeadings, WithEvents
{
    private readonly bool $canViewContact;

    public function __construct(
        private readonly mixed $request,
    ) {
        $this->canViewContact = Gate::allows('contact');
    }

    public function collection(): Collection
    {
        $userCities = ACL::getUserCities();
        $accountId = Auth::user()->account_id;

        $query = Leads::query()
            ->with([
                'lead_service' => fn($q) => $q->where('status', 1)->with(['service:id,name', 'childservice:id,name']),
                'city:id,name',
                'towns:id,name',
                'region:id,name',
                'lead_status:id,name',
                'user:id,name',
            ])
            ->where('account_id', $accountId)
            ->where(function ($q) use ($userCities): void {
                $q->whereIn('city_id', $userCities)
                  ->orWhereNull('city_id');
            });

        $this->applyFilters($query);

        if ($this->request->service_id) {
            $serviceId = $this->request->service_id;
            $query->whereHas('lead_service', fn($q) => $q->where('service_id', $serviceId)->where('status', 1));
        }

        $leads = $query->orderByDesc('id')->get();

        $rows = [];
        foreach ($leads as $lead) {
            $phone = $this->canViewContact ? ($lead->phone ?? 'N/A') : '***********';
            $baseRow = [
                $lead->id,
                $lead->name ?? 'N/A',
                $phone,
                $lead->gender == 1 ? 'Male' : 'Female',
                $lead->city?->name ?? 'N/A',
                $lead->towns?->name ?? 'N/A',
                $lead->region?->name ?? 'N/A',
                $lead->lead_status?->name ?? 'N/A',
            ];

            if ($lead->lead_service && $lead->lead_service->count() > 0) {
                foreach ($lead->lead_service as $service) {
                    $rows[] = [
                        ...$baseRow,
                        $service->service?->name ?? 'N/A',
                        $service->childservice?->name ?? 'Empty',
                        Carbon::parse($lead->created_at)->format('F j,Y h:i A'),
                        $lead->user?->name ?? 'N/A',
                    ];
                }
            } else {
                $rows[] = [
                    ...$baseRow,
                    'N/A',
                    'N/A',
                    Carbon::parse($lead->created_at)->format('F j,Y h:i A'),
                    $lead->user?->name ?? 'N/A',
                ];
            }
        }

        return collect($rows);
    }

    private function applyFilters(mixed $query): void
    {
        if ($this->request->created_at) {
            $dateRange = explode(' - ', $this->request->created_at);
            $startDate = date('Y-m-d 00:00:00', strtotime($dateRange[0]));
            $endDate = (new \DateTime($dateRange[1]))->setTime(23, 59, 59)->format('Y-m-d H:i:s');
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

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
            $value = $this->request->$requestKey;
            if ($value && $value !== 'undefined' && $value !== 'null') {
                $query->where($column, $value);
            }
        }

        if ($this->request->name) {
            $query->where('name', 'like', '%' . $this->request->name . '%');
        }
    }

    public function headings(): array
    {
        return [
            'ID', 'Full Name', 'Phone', 'Gender', 'City',
            'Centre', 'Region', 'Lead Status', 'Service',
            'Treatment', 'Created At', 'Created By',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:L1')->getFont()->setBold(true);
                $sheet->getRowDimension(1)->setRowHeight(30);

                foreach (range('A', 'L') as $col) {
                    $width = in_array($col, ['I']) ? 40 : 20;
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}
