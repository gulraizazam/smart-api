<?php

namespace App\Models;

use App\Helpers\Filters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use App\Models\AuditTrails;
use Auth;
use DB;
use App\Helpers\ACL;

class Invoices extends Model
{
    use SoftDeletes;

    protected $fillable = ['total_price', 'account_id', 'patient_id', 'appointment_id', 'invoice_status_id', 'active','is_exclusive', 'created_at', 'updated_at', 'deleted_at','created_by','location_id','doctor_id'];

    protected static $_fillable = ['total_price', 'account_id', 'patient_id', 'appointment_id', 'invoice_status_id', 'active','is_exclusive', 'created_at', 'updated_at', 'deleted_at','created_by','location_id','doctor_id'];

    protected $table = 'invoices';

    protected static $_table = 'invoices';

    /*Get the invoice status data*/
    public function invoicestatus()
    {
        return $this->belongsTo('App\Models\InvoiceStatuses', 'invoice_status_id')->withTrashed();
    }

    /*Get the user data*/
    public function user()
    {
        return $this->belongsTo('App\User', 'patient_id')->withTrashed();
    }

    /**
     * Get the package advances information.
     */
    public function packageadvance()
    {

        return $this->hasMany('App\Models\PackageAdvances', 'invoice_id');
    }
    /**
     * Create Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function createRecord($data)
    {
        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        return $record;
    }

    /*
     * Get the appointments of the invoices
     * */

    public function toAppointment()
    {
        return $this->belongsTo(Appointments::class, 'id');
    }

