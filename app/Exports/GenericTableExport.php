<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Reusable Excel export for tabular reports — feed it the headings and
 * already-flattened row arrays and you get a styled `.xlsx` back. Saves
 * each report from owning a one-off Maatwebsite class.
 */
class GenericTableExport implements FromCollection, WithEvents, WithHeadings
{
    /**
     * @param  string[]  $headings
     * @param  array<int, array<int, scalar|null>>  $rows  flat row arrays in heading order
     */
    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
    ) {}

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function registerEvents(): array
    {
        $count = count($this->headings);
        $lastCol = $count > 0 ? chr(ord('A') + $count - 1) : 'A';
        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol, $count) {
                $sheet = $event->sheet->getDelegate();
                if ($count > 0) {
                    $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
                    $sheet->getRowDimension(1)->setRowHeight(28);
                    foreach (range('A', $lastCol) as $col) {
                        $sheet->getColumnDimension($col)->setAutoSize(true);
                    }
                }
            },
        ];
    }
}
