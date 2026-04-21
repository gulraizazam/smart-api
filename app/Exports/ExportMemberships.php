<?php

declare(strict_types=1);

namespace App\Exports;

use App\Helpers\ACL;
use App\Services\Reports\Concerns\ParsesDateRange;
use App\Services\Reports\MembershipReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

class ExportMemberships implements FromCollection, WithEvents, WithHeadings, WithMapping
{
    use ParsesDateRange;

    public function __construct(
        private readonly MembershipReportService $reportService,
        private readonly Request $request,
    ) {}

    public function collection(): Collection
    {
        [$startDate, $endDate] = self::parseDateRange($this->request->date_range ?: null);

        $rawLocation = $this->request->location_id;
        $locationIds = ! empty($rawLocation)
            ? array_values(array_filter(array_map('intval', (array) $rawLocation), fn (int $id): bool => $id > 0))
            : [];
        if (empty($locationIds)) {
            $locationIds = array_map('intval', ACL::getUserCentres());
        }
        $membershipTypeId = ($this->request->membership_type_id !== null && $this->request->membership_type_id !== '')
            ? $this->request->membership_type_id
            : null;

        return $this->reportService->generate(
            locationIds: $locationIds,
            membershipTypeId: $membershipTypeId,
            startDate: $startDate,
            endDate: $endDate,
        );
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

    public function map($row): array
    {
        // Bug fixed: was returning [[...]] (double-wrapped) — each row rendered as a single cell
        return [
            $row['user_id'],
            $row['user_name'] ?? 'N/A',
            $row['location'] ?? 'N/A',
            $row['membership_code'] ?? 'N/A',
            $row['membership_type'] ?? 'N/A',
            $row['service_status'] ?? 'N/A',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:F1')->getFont()->setBold(true);
                $sheet->getRowDimension(1)->setRowHeight(30);

                foreach (range('A', 'F') as $col) {
                    $sheet->getColumnDimension($col)->setWidth(20);
                }
            },
        ];
    }
}
