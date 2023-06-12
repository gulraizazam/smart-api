<?php

namespace App\Models;

use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use Auth;
use Config;
use DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class Patients extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'email', 'password', 'remember_token', 'phone', 'main_account', 'gender', 'cnic', 'dob', 'address', 'referred_by', 'active', 'user_type_id', 'resource_type_id', 'account_id'];

    protected static $_fillable = ['name', 'email', 'phone', 'main_account', 'gender', 'cnic', 'dob', 'address', 'referred_by', 'user_type_id'];

    protected static $USER_TYPE = 3;

    protected $table = 'users';

    protected static $_table = 'users';

    /**
     * Get the Leads for Patient.
     */
    public function leads()
    {
        return $this->hasMany('App\Models\Leads', 'lead_source_id');
    }

    /*Relation for audit trail*/
    public function audit_field_before()
    {
        return $this->hasMany('App\Models\AuditTrailChanges', 'field_before');
    }

    public function audit_field_after()
    {
        return $this->hasMany('App\Models\AuditTrailChanges', 'field_after');
    }

    /*end*/
    public static function getAll($account_id)
    {
        return self::where(['user_type_id' => self::$USER_TYPE, 'active' => 1, 'account_id' => $account_id])->get();
    }

    /*
     * Ajax base result of patient
     * */
    public static function getPatientAjax($name, $account_id)
    {
        $name = GeneralFunctions::patientSearch($name);

        $phone_numeric = GeneralFunctions::clearnString($name);

        if (is_numeric($phone_numeric)) {
            $phone = GeneralFunctions::cleanNumber($name);

            return self::where([
                ['user_type_id', '=', '3'],
                ['active', '=', '1'],
                ['account_id', '=', $account_id],
                ['phone', 'LIKE', "%{$phone}%"],
            ])->orwhere('id', '=', $phone)->select(DB::raw('CONCAT("C-",id) as phone'), 'name', 'id')->get();
        } else {
            return self::where([
                ['user_type_id', '=', '3'],
                ['active', '=', '1'],
                ['account_id', '=', $account_id],
                ['name', 'LIKE', "%{$name}%"],
            ])->select(DB::raw('CONCAT("C-",id) as phone'), 'name', 'id')->get();
        }
    }

    /*
     * Ajax base result of patient according to id or name
     * */
    public static function getPatientidAjax($name, $account_id)
    {
        if (stripos($name, 'C-') !== false) {
            $name = str_replace(['C-', 'c-'], '', $name);

            return self::where([
                'user_type_id' => '3',
                'active' => '1',
                'account_id' => $account_id,
                'id' => $name,
            ])->select('name', 'id', 'phone')->get();
        }
        $users = collect();
        if (is_numeric($name)) {
            $users = self::where([
                'user_type_id' => '3',
                'active' => '1',
                'account_id' => $account_id,
                'id' => $name,
            ])->select('name', 'id', 'phone')->get();
        }
        if ($users->count() > 0) {
            return $users;
        }
        $name = GeneralFunctions::patientSearch($name);
        $phone_numeric = GeneralFunctions::clearnString($name);
        if (is_numeric($phone_numeric)) {
            $phone = GeneralFunctions::cleanNumber($name);

            return self::where([
                ['user_type_id', '=', '3'],
                ['active', '=', '1'],
                ['account_id', '=', $account_id],
                ['phone', 'LIKE', "%{$phone}%"],
            ])->select('name', 'id', 'phone')->get();
        } else {
            return self::where([
                ['user_type_id', '=', '3'],
                ['active', '=', '1'],
                ['account_id', '=', $account_id],
                ['name', 'LIKE', "%{$name}%"],
            ])->select('name', 'id', 'phone')->get();
        }
    }

    public static function getPatientPhoneAjax($phone, $account_id)
    {
        if (is_numeric($phone)) {
            return self::where([
                ['user_type_id', '=', '3'],
                ['active', '=', '1'],
                ['account_id', '=', $account_id],
                ['phone', 'LIKE', "%{$phone}%"],
            ])->select('name', 'id', 'phone')->get();
        } else {
            return self::where([
                ['user_type_id', '=', '3'],
                ['active', '=', '1'],
                ['account_id', '=', $account_id],
                ['phone', 'LIKE', "%{$phone}%"],
            ])->select('name', 'id', 'phone')->get();
        }
    }

    /**
     * Get the User that owns the Patient.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the User that owns the Patient.
     */
    public static function getByPhone($phone, $account_id = false, $patient_id = false)
    {
        $where = [];

        $where[] = [
            'phone',
            '=',
            $phone,
        ];
        $where[] = [
            'user_type_id',
            '=',
            self::$USER_TYPE,
        ];
        if ($patient_id) {
            $where[] = [
                'id',
                '=',
                $patient_id,
            ];
        }
        //        if ($account_id) {
        //            $where[] = array('account_id' => $account_id);
        //        }

        return self::where($where)->first();
    }

		/**
		 * Create Record
		 *
		 * @param data
		 *
		 * @return (mixed)
		 */
		static public function createRecord($data,$flag=0)
		{
            if($flag == 1){
			    $patient = Patients::where(['phone' => $data['phone']])->first();
                if(!$patient){
                    $record = Patients::create($data);
                    AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

                    return $record;
                } else {
                    if ($flag == 1) {
                        return 'Patient is already exist';
                    } else {
                        return $patient;
                    }
                }
            } else {
                $record = Patients::create($data);
                AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

                return $record;
            }
        }

        /**
         * update Record
         *
         * @param data
         * @return (mixed)
         */
        public static function updateRecord($id, $data, $appointmentData = false, $patientData = false)
        {
            if ($appointmentData) {
                if ($appointmentData['patient_id'] != 0) {
                    $old_data = (Patients::find($appointmentData['patient_id']))->toArray();
                }
                if (isset($appointmentData['patient_id_1'])) {
                    if ($appointmentData['patient_id'] == 0) {
                        $appointmentData['patient_id'] = $appointmentData['patient_id_1'];
                        $patientData['patient_id'] = $patientData['patient_id_1'];
                    }
                }
                $record = Patients::find($appointmentData['patient_id']);
                /* $record = Patients::updateOrCreate(array(
                     'id' => $appointmentData['patient_id'],
                     'phone' => $appointmentData['phone'],
                     'user_type_id' => Config::get('constants.patient_id'),
                     'account_id' => Auth::User()->account_id
                 ), $patientData);*/
                $is_exist = Patients::find($appointmentData['patient_id']);
                if ($is_exist) {
                    AuditTrails::EditEventLogger(self::$_table, 'edit', $record, self::$_fillable, $is_exist, $appointmentData['patient_id']);
                } else {
                    AuditTrails::addEventLogger(self::$_table, 'create', $record, self::$_fillable, $record);
                }

                return $record;
            } else {
                $old_data = (Patients::find($id))->toArray();
                $record = self::where(['id' => $id])->first();
                if (! $record) {
                    return null;
                }
                $record->update($data);
                AuditTrails::EditEventLogger(self::$_table, 'edit', $record, self::$_fillable, $old_data, $id);

                return $record;
            }
        }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveOnly($patientId = false)
    {
        if ($patientId && ! is_array($patientId)) {
            $patientId = [$patientId];
        }
        $query = self::where(['user_type_id' => self::$USER_TYPE, 'active' => 1]);
        if ($patientId) {
            $query->whereIn('id', $patientId);
        }

        return $query->OrderBy('name', 'asc')->get();
    }

    /**
     * Get Total Records
     *
     * @param (int) $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id, $apply_filter, $filename)
    {

        $where = self::filters_patients($request, $account_id, $apply_filter, $filename);

			if (count($where)) {
				if(\Illuminate\Support\Facades\Gate::allows("view_inactive_patients")){
					return self::where($where)->count();
				}else{
					return self::where($where)->where(['active' => 1])->count();
				}
			} else {
				if(\Illuminate\Support\Facades\Gate::allows("view_inactive_patients")){
					return self::count();
				}else{
					return self::where(['active' => 1])->count();
				}
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
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id, $apply_filter, $filename)
    {

        $where = self::filters_patients($request, $account_id, $apply_filter, $filename);

        [$orderBy, $order] = getSortBy($request);

			if (count($where)) {
				if(\Illuminate\Support\Facades\Gate::allows("view_inactive_patients")){
					return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy("created_at","DESC")->select('*', 'id as patient_id')->get();
				}else{
					return self::where(['active' => 1])->where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy("created_at","DESC")->select('*', 'id as patient_id')->get();
				}

				//return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->select('*', 'id as patient_id')->get();
			} else {
				//return self::limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->select('*', 'id as patient_id')->get();
				if(\Illuminate\Support\Facades\Gate::allows("view_inactive_patients")){
					return self::limit($iDisplayLength)->offset($iDisplayStart)->orderBy("created_at", "DESC")->select('*', 'id as patient_id')->get();
				}else{
					return self::where(['active' => 1])->limit($iDisplayLength)->offset($iDisplayStart)->orderBy("created_at","DESC")->select('*', 'id as patient_id')->get();
				}
			}
		}

    /**
     * Delete Record
     *
     * @param id
     * @return (mixed)
     */
    public static function DeleteRecord($id)
    {

        $patient = self::getData($id);

        if (! $patient) {
            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (self::isChildExists($id, Auth::User()->account_id)) {
            return [
                'status' => false,
                'message' => 'Lead or Appointment exists, unable to delete resource',
            ];
        }

        $patient->delete();

        //log request for delete for audit trail

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return [
            'status' => true,
            'message' => 'Record has been deleted successfully.',
        ];
    }

    /**
     * inactive Record
     *
     * @param id
     * @return (mixed)
     */
    public static function InactiveRecord($id)
    {
        $patient = self::getData($id);

        if (! $patient) {
            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }

        $patient->update(['active' => 0]);

        AuditTrails::inactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

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

        $patient = self::getData($id);

        if (! $patient) {
            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }

        $patient->update(['active' => 1]);

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return [
            'status' => true,
            'message' => 'Record has been activated successfully.',
        ];

    }

    /**
     * Check if child records exist
     *
     * @param (int) $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
    {
        if (
            Leads::where(['patient_id' => $id, 'account_id' => $account_id])->count() ||
            Appointments::where(['patient_id' => $id, 'account_id' => $account_id])->count() ||
            CustomFormFeedbacks::where(['reference_id' => $id, 'account_id' => $account_id])->count() ||
            Documents::where(['user_id' => $id])->count() ||
            Packages::where(['patient_id' => $id, 'account_id' => $account_id])->count() ||
            Measurement::where(['patient_id' => $id])->count() ||
            Medical::where(['patient_id' => $id])->count() ||
            Invoices::where(['patient_id' => $id, 'account_id' => $account_id])->count()
        ) {
            return true;
        }

        return false;
    }

    public static function filters_patients($request, $account_id, $apply_filter, $filename)
    {

        $where = [];
        $filters = getFilters($request->all());

        $where[] = [
            'user_type_id',
            '=',
            self::$USER_TYPE,
        ];

        if ($account_id) {
            $where[] = [
                'account_id',
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
                        'account_id',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'account_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'patient_id')) {
            $where[] = [
                'id',
                'like',
                '%'.GeneralFunctions::patientSearch($filters['patient_id']).'%',
            ];
            Filters::put(Auth::user()->id, $filename, 'patient_id', $filters['patient_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'patient_id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'patient_id')) {
                    $where[] = [
                        'id',
                        'like',
                        '%'.Filters::get(Auth::user()->id, $filename, 'patient_id').'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'name')) {
            $where[] = [
                'name',
                'like',
                '%'.$filters['name'].'%',
            ];
            Filters::put(Auth::user()->id, $filename, 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'name');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'name')) {
                    $where[] = [
                        'name',
                        'like',
                        '%'.Filters::get(Auth::user()->id, $filename, 'name').'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'email')) {
            $where[] = [
                'email',
                'like',
                '%'.$filters['email'].'%',
            ];
            Filters::put(Auth::user()->id, $filename, 'email', $filters['email']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'email');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'email')) {
                    $where[] = [
                        'email',
                        'like',
                        '%'.Filters::get(Auth::user()->id, $filename, 'email').'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'gender')) {
            $where[] = [
                'gender',
                'like',
                '%'.$filters['gender'].'%',
            ];
            Filters::put(Auth::user()->id, $filename, 'gender', $filters['gender']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'gender');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'gender')) {
                    $where[] = [
                        'gender',
                        'like',
                        '%'.Filters::get(Auth::user()->id, $filename, 'gender').'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'phone')) {
            $where[] = [
                'phone',
                'like',
                '%'.GeneralFunctions::cleanNumber($filters['phone']).'%',
            ];
            Filters::put(Auth::user()->id, $filename, 'phone', $filters['phone']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'phone');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'phone')) {
                    $where[] = [
                        'users.phone',
                        'like',
                        '%'.GeneralFunctions::cleanNumber(
                            Filters::get(Auth::User()->id, $filename, 'phone')
                        ).'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_from')) {
            $where[] = [
                'created_at',
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
                        'created_at',
                        '>=',
                        Filters::get(Auth::User()->id, $filename, 'created_from').' 00:00:00',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_to')) {
            $where[] = [
                'created_at',
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
                        'created_at',
                        '<=',
                        Filters::get(Auth::User()->id, $filename, 'created_to').' 23:59:59',
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
