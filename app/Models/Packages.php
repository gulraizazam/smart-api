<?php

namespace App\Models;

use DateTime;
use App\Helpers\ACL;
use App\Helpers\Filters;
use Illuminate\Http\Request;
use App\Helpers\GeneralFunctions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

class Packages extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['random_id', 'name', 'plan_name', 'sessioncount', 'total_price', 'is_exclusive', 'plan_type', 'account_id', 'patient_id', 'active', 'created_at', 'updated_at', 'deleted_at', 'location_id', 'appointment_id', 'is_refund'];

    protected static $_fillable = ['name', 'sessioncount', 'total_price', 'is_exclusive', 'plan_type', 'patient_id', 'active', 'location_id', 'appointment_id', 'is_refund', 'created_at', 'updated_at', 'deleted_at'];

    protected $table = 'packages';

    protected static $_table = 'packages';

    /*
     * get the data of patients from users table
     *
     * */
    public function user()
    {
        return $this->belongsTo(User::class, 'patient_id')->withTrashed();
    }

    /**
     * Get the packages.
     */
    public function packagesadvances()
    {

        return $this->hasMany('App\Models\PackageAdvances', 'package_id');
    }

    /*
    * get the data of location from location table
    *
    * */
    public function location()
    {
        return $this->belongsTo('App\Models\Locations', 'location_id')->withTrashed();
    }

    /*
     * get the data of appointment from package
     * */

    public function appointment()
    {
       return  $this->belongsTo(Appointments::class);
    }

    /*
     * get the data of appointment from package
     *
     */
    public function packageservice()
    {
        return $this->hasMany('App\Models\PackageService', 'package_id');
    }
    public function services()
    {
        return $this->hasMany('App\Models\Services', 'service_id');
    }

    /*
     * Create Record
     *  @param: data
     * @return: mixed
     * */
    public static function createRecord($data, $request)
    {

        $record = self::create($data);

        $data['name'] = sprintf('%05d', $record->id);

        $record->update(['name' => sprintf('%05d', $record->id)]);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        $packagebundle = PackageBundles::createRecord($record, $request);

        return $record;
    }

    /*
     * Update Record
     * @param: data
     * @return: mixed
     * */
    public static function updateRecord($data, $random_id, $request)
    {
        $record = self::where('random_id', '=', $random_id)->first();
        $id = $record->id;
        $old_data = (self::find($record->id))->toArray();
        $record->update($data);
        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $id);
        $packagebundle = PackageBundles::updateRecord($record, $request);

        return $record;
    }

    /*
    * Update Record when refu
    * @param: data
    * @return: mixed
    * */
    public static function updateRecordRefunds($package_id)
    {

        $record = self::where('id', '=', $package_id)->first();

        $id = $record->id;

        $old_data = (self::find($package_id))->toArray();

        $record->update(['is_refund' => '1']);

        AuditTrails::editEventLogger(self::$_table, 'Edit', $record, self::$_fillable, $old_data, $id);

        return $record;
    }

    /**
     * inactive Record
     *
     * @param id
     * @return (mixed)
     */
    public static function inactiveRecord($id)
    {

        $package = Packages::getData($id);

        if (!$package) {
            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }

        $record = $package->update(['active' => 0]);

        AuditTrails::InactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return [
            'status' => true,
            'message' => 'Record has been inactivated successfully.',
        ];
    }

    /**
     * active Record
     *
     * @param id
     * @return (mixed)
     */
    public static function activeRecord($id)
    {

        $package = Packages::getData($id);

        if (!$package) {

            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }

        $record = $package->update(['active' => 1]);

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return [
            'status' => true,
            'message' => 'Record has been activated successfully.',
        ];
    }

    /**
     * Delete Record
     *
     * @param id
     * @return (mixed)
     */
    public static function DeleteRecord($id)
    {
        $package = Packages::getData($id);

        if (!$package) {
            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Packages::isChildExists($id, Auth::User()->account_id)) {

            return [
                'status' => false,
                'message' => 'Child records exist, unable to delete resource',
            ];
        }

        $record = $package->delete();

        //log request for delete for audit trail

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return [
            'status' => true,
            'message' => 'Record has been deleted successfully.',
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
        if (
            InvoiceDetails::where(['package_id' => $id])->count() ||
            PackageAdvances::where(['package_id' => $id])->count()

        ) {
            return true;
        }

        return false;
    }

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id, $id, $apply_filter, $filename)
    {
        $where = self::filters($request, $account_id, $id, $apply_filter, $filename);

        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_plans')) {
                return self::where($where)->whereIn('location_id', ACL::getUserCentres())->count();
            } else {
                return self::where($where)->where('active', 1)->whereIn('location_id', ACL::getUserCentres())->count();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_plans')) {
                return self::whereIn('location_id', ACL::getUserCentres())->count();
            } else {
                return self::whereIn('location_id', ACL::getUserCentres())->where('active', 1)->count();
            }
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
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id, $id, $apply_filter, $filename)
    {

        $where = self::filters($request, $account_id, $id, $apply_filter, $filename);

        [$orderBy, $order] = getSortBy($request, 'updated_at', 'DESC');
        if (\Illuminate\Support\Facades\Gate::allows('view_inactive_plans')) {
            return self::when(count($where), fn ($query) => $query->where($where))->whereIn('location_id', ACL::getUserCentres())
                ->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->orderby($orderBy, $order)
                ->get();
        } else {
            return self::when(count($where), fn ($query) => $query->where($where))->where('active', 1)->whereIn('location_id', ACL::getUserCentres())
                ->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->orderby($orderBy, $order)
                ->get();
        }
    }

    public static function filters($request, $account_id, $id, $apply_filter, $filename)
    {

        $where = [];

        $filters = getFilters($request->all());
        $apply_filter = checkFilters($filters, $filename);

        if (hasFilter($filters, 'created_at')) {
            $date_range = explode(' - ', $filters['created_at']);
            $start_date_time = date('Y-m-d H:i:s', strtotime($date_range[0]));
            $end_date_string = new DateTime($date_range[1]);
            $end_date_string->setTime(23, 59, 0);
            $end_date_time = $end_date_string->format('Y-m-d H:i:s');
        } else {
            $start_date_time = null;
            $end_date_time = null;
        }

        if ($id != false) {
            $where[] = [
                'patient_id',
                '=',
                $id,
            ];
            Filters::put(Auth::user()->id, $filename, 'patient_id', $id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'patient_id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'patient_id')) {
                    /*$where[] = array(
                        'patient_id',
                        '=',
                        Filters::get(Auth::user()->id,$filename,'patient_id')
                    );*/
                }
            }
        }

        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::User()->id, $filename, 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'account_id')) {
                    $where[] = [
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'account_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'patient_id')) {
            $where[] = [
                'patient_id',
                '=',
                $filters['patient_id'],
            ];
            // Filters::put(Auth::User()->id, $filename, 'patient_id', $filters['patient_id']);
            // Filters::put(Auth::user()->id , $filename, 'patient_name', str_replace('undefined', '', $filters['patient_name'])) ;
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'patient_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'patient_id')) {
                    /*$where[] = array(
                        'patient_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'patient_id')
                    );*/
                }
            }
        }
        if (hasFilter($filters, 'id')) {
            $where[] = [
                'patient_id',
                '=',
                GeneralFunctions::patientSearch($filters['id']),
            ];
            Filters::put(Auth::User()->id, $filename, 'patient_id', GeneralFunctions::patientSearch($filters['id']));
            Filters::put(Auth::User()->id, $filename, 'id', GeneralFunctions::patientSearch($filters['id']));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'id')) {
                    /*$where[] = array(
                        'patient_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'id')
                    );*/
                }
            }
        }

        if (hasFilter($filters, 'package_id')) {
            $where[] = [
                'id',
                '=',
                $filters['package_id'],
            ];
            Filters::put(Auth::User()->id, $filename, 'package_id', $filters['package_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'package_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'package_id')) {
                    $where[] = [
                        'id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'package_id'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'created_at')) {
            $where[] = ['created_at', '>=', $start_date_time];
            $where[] = ['created_at', '<=', $end_date_time];
            Filters::put(Auth::User()->id, $filename, 'created_at', $filters['created_at']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_at');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_at')) {
                    $where[] = ['created_at', '>=', Filters::get(Auth::User()->id, $filename, 'created_at')];
                }
            }
        }

        if (hasFilter($filters, 'location_id')) {
            $where[] = [
                'location_id',
                '=',
                $filters['location_id'],
            ];
            Filters::put(Auth::User()->id, $filename, 'location_id', $filters['location_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'location_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'location_id')) {
                    $where[] = [
                        'location_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'location_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'status')) {
            $where[] = [
                'active',
                '=',
                $filters['status'],
            ];
            Filters::put(Auth::user()->id, $filename, 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'status');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'status') == 0 || Filters::get(Auth::user()->id, $filename, 'status') == 1) {
                    if (Filters::get(Auth::user()->id, $filename, 'status') != null) {
                        $where[] = [
                            'active',
                            '=',
                            Filters::get(Auth::user()->id, $filename, 'status'),
                        ];
                    }
                }
            }
        }

        return $where;
    }
}
