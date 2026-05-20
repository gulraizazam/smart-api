<?php

declare(strict_types=1);

namespace App\Exports;

use App\Services\Lead\LeadService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        private readonly LeadService $leadService,
    ) {
        $this->canViewContact = Gate::allows('contact');
    }

    public function collection(): Collection
    {
        $datatableData = $this->leadService->getDatatableData($this->request->all(), null);

        $query = $datatableData['query'];

        if (! Gate::allows('leads.list.view_inactive')) {
            $query->where('leads.active', 1);
        }

        $leads = $query
            ->with([
                'region:id,name',
                'lead_status:id,name',
                'user:id,name',
            ])
            ->orderBy($datatableData['orderBy'], $datatableData['order'])
            ->get();

        $rows = [];
        foreach ($leads as $lead) {
            $row = [
                $lead->id,
                $lead->name ?? 'N/A',
            ];

            if ($this->canViewContact) {
                $row[] = $lead->phone ?? 'N/A';
            }

            $services = $lead->lead_service ?? collect();
            $serviceNames = $services
                ->map(fn($s): string => $s->service?->name ?? 'N/A')
                ->filter()
                ->unique()
                ->implode(', ');
            $treatmentNames = $services
                ->map(fn($s): ?string => $s->childservice?->name)
                ->filter()
                ->unique()
                ->implode(', ');

            $rows[] = [
                ...$row,
                $lead->gender == 1 ? 'Male' : 'Female',
                $lead->city?->name ?? 'N/A',
                $lead->towns?->name ?? 'N/A',
                $lead->region?->name ?? 'N/A',
                $lead->lead_status?->name ?? 'N/A',
                $serviceNames !== '' ? $serviceNames : 'N/A',
                $treatmentNames !== '' ? $treatmentNames : 'N/A',
                Carbon::parse($lead->created_at)->format('F j,Y h:i A'),
                $lead->user?->name ?? 'N/A',
            ];
        }

        return collect($rows);
    }

    public function headings(): array
    {
        $headings = [
            'ID', 'Full Name', 'Phone', 'Gender', 'City',
            'Centre', 'Region', 'Lead Status', 'Service',
            'Treatment', 'Created At', 'Created By',
        ];

        if (! $this->canViewContact) {
            $headings = array_values(array_filter($headings, fn (string $h): bool => $h !== 'Phone'));
        }

        return $headings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $columnCount = $this->canViewContact ? 12 : 11;
                $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);
                $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
                $sheet->getRowDimension(1)->setRowHeight(30);

                $serviceColumn = $this->canViewContact ? 'I' : 'H';
                foreach (range('A', $lastColumn) as $col) {
                    $width = $col === $serviceColumn ? 40 : 20;
                    $sheet->getColumnDimension($col)->setWidth($width);
                }
            },
        ];
    }
}
