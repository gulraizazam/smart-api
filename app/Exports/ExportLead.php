<?php


namespace App\Exports;
use App\Helpers\ACL;
use App\Helpers\GeneralFunctions;
use App\Models\Leads;
use App\Models\LeadStatuses;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;

class ExportLead implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    private $limit = 1000;
    private $offset = 0;

    public function __construct($limit = 1000, $offset = 0)
    {
        $this->limit = $limit;
        $this->offset = $offset;
    }

    public function collection()
    {
        $resultQuery = Leads::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', '=', Config::get('constants.patient_id'))
            ->where(function ($query) {
                $query->whereIn('leads.city_id', ACL::getUserCities());
                $query->orWhereNull('leads.city_id');
            });

        $junk_lead_statuses = LeadStatuses::where(array(
            'account_id' => Auth::User()->account_id,
            'is_junk' => 1,
        ))->first();

        if (request()->has('type')) {

            $resultQuery->where('leads.lead_status_id', $junk_lead_statuses->id ?? 0);

        } else {
            $resultQuery->where('leads.lead_status_id', '!=', $junk_lead_statuses->id ?? 0);
        }

       return $resultQuery->limit($this->limit)->offset($this->offset)
           ->select('*', 'leads.created_by as lead_created_by', 'leads.id as lead_id', 'leads.created_at as lead_created_at', 'users.id as PatientId')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Full Name',
            'Phone',
            'City',
            'Region',
            'Lead Status',
            'Service',
            'Created At',
            'Created By',
        ];
    }

    public function map($lead): array
    {
        if (!Gate::allows('contact')) {
            $phone = '***********';
        } else {
            $phone = $lead->phone ?? 'N/A';
        }
        return [
            GeneralFunctions::patientSearchStringAdd($lead->id),
            $lead->name ?? 'N/A',
            $phone,
            $lead->city->name ?? 'N/A',
            $lead->region->name ?? 'N/A',
            $lead->lead_status->name ?? 'N/A',
            $lead->service->name ?? 'N/A',
            Carbon::parse($lead->lead_created_at)->format('F j,Y h:i A') ?? 'N/A',
            $lead->user->name?? 'N/A',
        ];
    }

    /**
     * Write code on Method
     *
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {

                $event->sheet->getDelegate()->getStyle('A1:I1')->getFont()->setBold(true);

                $event->sheet->getDelegate()->getRowDimension('1')->setRowHeight(30);

                $event->sheet->getDelegate()->getColumnDimension('A')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('B')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('C')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('D')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('E')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('F')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('G')->setWidth(20);
                $event->sheet->getDelegate()->getColumnDimension('H')->setWidth(40);
                $event->sheet->getDelegate()->getColumnDimension('I')->setWidth(20);

            },
        ];
    }

}
