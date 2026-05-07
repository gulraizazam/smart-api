<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Reusable Excel export for tabular reports — feed it the headings and
 * already-flattened row arrays and you get a styled `.xlsx` back. Saves
 * each report from owning a one-off Maatwebsite class.
 *
 * Optional `totalsRow` renders a bold row directly below the table (in
 * heading order). Optional `summaryBlock` renders a key/value breakdown a
 * couple of rows further down — used by sales reports to surface the
 * Cash / Card / Bank / Refund / Net split that the on-screen panel shows.
 */
class GenericTableExport implements FromCollection, WithEvents, WithHeadings
{
    /**
     * @param  string[]  $headings
     * @param  array<int, array<int, scalar|null>>  $rows  flat row arrays in heading order
     * @param  array<int, scalar|null>|null  $totalsRow  optional totals row, in heading order
     * @param  array<int, array{label: string, value: scalar|null, bold?: bool}>|null  $summaryBlock
     */
    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
        private readonly ?array $totalsRow = null,
        private readonly ?array $summaryBlock = null,
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
        $lastCol = $count > 0 ? Coordinate::stringFromColumnIndex($count) : 'A';
        $totalsRow = $this->totalsRow;
        $summaryBlock = $this->summaryBlock;
        $dataRowCount = count($this->rows);

        return [
            AfterSheet::class => function (AfterSheet $event) use ($lastCol, $count, $totalsRow, $summaryBlock, $dataRowCount) {
                $sheet = $event->sheet->getDelegate();
                if ($count > 0) {
                    $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
                    $sheet->getRowDimension(1)->setRowHeight(28);
                    for ($i = 1; $i <= $count; $i++) {
                        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
                    }
                }

                $cursor = 1 + $dataRowCount + 1; // headings row + data rows, then 1-based next row

                if ($totalsRow !== null) {
                    foreach ($totalsRow as $i => $cell) {
                        $col = Coordinate::stringFromColumnIndex($i + 1);
                        $sheet->setCellValue("{$col}{$cursor}", $cell);
                    }
                    $sheet->getStyle("A{$cursor}:{$lastCol}{$cursor}")->getFont()->setBold(true);
                    $cursor++;
                }

                if (! empty($summaryBlock)) {
                    $cursor++; // blank spacer row
                    foreach ($summaryBlock as $entry) {
                        $sheet->setCellValue("A{$cursor}", $entry['label']);
                        $sheet->setCellValue("B{$cursor}", $entry['value']);
                        if (! empty($entry['bold'])) {
                            $sheet->getStyle("A{$cursor}:B{$cursor}")->getFont()->setBold(true);
                        } else {
                            $sheet->getStyle("A{$cursor}")->getFont()->setBold(true);
                        }
                        $cursor++;
                    }
                }
            },
        ];
    }
}
