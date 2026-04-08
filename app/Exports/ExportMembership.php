<?php

declare(strict_types=1);
namespace App\Exports;

use App\Models\Membership;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class ExportMembership implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    public function __construct(
        private readonly mixed $request,
    ) {}

    public function collection(): \Illuminate\Support\Collection
    {
        $query = Membership::query();

        // Bug fixed: was using ['<>', null] / ['=', null] in array WHERE — those don't generate IS NULL / IS NOT NULL
        if ($this->request->assigned !== null && $this->request->assigned !== '') {
            if ($this->request->assigned == 1) {
                $query->whereNotNull('memberships.patient_id');
            } elseif ($this->request->assigned == 0) {
                $query->whereNull('memberships.patient_id');
            }
        }

        // Bug fixed: was `!= null || != ''` (always true) — changed to `!== null && !== ''`
        // Bug fixed: was [['membership_type_id' => value]] (double-nested, malformed) — fixed to standard tuple
        if ($this->request->membership_type_id !== null && $this->request->membership_type_id !== '') {
            $query->where('membership_type_id', '=', $this->request->membership_type_id);
        }

        if ($this->request->code !== null && $this->request->code !== '') {
            $query->where('code', '=', $this->request->code);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Code',
            'Membership Type',
            'Patient',
            'Start Date',
            'End Date',
        ];
    }

    public function map($membership): array
    {
        return [
            $membership->id,
            $membership->code ?? 'N/A',
            $membership->membership_type_id == '3' ? 'Gold' : 'Student',
            $membership->patient?->name ?? 'N/A',
            $membership->start_date ?? 'N/A',
            $membership->end_date ?? 'N/A',
        ];
    }

    /**
     * Write code on Method
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getStyle('A1:F1')->getFont()->setBold(true);
                $event->sheet->getDelegate()->getRowDimension(1)->setRowHeight(30);
                $event->sheet->getDelegate()->getColumnDimension('A')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('B')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('C')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('D')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('E')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('F')->setWidth(20);
            },
        ];
    }
}
