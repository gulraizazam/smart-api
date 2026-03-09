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
        
        /*Only for back date problem*/
        $check_is_setteled = PackageAdvances::where([
            ['cash_flow', '=', 'out'],
            ['cash_amount', '>', 0],
            ['is_setteled', '=', '1'],
            ['package_id', '=', $request->package_id],
        ])->first();
        if($check_is_setteled){
            return 'setteled';
        }
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
        if($request->refund_amount  > $package_cash_receive){
            return 'amountexceed';
        }
       
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
        } else {
            // Back date entry - use the provided date with current time
            $custom_created_at = $request->created_at.' '.Carbon::now()->format('H:i:s');
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
        $data['appointment_id'] = $packageinformation->appointment_id;
        $data['created_at'] = $custom_created_at;
        $data['updated_at'] = $custom_created_at;

        $record = self::create($data);
        $patient = User::whereId($packageinformation->patient_id)->first();
        $location = Locations::whereId($packageinformation->location_id)->first();
        
        // Build activity description with refund details
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $locationName = $location->name ?? '';
        $refundAmount = $request->refund_amount;
        $refundDate = $request->created_at ? date('M j, Y', strtotime($request->created_at)) : date('M j, Y');
        $caseSetteled = $request->case_setteled == "1";
        
        $description = '<span class="highlight">' . $creatorName . '</span> refunded <span class="highlight-green">Rs. ' . number_format($refundAmount) . '</span> to <span class="highlight-orange">' . $patientName . '</span> for <span class="highlight-purple">Plan #' . sprintf('%05d', $request->package_id) . '</span>' . ($locationName ? ' in <span class="highlight">' . $locationName . '</span>' : '') . ' on <span class="highlight-purple">' . $refundDate . '</span>';
        
        if ($caseSetteled) {
            $description .= ' - <span class="highlight-green">Case Settled</span>';
        }
        
        $activity = new Activity();
        $activity->timestamps = false;
        $activity->action = 'refunded';
        $activity->activity_type = 'refund_made';
        $activity->description = $description;
        $activity->patient = $patientName;
        $activity->patient_id = $patient->id;
        $activity->appointment_type = 'Plan';
        $activity->created_by = Auth::user()->id;
        $activity->planId = $request->package_id;
        $activity->amount = $refundAmount;
        $activity->location = $locationName;
        $activity->centre_id = $request->location_id;
        $activity->account_id = Auth::user()->account_id;
        $activity->created_at = Filters::getCurrentTimeStamp();
        $activity->updated_at = Filters::getCurrentTimeStamp();
        $activity->save();
        // Here We sand the message of refund
        if ($record->cash_amount > 0) {
            Invoice_Plan_Refund_Sms_Functions::RefundCashReceived_SMS($record);
        }
        // End

        //log request for Create for Audit Trail

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        $packageinformation = Packages::find($request->package_id);
        $services = Services::where('name','Refund Settelment')->first();
        if ($packageinformation->is_refund == '0') {
            $package = Packages::updateRecordRefunds($request->package_id);
        }

        // Always regenerate plan_name from bundles/services on refund
        self::regeneratePlanName($packageinformation);
        if($request->case_setteled == "1"){
            $package_is_refunded_amount = PackageAdvances::where([
                ['package_id', '=', $request->package_id],
                ['cash_flow', '=', 'out'],
                ['is_refund', '=', '1'],
                ['is_tax', '=', '0'],
            ])->sum('cash_amount');
            $amount_after_refund = $consumed_amount_with_tax + $package_is_refunded_amount;
            $amount_left = $package_cash_receive - $amount_after_refund;
            $find_doc = Appointments::where('id',$packageinformation->appointment_id)->first();
            
                
                $data_adjustment['cash_flow'] = 'out';
                $data_adjustment['cash_amount'] = $amount_left;
                $data_adjustment['is_adjustment'] = '0';
                $data_adjustment['is_setteled'] = 1;
                $data_adjustment['patient_id'] = $request->get('patient_id');
                $data_adjustment['payment_mode_id'] = 5;
                $data_adjustment['account_id'] = $id;
                $data_adjustment['created_by'] = Auth::User()->id;
                $data_adjustment['updated_by'] = Auth::User()->id;
                $data_adjustment['package_id'] = $request->package_id;
                $data_adjustment['patient_id'] = $packageinformation->patient_id;
                $data_adjustment['location_id'] = $packageinformation->location_id;
                $data_adjustment['appointment_id'] = $packageinformation->appointment_id;
                $data_adjustment['created_at'] = $custom_created_at;
                $data_adjustment['updated_at'] = $custom_created_at;
                $record = self::create($data_adjustment);
                if($amount_left > 0){
                    $dataInvoice['total_price'] = $amount_left;
                    $dataInvoice['account_id'] = Auth::User()->account_id;
                    $dataInvoice['patient_id'] = $packageinformation->patient_id;
                    $dataInvoice['appointment_id'] = $packageinformation->appointment_id;
                    $dataInvoice['invoice_status_id'] = 3;
                    $dataInvoice['created_by'] = Auth::User()->id;
                    $dataInvoice['location_id'] =$packageinformation->location_id;
                    $dataInvoice['doctor_id'] =$find_doc->doctor_id;
                    $dataInvoice['active'] = 1;
                    $dataInvoice['is_exclusive'] = 0;
                    $dataInvoice['is_settlement'] = 1;
                    $dataInvoice['package_id'] = $request->package_id;
                    $create_invoice =  Invoices::create($dataInvoice);
                    $dataInvoiceDetail['qty'] = 1;
                    $dataInvoiceDetail['service_id'] =$services->id;
                    $dataInvoiceDetail['invoice_id'] = $create_invoice->id;
                    $dataInvoiceDetail['is_settlement'] = 1;
                    InvoiceDetails::create($dataInvoiceDetail);
                }
            }
            return $record;
    }


    /**
     * Regenerate plan_name for a package from its bundles/services.
     */
    private static function regeneratePlanName(Packages $package): void
    {
        if ($package->plan_type === 'membership') {
            $membershipNames = PackageBundles::where('package_bundles.package_id', $package->id)
                ->join('membership_types', 'package_bundles.membership_type_id', '=', 'membership_types.id')
                ->orderBy('package_bundles.id', 'asc')
                ->limit(2)
                ->pluck('membership_types.name')
                ->toArray();

            if (!empty($membershipNames)) {
                Packages::where('id', $package->id)->update(['plan_name' => implode(', ', $membershipNames)]);
            }
            return;
        }

        $totalBundleCount = PackageBundles::where('package_id', $package->id)->count();

        if ($package->plan_type === 'plan') {
            $names = PackageBundles::where('package_bundles.package_id', $package->id)
                ->join('services', 'package_bundles.bundle_id', '=', 'services.id')
                ->orderBy('package_bundles.id', 'asc')
                ->limit(2)
                ->pluck('services.name')
                ->toArray();
        } else {
            $names = PackageBundles::where('package_bundles.package_id', $package->id)
                ->join('bundles', 'package_bundles.bundle_id', '=', 'bundles.id')
                ->orderBy('package_bundles.id', 'asc')
                ->limit(2)
                ->pluck('bundles.name')
                ->toArray();
        }

        $planName = !empty($names) ? implode(', ', $names) : '-';

        if ($package->plan_type === 'plan' && $totalBundleCount > 2) {
            $planName .= '...';
        }

        Packages::where('id', $package->id)->update(['plan_name' => $planName]);
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

}
