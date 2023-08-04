<?php

namespace App\Models;

use Auth;
use Carbon\Carbon;
use App\Helpers\ACL;
use App\Helpers\Filters;
use Illuminate\Http\Request;
use App\HelperModule\ApiHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Helpers\Invoice_Plan_Refund_Sms_Functions;

class Refunds extends Model
{
    use SoftDeletes;

    protected $fillable = ['cash_flow', 'cash_amount', 'active', 'patient_id', 'payment_mode_id', 'account_id', 'appointment_type_id', 'appointment_id', 'location_id', 'created_by', 'updated_by', 'created_at', 'updated_at', 'package_id', 'deleted_at', 'invoice_id', 'is_refund', 'refund_note', 'is_adjustment', 'is_tax','is_setteled'];

    protected static $_fillable = ['cash_flow', 'cash_amount', 'active', 'patient_id', 'payment_mode_id', 'appointment_type_id', 'appointment_id', 'location_id', 'created_by', 'updated_by', 'package_id', 'invoice_id', 'is_refund', 'refund_note', 'is_adjustment', 'is_tax'];

    protected $table = 'package_advances';

    protected static $_table = 'package_advances';

    /**
     * Get the user information that present in packages_advances.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'patient_id')->withTrashed();
    }

    /**
     * Create Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function createRecord($request, $id)
    {
        dd($request->all());
      
        /*Only for back date problem*/
        $package_advance_last_in = PackageAdvances::where([
            ['cash_flow', '=', 'in'],
            ['cash_amount', '>', 0],
            ['is_setteled', '=', '0'],
            ['package_id', '=', $request->package_id],
        ])->orderBy('created_at', 'desc')->first();
        $package_cash_receive = PackageAdvances::where([
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
            ['is_setteled', '=', '0'],
        ])->sum('cash_amount');
        $package_is_refunded_amount = PackageAdvances::where([
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'out'],
            ['is_refund', '=', '1'],
            ['is_tax', '=', '0'],
        ])->sum('cash_amount');
        $package_is_consumed_amount = PackageAdvances::where([
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'out'],
            ['is_refund', '=', '0'],
            ['is_tax', '=', '0'],
        ])->sum('cash_amount');
        $package_is_consumed_tax_amount = PackageAdvances::where([
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'out'],
            ['is_refund', '=', '0'],
            ['is_tax', '=', '1'],
        ])->sum('cash_amount');
        $consumed_amount_with_tax = $package_is_consumed_amount + $package_is_consumed_tax_amount;
       
        $remaining_amount = $package_cash_receive - $package_is_refunded_amount;
        // if($request->refund_amount  > $remaining_amount){
        //     return false;
        // }
       
        $custom_created_at = '';
        if ($request->created_at > $request->date_backend) {
            $custom_created_at = $request->created_at.' '.Carbon::now()->format('H:i:s');
        } elseif ($request->created_at === $request->date_backend) {
            $date_format_orignal_created = $request->created_at.' '.Carbon::now()->format('H:i:s');
            $date_format_orignal_in = $package_advance_last_in->created_at;
            if ($date_format_orignal_created > $date_format_orignal_in) {
                $custom_created_at = $date_format_orignal_created;
            } elseif ($date_format_orignal_created <= $date_format_orignal_in) {
                $custom_created_at = $date_format_orignal_in->addMinutes(2)->toDateTimeString();
            }
        }

        $packageinformation = Packages::find($request->package_id);

        $data = $request->all();

        $package_is_adjustment = PackageAdvances::where([
            ['package_id', '=', $packageinformation->id],
            ['is_adjustment', '=', '1'],
            ['cash_flow', '=', 'out'],
        ])->sum('cash_amount');
        // Set Account ID
        $data['cash_flow'] = 'out';
        $data['cash_amount'] = $request->get('refund_amount');
        $data['is_refund'] = '1';
        $data['patient_id'] = $request->get('patient_id');
        $data['payment_mode_id'] = $request->payment_mode_id;
        $data['account_id'] = $id;
        $data['created_by'] = Auth::User()->id;
        $data['updated_by'] = Auth::User()->id;
        $data['refund_note'] = $request->refund_note;
        $data['package_id'] = $request->package_id;
        $data['patient_id'] = $packageinformation->patient_id;
        $data['location_id'] = $packageinformation->location_id;

        $data['created_at'] = $custom_created_at;
        $data['updated_at'] = $custom_created_at;

        $record = self::create($data);

        // Here We sand the message of refund
        if ($record->cash_amount > 0) {
            Invoice_Plan_Refund_Sms_Functions::RefundCashReceived_SMS($record);
        }
        // End

        //log request for Create for Audit Trail

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        $packageinformation = Packages::find($request->package_id);

        if ($packageinformation->is_refund == '0') {
            $package = Packages::updateRecordRefunds($request->package_id);
        }
        if($request->case_setteled == "on"){
            $package_is_refunded_amount = PackageAdvances::where([
                ['package_id', '=', $request->package_id],
                ['cash_flow', '=', 'out'],
                ['is_refund', '=', '1'],
                ['is_tax', '=', '0'],
            ])->sum('cash_amount');
            $amount_after_refund = $consumed_amount_with_tax + $package_is_refunded_amount;
            $amount_left = $package_cash_receive - $amount_after_refund;
            
            if($amount_left > 0){
                
                $data_adjustment['cash_flow'] = 'in';
                $data_adjustment['cash_amount'] = $amount_left;
                $data_adjustment['is_adjustment'] = '0';
                $data_adjustment['is_setteled'] = 1;
                $data_adjustment['patient_id'] = $request->get('patient_id');
                $data_adjustment['payment_mode_id'] = $request->payment_mode_id;
                $data_adjustment['account_id'] = $id;
                $data_adjustment['created_by'] = Auth::User()->id;
                $data_adjustment['updated_by'] = Auth::User()->id;
                $data_adjustment['package_id'] = $request->package_id;
                $data_adjustment['patient_id'] = $packageinformation->patient_id;
                $data_adjustment['location_id'] = $packageinformation->location_id;

                $data_adjustment['created_at'] = $custom_created_at;
                $data_adjustment['updated_at'] = $custom_created_at;

                $record = self::create($data_adjustment);
            }
        }
        // if ($package_is_adjustment == '0') {

        //     $data_adjustment['cash_flow'] = 'out';
        //     $data_adjustment['cash_amount'] = $request->get('is_adjustment_amount');
        //     $data_adjustment['is_adjustment'] = '1';
        //     $data_adjustment['patient_id'] = $request->get('patient_id');
        //     $data_adjustment['payment_mode_id'] = '1';
        //     $data_adjustment['account_id'] = $id;
        //     $data_adjustment['created_by'] = Auth::User()->id;
        //     $data_adjustment['updated_by'] = Auth::User()->id;
        //     $data_adjustment['package_id'] = $request->package_id;
        //     $data_adjustment['patient_id'] = $packageinformation->patient_id;
        //     $data_adjustment['location_id'] = $packageinformation->location_id;

        //     $data_adjustment['created_at'] = $custom_created_at;
        //     $data_adjustment['updated_at'] = $custom_created_at;

        //     $record = self::create($data_adjustment);

        //     AuditTrails::addEventLogger(self::$_table, 'create', $data_adjustment, self::$_fillable, $record);
        
        //     $data_refund_tax['cash_flow'] = 'out';
        //     $data_refund_tax['cash_amount'] = $request->get('return_tax_amount');
        //     $data_refund_tax['is_tax'] = '1';
        //     $data_refund_tax['is_refund'] = '1';
        //     $data_refund_tax['patient_id'] = $request->get('patient_id');
        //     $data_refund_tax['payment_mode_id'] = '1';
        //     $data_refund_tax['account_id'] = $id;
        //     $data_refund_tax['created_by'] = Auth::User()->id;
        //     $data_refund_tax['updated_by'] = Auth::User()->id;
        //     $data_refund_tax['package_id'] = $request->package_id;
        //     $data_refund_tax['patient_id'] = $packageinformation->patient_id;
        //     $data_refund_tax['location_id'] = $packageinformation->location_id;

        //     $data_refund_tax['created_at'] = $custom_created_at;
        //     $data_refund_tax['updated_at'] = $custom_created_at;

        //     $record = self::create($data_refund_tax);

        //     AuditTrails::addEventLogger(self::$_table, 'create', $data_refund_tax, self::$_fillable, $record);
        // }

        return $record;
    }

    /**
     * Create Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function createRecordfornonplans($request, $id)
    {
        $package_advance_information = PackageAdvances::find($request->package_advance_id);

        /*Only for back date problem*/
        $package_advance_last_in = PackageAdvances::where([
            ['cash_flow', '=', 'in'],
            ['cash_amount', '>', 0],
            ['appointment_id', '=', $package_advance_information->appointment_id],
        ])->orderBy('created_at', 'desc')->first();
        /*end*/

        $custom_created_at = '';
        if ($request->created_at > $request->date_backend) {
            $custom_created_at = $request->created_at.' '.Carbon::now()->format('H:i:s');
        } elseif ($request->created_at === $request->date_backend) {
            $date_format_orignal_created = $request->created_at.' '.Carbon::now()->format('H:i:s');
            $date_format_orignal_in = $package_advance_last_in->created_at;
            if ($date_format_orignal_created > $date_format_orignal_in) {
                $custom_created_at = $date_format_orignal_created;
            } elseif ($date_format_orignal_created <= $date_format_orignal_in) {
                $custom_created_at = $date_format_orignal_in->addMinutes(2)->toDateTimeString();
            }
        }

        $package_advance_information = PackageAdvances::find($request->package_advance_id);
        $data = $request->all();
        // Set Account ID
        $data['cash_flow'] = 'out';
        $data['cash_amount'] = $request->get('refund_amount');
        $data['is_refund'] = '1';
        $data['refund_note'] = $request->refund_note;
        $data['patient_id'] = $request->get('patient_id');
        $data['payment_mode_id'] = '1';
        $data['account_id'] = $id;
        $data['created_by'] = $id;
        $data['updated_by'] = $id;
        $data['appointment_type_id'] = $package_advance_information->appointment_type_id;
        $data['appointment_id'] = $package_advance_information->appointment_id;
        $data['location_id'] = $package_advance_information->location_id;

        $data['created_at'] = $custom_created_at;
        $data['updated_at'] = $custom_created_at;

        $record = self::create($data);

        // Here We sand the message of refund
        if ($record->cash_amount > 0) {
            Invoice_Plan_Refund_Sms_Functions::RefundCashReceived_SMS($record);
        }
        // End

        //log request for Create for Audit Trail

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        $appointment_is_adjustment = PackageAdvances::where([
            ['patient_id', '=', $request->get('patient_id')],
            ['is_adjustment', '=', '1'],
            ['cash_flow', '=', 'out'],
            ['appointment_id', '=', $package_advance_information->appointment_id],
        ])->whereNull('package_id')->sum('cash_amount');

        if ($appointment_is_adjustment == '0') {

            $data_adjustment['cash_flow'] = 'out';
            $data_adjustment['cash_amount'] = $request->get('is_adjustment_amount');
            $data_adjustment['is_adjustment'] = '1';
            $data_adjustment['patient_id'] = $request->get('patient_id');
            $data_adjustment['payment_mode_id'] = '1';
            $data_adjustment['account_id'] = $id;
            $data_adjustment['created_by'] = $id;
            $data_adjustment['updated_by'] = $id;
            $data_adjustment['appointment_type_id'] = $package_advance_information->appointment_type_id;
            $data_adjustment['appointment_id'] = $package_advance_information->appointment_id;
            $data_adjustment['location_id'] = $package_advance_information->location_id;

            $data_adjustment['created_at'] = $custom_created_at;
            $data_adjustment['updated_at'] = $custom_created_at;

            $record = self::create($data_adjustment);

            AuditTrails::addEventLogger(self::$_table, 'create', $data_adjustment, self::$_fillable, $record);
        }

        return $record;
    }

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $id = false)
    {
        $where = [];

        if ($id != false) {
            $where[] = [
                'patient_id',
                '=',
                $id,
            ];
        }
        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
        }

        if ($request->get('patient_id')) {
            $where[] = [
                'patient_id',
                'like',
                '%'.$request->get('patient_id').'%',
            ];
        }

        if (count($where)) {
            return self::where($where)->distinct('patient_id')->count('patient_id');
        } else {
            return self::distinct('patient_id')->count('patient_id');
        }
    }

    /**
     * Get Records
     *
     * @param  (int)  $iDisplayStart Start Index
     * @param  (int)  $iDisplayLength Total Records Length
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $id = false)
    {
        $where = [];
        if ($id != false) {
            $where[] = [
                'patient_id',
                '=',
                $id,
            ];
        }
        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
        }

        if ($request->get('patient_id')) {
            $where[] = [
                'patient_id',
                'like',
                '%'.$request->get('patient_id').'%',
            ];
        }
        if (count($where)) {
            return self::where($where)->distinct()->groupby('patient_id')->limit($iDisplayLength)->offset($iDisplayStart)->get();
        } else {
            return self::distinct()->groupby('patient_id')->limit($iDisplayLength)->offset($iDisplayStart)->get();
        }
    }

    /**
     * Get Total Records for non plans refunds
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecordsnonplansrefunds(Request $request, $account_id = false, $id = false, $apply_filter = false)
    {

        $where = self::filters_nonPlanRefunds($request, $account_id, $id, $apply_filter);

        $nonplansrefundspatient = self::where($where)->whereNull('package_id')->groupby('appointment_id')->distinct('appointment_id')->get();
        $count = 0;
        $nonrefundspatient = [];

        foreach ($nonplansrefundspatient as $patient) {
            $appointment_info = Appointments::find($patient->appointment_id);
            if ($appointment_info && isset($appointment_info->location) && in_array($appointment_info->location->id, ACL::getUserCentres())) {
                $singlepatient_cash_in = self::where([
                    ['patient_id', '=', $patient->patient_id],
                    ['appointment_id', '=', $patient->appointment_id],
                    ['cash_flow', '=', 'in'],
                ])->whereNull('package_id')->sum('cash_amount');
                $singlepatient_cash_out = self::where([
                    ['patient_id', '=', $patient->patient_id],
                    ['appointment_id', '=', $patient->appointment_id],
                    ['cash_flow', '=', 'out'],
                ])->whereNull('package_id')->sum('cash_amount');

                if ($singlepatient_cash_in - $singlepatient_cash_out != 0) {
                    $nonrefundspatient[] = $patient;
                    $count++;
                }
            }
        }

        return [
            'iTotalRecords' => $count,
            'nonplansrefunds' => $nonrefundspatient,
        ];
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
    {
        //        if (
        //        InvoiceDetails::where(['package_id' => $id])->count()
        //        ) {
        //            return true;
        //        }
        //
        //        return false;
    }

    public static function filters_nonPlanRefunds($request, $account_id, $id, $apply_filter)
    {
        $where = [];

        $filters = getFilters($request->all());

        if ($id != false) {
            $where[] = [
                'patient_id',
                '=',
                $id,
            ];
        }
        if (hasFilter($filters, 'patient_id')) {
            $where[] = [
                'patient_id',
                '=',
                $filters['patient_id'],
            ];
            Filters::put(Auth::user()->id, 'nonplansrefunds', 'patient_id', $filters['patient_id']);
            Filters::put(Auth::user()->id, 'nonplansrefunds', 'patient_name', str_replace('undefined', '', $filters['patient_name']));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'nonplansrefunds', 'patient_id');
                Filters::forget(Auth::user()->id, 'nonplansrefunds', 'patient_name');
            } else {
                if (Filters::get(Auth::user()->id, 'nonplansrefunds', 'patient_id')) {
                    $where[] = [
                        'patient_id',
                        '=',
                        Filters::get(Auth::user()->id, 'nonplansrefunds', 'patient_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'id')) {
            $where[] = [
                'patient_id',
                '=',
                \App\Helpers\GeneralFunctions::patientSearch($filters['id']),
            ];
            Filters::put(Auth::user()->id, 'nonplansrefunds', 'id', \App\Helpers\GeneralFunctions::patientSearch($filters['id']));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'nonplansrefunds', 'id');
            } else {
                if (Filters::get(Auth::user()->id, 'nonplansrefunds', 'id')) {
                    $where[] = [
                        'patient_id',
                        '=',
                        Filters::get(Auth::user()->id, 'nonplansrefunds', 'id'),
                    ];
                }
            }
        }

        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::user()->id, 'nonplansrefunds', 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'nonplansrefunds', 'account_id', $account_id);
            } else {
                if (Filters::get(Auth::user()->id, 'nonplansrefunds', 'account_id', $account_id)) {
                    $where[] = [
                        'account_id',
                        '=',
                        Filters::get(Auth::user()->id, 'nonplansrefunds', 'account_id', $account_id),
                    ];
                }
            }
        }

        return $where;
    }
}
