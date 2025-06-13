<?php

namespace App\Exports;

use DateTime;
use App\Helpers\ACL;
use App\Models\Leads;
use App\Models\Membership;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class ExportMembership implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    private $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection()
    {
      
        $where = [];
        if (!is_null($this->request->assigned) && $this->request->assigned !== '') {
            if ($this->request->assigned == 1) {
                // patient_id is not null
                $where[] = ['memberships.patient_id', '<>', null];
            } elseif ($this->request->assigned == 0) {
                // patient_id is null
                $where[] = ['memberships.patient_id', '=', null];
            }
        }
        if ($this->request->membership_type_id != null || $this->request->membership_type_id != '') {
            $where[] = [['membership_type_id' => $this->request->membership_type_id]];
        }
        if ($this->request->code != null || $this->request->code != '') {
            $where[] = [['code' => $this->request->code]];
        }
       
        
        
        
        $result_query = Membership::where($where)->get();
        
       
       
        return $result_query;
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
        optional($membership->patient)->name ?? 'N/A',
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