    /**
     * Cancel Record
     *
     * @param $id
     *
     * @return (mixed)
     */
    static public function CancelRecord($id,$account_id){

        $old_data = (Invoices::find($id))->toArray();

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id
        ])->first();

        if (!$record) {
            return null;
        }
        $invoicestatus = InvoiceStatuses::where('slug','=','cancelled')->first();

        $record->update(['invoice_status_id' => $invoicestatus->id]);

        $data = (Invoices::find($id))->toArray();

        AuditTrails::EditEventLogger(self::$_table, 'cancel', $data, self::$_fillable, $old_data, $id);

        return $record;

    }
    /**
     * Get Total Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getTotalRecords(Request $request, $account_id = false,$id = false, $apply_filter = false, $filename)
    {
        $where = self::filters_invoices( $request, $account_id , $id , $apply_filter, $filename );

        if (count($where)) {
            return DB::table('appointments')
                ->join('invoices', 'appointments.id','=','invoices.appointment_id')
                ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->where($where)
                ->whereIn('invoices.location_id',ACL::getUserCentres())
                ->whereNull('invoices.deleted_at')
                ->select('invoices.*', 'invoice_details.service_id','appointments.appointment_type_id')
                ->count();
        } else {
            return DB::table('appointments')
                ->join('invoices', 'appointments.id','=','invoices.appointment_id')
                ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->whereIn('invoices.location_id',ACL::getUserCentres())
                ->whereNull('invoices.deleted_at')
                ->select('invoices.*', 'invoice_details.service_id','appointments.appointment_type_id')
                ->count();
        }
    }

    /**
     * Get Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $iDisplayStart Start Index
     * @param (int) $iDisplayLength Total Records Length
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false,$id = false, $apply_filter = false, $filename )
    {
        $where = self::filters_invoices( $request, $account_id , $id , $apply_filter, $filename );

        $orderBy = 'created_at';
        $order = 'desc';

        if ($request->get('order')[0]['dir']) {
            $orderColumn = $request->get('order')[0]['column'];
            $orderBy = $request->get('columns')[$orderColumn]['data'];
            if ($orderBy == 'created_at') {
                $orderBy = 'invoices.created_at';
            }
            $order = $request->get('order')[0]['dir'];
        }


        if (count($where)) {

            return DB::table('appointments')
                ->join('invoices', 'appointments.id','=','invoices.appointment_id')
                ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->where($where)
                ->whereIn('invoices.location_id',ACL::getUserCentres())
                ->whereNull('invoices.deleted_at')
                ->select('invoices.*', 'invoice_details.service_id','appointments.appointment_type_id')
                ->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy,$order)->get();
        } else {
            return DB::table('appointments')
                ->join('invoices', 'appointments.id','=','invoices.appointment_id')
                ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->whereIn('invoices.location_id',ACL::getUserCentres())
                ->whereNull('invoices.deleted_at')
                ->select('invoices.*', 'invoice_details.service_id','appointments.appointment_type_id')
                ->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy,$order)->get();
        }
    }

    /**
     * Check if child records exist
     *
     * @param (int) $id
     * @param
     *
     * @return (boolean)
     */
    static public function isChildExists($id, $account_id)
    {
        /*if (
            Locations::where(['city_id' => $id, 'account_id' => $account_id])->count() ||
            Leads::where(['city_id' => $id, 'account_id' => $account_id])->count() ||
            Appointments::where(['city_id' => $id, 'account_id' => $account_id])->count()
        ) {
            return true;
        }

        return false;*/
        return false;
    }

    static public function filters_invoices($request , $account_id , $id, $apply_filter, $filename )
    {
        $where = array();

        if($id != false){
            $where[] = array(
                'invoices.patient_id',
                '=',
                $id
            );
            Filters::put(Auth::user()->id, $filename, 'id', $id);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id, $filename, 'id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'id')){
                    $where[] = array(
                        'invoices.patient_id',
                        '=',
                        Filters::get(Auth::user()->id,$filename, 'id')
                    );
                }
            }
        }

        if ($account_id) {
            $where[] = array(
                'invoices.account_id',
                '=',
                $account_id
            );
            Filters::put(Auth::user()->id , $filename, 'account_id', $account_id ) ;
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id ,$filename, 'account_id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'account_id')){
                    $where[] = array(
                        'invoices.account_id',
                        '=',
                        Filters::get(Auth::user()->id ,$filename, 'account_id')
                    );
                }
            }
        }

        if ( $request->get('appointment_type_id')){
            $where[] = array(
                'appointments.appointment_type_id',
                '=',
                $request->get('appointment_type_id')
            );
            Filters::put(Auth::user()->id, $filename, 'appointment_type_id', $request->get('appointment_type_id'));
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id, $filename, 'appointment_type_id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'appointment_type_id')){
                    $where[] = array(
                        'appointments.appointment_type_id',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'appointment_type_id')
                    );
                }
            }
        }

        if ($request->get('patient_id')) {
            $where[] = array(
                'invoices.patient_id',
                '=',
                $request->get('patient_id')
            );
            Filters::put(Auth::user()->id , $filename, 'patient_id', $request->get('patient_id')) ;
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id ,$filename, 'patient_id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'patient_id')){
                    $where[] = array(
                        'invoices.patient_id',
                        '=',
                        Filters::get(Auth::user()->id ,$filename, 'patient_id')
                    );
                }
            }
        }

        if ($request->get('id')) {
            $where[] = array(
                'invoices.patient_id',
                '=',
                \App\Helpers\GeneralFunctions::patientSearch($request->get('id'))
            );
            Filters::put(Auth::user()->id , $filename, 'patient_id', \App\Helpers\GeneralFunctions::patientSearch($request->get('id'))) ;
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id ,$filename, 'id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'id')){
                    $where[] = array(
                        'invoices.patient_id',
                        '=',
                        Filters::get(Auth::user()->id ,$filename, 'id')
                    );
                }
            }
        }

        if ($request->get('invoice_status_id')) {
            $where[] = array(
                'invoices.invoice_status_id',
                '=',
                $request->get('invoice_status_id')
            );
            Filters::put( Auth::user()->id , $filename, 'invoice_status_id', $request->get('invoice_status_id')) ;
        } else {
            if ($apply_filter){
                Filters::forget( Auth::user()->id ,$filename, 'invoice_status_id');
            } else {
                if (Filters::get( Auth::user()->id, $filename, 'invoice_status_id')){
                    $where[] = array(
                        'invoices.invoice_status_id',
                        '=',
                        Filters::get( Auth::user()->id ,$filename, 'invoice_status_id')
                    );
                }
            }
        }

        if ($request->get('location_id')){
            $where[] = array(
                'invoices.location_id',
                '=',
                $request->get('location_id')
            );
            Filters::put(Auth::user()->id, $filename, 'location_id', $request->get('location_id'));
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id , $filename, 'location_id');
            } else {
                if (Filters::get(Auth::user()->id ,$filename, 'location_id')){
                    $where[] = array(
                        'invoices.location_id',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'location_id')
                    );
                }
            }
        }

        if ($request->get('service')) {
            $where[] = array(
                'invoice_details.service_id',
                '=',
                $request->get('service')
            );
            Filters::put( Auth::user()->id , $filename, 'service', $request->get('service')) ;
        } else {
            if ($apply_filter){
                Filters::forget( Auth::user()->id ,$filename, 'service');
            } else {
                if (Filters::get( Auth::user()->id, $filename, 'service')){
                    $where[] = array(
                        'invoice_details.service_id',
                        '=',
                        Filters::get( Auth::user()->id ,$filename, 'service')
                    );
                }
            }
        }

        if ($request->get('created_from') && $request->get('created_from') != '') {
            $where[] = array(
                'invoices.created_at',
                '>=',
                $request->get('created_from') . ' 00:00:00'
            );
            Filters::put(Auth::User()->id, $filename, 'created_from', $request->get('created_from') . ' 00:00:00');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_from');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_from')) {
                    $where[] = array(
                        'invoices.created_at',
                        '>=',
                        Filters::get(Auth::User()->id, $filename, 'created_from') . ' 00:00:00'
                    );
                }
            }
        }

        if ($request->get('created_to') && $request->get('created_to') != '') {
            $where[] = array(
                'invoices.created_at',
                '<=',
                $request->get('created_to') . ' 23:59:59'
            );
            Filters::put(Auth::User()->id, $filename, 'created_to', $request->get('created_to') . ' 23:59:59');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_to');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_to')) {
                    $where[] = array(
                        'invoices.created_at',
                        '<=',
                        Filters::get(Auth::User()->id, $filename, 'created_to') . ' 23:59:59'
                    );
                }
            }
        }

        return $where ;
    }

}
