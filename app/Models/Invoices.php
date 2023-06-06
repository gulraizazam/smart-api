<?php

namespace App\Models;

use App\Helpers\ACL;
use App\Helpers\Filters;
use Auth;
use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class Invoices extends Model
{
    use SoftDeletes;

    protected $fillable = ['total_price', 'account_id', 'patient_id', 'appointment_id', 'invoice_status_id', 'active', 'is_exclusive', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'location_id', 'doctor_id'];

    protected static $_fillable = ['total_price', 'account_id', 'patient_id', 'appointment_id', 'invoice_status_id', 'active', 'is_exclusive', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'location_id', 'doctor_id'];

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
        return $this->belongsTo('App\Models\User', 'patient_id')->withTrashed();
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
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function createRecord($data)
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
     *
     * @return (mixed)
     */
    public static function CancelRecord($id, $account_id)
    {

        $old_data = (Invoices::find($id))->toArray();

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
            return null;
        }
        $invoicestatus = InvoiceStatuses::where('slug', '=', 'cancelled')->first();

        $record->update(['invoice_status_id' => $invoicestatus->id]);

        $data = (Invoices::find($id))->toArray();

        AuditTrails::EditEventLogger(self::$_table, 'cancel', $data, self::$_fillable, $old_data, $id);

        return $record;

    }

    /**
     * Get Total Records
     *
     * @param (int) $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id, $id, $apply_filter, $filename)
    {
        $where = self::filters_invoices($request, $account_id, $id, $apply_filter, $filename);

        if (count($where)) {
            return DB::table('appointments')
                ->join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->where($where)
                ->whereIn('invoices.location_id', ACL::getUserCentres())
                ->whereNull('invoices.deleted_at')
                ->select('invoices.*', 'invoice_details.service_id', 'appointments.appointment_type_id')
                ->count();
        } else {
            return DB::table('appointments')
                ->join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->whereIn('invoices.location_id', ACL::getUserCentres())
                ->whereNull('invoices.deleted_at')
                ->select('invoices.*', 'invoice_details.service_id', 'appointments.appointment_type_id')
                ->count();
        }
    }

    /**
     * Get Records
     *
     * @param (int) $iDisplayStart Start Index
     * @param (int) $iDisplayLength Total Records Length
     * @param (int) $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id, $id, $apply_filter, $filename)
    {
        $where = self::filters_invoices($request, $account_id, $id, $apply_filter, $filename);

        [$orderBy, $order] = getSortBy($request, 'created_at', 'DESC');

        if (count($where)) {

            return DB::table('appointments')
                ->join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->where($where)
                ->whereIn('invoices.location_id', ACL::getUserCentres())
                ->whereNull('invoices.deleted_at')
                ->select('invoices.*', 'invoice_details.service_id', 'appointments.appointment_type_id')
                ->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy, $order)->get();
        } else {
            return DB::table('appointments')
                ->join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->whereIn('invoices.location_id', ACL::getUserCentres())
                ->whereNull('invoices.deleted_at')
                ->select('invoices.*', 'invoice_details.service_id', 'appointments.appointment_type_id')
                ->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy, $order)->get();
        }
    }

    /**
     * Check if child records exist
     *
     * @param (int) $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
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

    public static function filters_invoices($request, $account_id, $id, $apply_filter, $filename)
    {
        $where = [];

        $filters = getFilters($request->all());

        if ($id != false) {
            $where[] = [
                'invoices.patient_id',
                '=',
                $id,
            ];
            Filters::put(Auth::user()->id, $filename, 'id', $id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'id')) {
                    $where[] = [
                        'invoices.patient_id',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'id'),
                    ];
                }
            }
        }

        if ($account_id) {
            $where[] = [
                'invoices.account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::user()->id, $filename, 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'account_id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'account_id')) {
                    $where[] = [
                        'invoices.account_id',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'account_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'appointment_type_id')) {
            $where[] = [
                'appointments.appointment_type_id',
                '=',
                $filters['appointment_type_id'],
            ];
            Filters::put(Auth::user()->id, $filename, 'appointment_type_id', $filters['appointment_type_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'appointment_type_id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'appointment_type_id')) {
                    $where[] = [
                        'appointments.appointment_type_id',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'appointment_type_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'patient_id')) {
            $where[] = [
                'invoices.patient_id',
                '=',
                $filters['patient_id'],
            ];
            Filters::put(Auth::user()->id, $filename, 'patient_id', $filters['patient_id']);
            Filters::put(Auth::user()->id, $filename, 'patient_name', $filters['patient_name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'patient_id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'patient_id')) {
                    $where[] = [
                        'invoices.patient_id',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'patient_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'id')) {
            $where[] = [
                'invoices.patient_id',
                '=',
                \App\Helpers\GeneralFunctions::patientSearch($filters['id']),
            ];
            Filters::put(Auth::user()->id, $filename, 'id', \App\Helpers\GeneralFunctions::patientSearch($filters['id']));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'id');
            } else {
                if (! is_null(Filters::get(Auth::user()->id, $filename, 'id'))) {

                    /* $where[] = array(
                         'invoices.patient_id',
                         '=',
                         Filters::get(Auth::user()->id ,$filename, 'id')
                     );*/
                }
            }
        }

        if (hasFilter($filters, 'invoice_status_id')) {
            $where[] = [
                'invoices.invoice_status_id',
                '=',
                $filters['invoice_status_id'],
            ];
            Filters::put(Auth::user()->id, $filename, 'invoice_status_id', $filters['invoice_status_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'invoice_status_id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'invoice_status_id')) {
                    $where[] = [
                        'invoices.invoice_status_id',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'invoice_status_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'location_id')) {
            $where[] = [
                'invoices.location_id',
                '=',
                $filters['location_id'],
            ];
            Filters::put(Auth::user()->id, $filename, 'location_id', $filters['location_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'location_id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'location_id')) {
                    $where[] = [
                        'invoices.location_id',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'location_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'service_id')) {
            $where[] = [
                'invoice_details.service_id',
                '=',
                $filters['service_id'],
            ];
            Filters::put(Auth::user()->id, $filename, 'service_id', $filters['service_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'service_id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'service_id')) {
                    $where[] = [
                        'invoice_details.service_id',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'service_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_from')) {
            $where[] = [
                'invoices.created_at',
                '>=',
                $filters['created_from'].' 00:00:00',
            ];
            Filters::put(Auth::User()->id, $filename, 'created_from', $filters['created_from'].' 00:00:00');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_from');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_from')) {
                    $where[] = [
                        'invoices.created_at',
                        '>=',
                        Filters::get(Auth::User()->id, $filename, 'created_from').' 00:00:00',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_to')) {
            $where[] = [
                'invoices.created_at',
                '<=',
                $filters['created_to'].' 23:59:59',
            ];
            Filters::put(Auth::User()->id, $filename, 'created_to', $filters['created_to'].' 23:59:59');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_to');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_to')) {
                    $where[] = [
                        'invoices.created_at',
                        '<=',
                        Filters::get(Auth::User()->id, $filename, 'created_to').' 23:59:59',
                    ];
                }
            }
        }

        return $where;
    }

    public function invoiceDetailService(): HasOne
    {
        return $this->hasOne('App\Models\InvoiceDetails', 'invoice_id', 'id');
    }
}
