<?php

	namespace App\Models;

	use App\Helpers\Filters;
	use App\Helpers\GeneralFunctions;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Database\Eloquent\SoftDeletes;
	use Illuminate\Http\Request;
	use Auth;
	use PHPUnit\Util\Filter;
	use Config;
	use DB;


	class Patients extends BaseModal
	{
		use SoftDeletes;

		protected $fillable = ['name', 'email', 'password', 'remember_token', 'phone', 'main_account', 'gender', 'cnic', 'dob', 'address', 'referred_by', 'active', 'user_type_id', 'resource_type_id', 'account_id'];

		protected static $_fillable = ['name', 'email', 'phone', 'main_account', 'gender', 'cnic', 'dob', 'address', 'referred_by', 'user_type_id'];

		static protected $USER_TYPE = 3;

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
			return $this->hasMany('App\Models\AuditTrailChanges','field_before');
		}
		public function audit_field_after()
		{
			return $this->hasMany('App\Models\AuditTrailChanges','field_after');
		}
		/*end*/
		static public function getAll($account_id)
		{
			return self::where(['user_type_id' => self::$USER_TYPE, 'active' => 1, 'account_id' => $account_id])->get();
		}
		/*
		 * Ajax base result of patient
		 * */
		static public function getPatientAjax($name, $account_id)
		{
			$name=GeneralFunctions::patientSearch($name);

			$phone_numeric = GeneralFunctions::clearnString($name);

			if (is_numeric($phone_numeric)) {
				$phone = GeneralFunctions::cleanNumber($name);
				return self::where([
					['user_type_id', '=', '3'],
					['active', '=', '1'],
					['account_id', '=', $account_id],
					['phone', 'LIKE', "%{$phone}%"]
				])->orwhere('id', '=', $phone)->select(DB::raw('CONCAT("C-",id) as phone'),'name', 'id')->get();
			} else {
				return self::where([
					['user_type_id', '=', '3'],
					['active', '=', '1'],
					['account_id', '=', $account_id],
					['name', 'LIKE', "%{$name}%"]
				])->select(DB::raw('CONCAT("C-",id) as phone'),'name', 'id')->get();
			}
		}

		/*
		 * Ajax base result of patient according to id or name
		 * */
		static public function getPatientidAjax($name, $account_id)
		{
			if (is_numeric($name)) {
				return self::where([
					['user_type_id', '=', '3'],
					['active', '=', '1'],
					['account_id', '=', $account_id],
					['id', 'LIKE', "%{$name}%"]
				])->select('name', 'id', 'phone')->get();
			} else {
				return self::where([
					['user_type_id', '=', '3'],
					['active', '=', '1'],
					['account_id', '=', $account_id],
					['name', 'LIKE', "%{$name}%"]
				])->select('name', 'id', 'phone')->get();
			}
		}

		static public function getPatientPhoneAjax($phone, $account_id)
		{
			if (is_numeric($phone)) {
				return self::where([
					['user_type_id', '=', '3'],
					['active', '=', '1'],
					['account_id', '=', $account_id],
					['phone', 'LIKE', "%{$phone}%"]
				])->select('name', 'id', 'phone')->get();
			} else {
				return self::where([
					['user_type_id', '=', '3'],
					['active', '=', '1'],
					['account_id', '=', $account_id],
					['phone', 'LIKE', "%{$phone}%"]
				])->select('name', 'id', 'phone')->get();
			}
		}

		/**
		 * Get the User that owns the Patient.
		 */
		public function user()
		{
			return $this->belongsTo('App\User', 'created_by');
		}

		/**
		 * Get the User that owns the Patient.
		 */
		static public function getByPhone($phone, $account_id = false, $patient_id = false)
		{
			$where = array();

			$where[] = array(
				'phone',
				'=',
				$phone
			);
			$where[] = array(
				'user_type_id',
				'=',
				self::$USER_TYPE
			);
			if ($patient_id) {
				$where[] = array(
					'id',
					'=',
					$patient_id
				);
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
		{   if($flag == 1){
			$patient=Patients::find($data['patient_id']);
			if(!$patient){
				$record = Patients::create($data);
				AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);
				return $record;
			}else{
				return $patient;
			}
		}else{
			$record = Patients::create($data);
			AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);
			return $record;
		}
		}

		/**
		 * update Record
		 *
		 * @param data
		 *
		 * @return (mixed)
		 */
		static public function updateRecord($id, $data, $appointmentData = false, $patientData = false)
		{
			if ($appointmentData) {
				if ($appointmentData['patient_id'] != 0) {

					$old_data = (Patients::find($appointmentData['patient_id']))->toArray();
				}
				if(isset($appointmentData['patient_id_1'])){
					if($appointmentData['patient_id'] == 0){
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
				if (!$record) {
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
		static public function getActiveOnly($patientId = false)
		{
			if ($patientId && !is_array($patientId)) {
				$patientId = array($patientId);
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
		 * @param \Illuminate\Http\Request $request
		 * @param (int) $account_id Current Organization's ID
		 *
		 * @return (mixed)
		 */
		static public function getTotalRecords(Request $request, $account_id = false, $apply_filter, $filename)
		{

			$where = self::filters_patients($request, $account_id, $apply_filter, $filename);

			if (count($where)) {
				return self::where($where)->count();
			} else {
				return self::count();
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
		static public function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter, $filename)
		{

			$where = self::filters_patients($request, $account_id, $apply_filter, $filename);

			$orderBy = 'created_at';
			$order = 'desc';

			if ($request->get('order')[0]['dir']) {
				$orderColumn = $request->get('order')[0]['column'];
				$orderBy = $request->get('columns')[$orderColumn]['data'];
				$order = $request->get('order')[0]['dir'];
			}

			if (count($where)) {
				return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
			} else {
				return self::limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
			}
		}

		/**
		 * Delete Record
		 *
		 * @param id
		 *
		 * @return (mixed)
		 */
		static public function DeleteRecord($id)
		{

			$patient = self::getData($id);

			if (!$patient) {
				flash('Resource not found.')->error()->important();
				return redirect()->route('admin.patients.index');
			}

			// Check if child records exists or not, If exist then disallow to delete it.
			if (self::isChildExists($id, Auth::User()->account_id)) {
				flash('Lead or Appointment exists, unable to delete resource')->error()->important();
				return redirect()->route('admin.patients.index');
			}

			$record = $patient->delete();

			//log request for delete for audit trail

			AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

			flash('Record has been deleted successfully.')->success()->important();

			return $record;
		}

		/**
		 * inactive Record
		 *
		 * @param id
		 *
		 * @return (mixed)
		 */
		static public function InactiveRecord($id)
		{
			$patient = self::getData($id);

			if (!$patient) {
				flash('Resource not found.')->error()->important();
				return redirect()->route('admin.patients.index');
			}

			$record = $patient->update(['active' => 0]);

			flash('Record has been inactivated successfully.')->success()->important();

			AuditTrails::inactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

			return $record;
		}

		/**
		 * active Record
		 *
		 * @param id
		 *
		 * @return (mixed)
		 */
		static public function activeRecord($id)
		{

			$patient = self::getData($id);

			if (!$patient) {
				flash('Resource not found.')->error()->important();
				return redirect()->route('admin.patients.index');
			}

			$record = $patient->update(['active' => 1]);

			flash('Record has been activated successfully.')->success()->important();

			AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

			return $record;

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

		static public function filters_patients($request, $account_id, $apply_filter, $filename)
		{
			$where = array();

			$where[] = array(
				'user_type_id',
				'=',
				self::$USER_TYPE
			);

			if ($account_id) {
				$where[] = array(
					'account_id',
					'=',
					$account_id
				);
				Filters::put(Auth::user()->id, $filename, 'account_id', $account_id);
			} else {
				if ($apply_filter) {
					Filters::forget(Auth::user()->id, $filename, 'account_id');
				} else {
					if (Filters::get(Auth::user()->id, $filename, 'account_id')) {
						$where[] = array(
							'account_id',
							'=',
							Filters::get(Auth::user()->id, $filename, 'account_id')
						);
					}
				}
			}

			if ($request->get('id') && $request->get('id') != '') {
				$where[] = array(
					'id',
					'like',
					'%' . GeneralFunctions::patientSearch($request->get('id')) . '%'
				);
				Filters::put(Auth::user()->id, $filename, 'id', $request->get('id'));
			} else {
				if ($apply_filter) {
					Filters::forget(Auth::user()->id, $filename, 'id');
				} else {
					if (Filters::get(Auth::user()->id, $filename, 'id')) {
						$where[] = array(
							'id',
							'like',
							'%' . Filters::get(Auth::user()->id, $filename, 'id') . '%'
						);
					}
				}
			}

			if ($request->get('name') && $request->get('name') != '') {
				$where[] = array(
					'name',
					'like',
					'%' . $request->get('name') . '%'
				);
				Filters::put(Auth::user()->id, $filename, 'name', $request->get('name'));
			} else {
				if ($apply_filter) {
					Filters::forget(Auth::user()->id, $filename, 'name');
				} else {
					if (Filters::get(Auth::user()->id, $filename, 'name')) {
						$where[] = array(
							'name',
							'like',
							'%' . Filters::get(Auth::user()->id, $filename, 'name') . '%'
						);
					}
				}
			}

			if ($request->get('email') && $request->get('email') != '') {
				$where[] = array(
					'email',
					'like',
					'%' . $request->get('email') . '%'
				);
				Filters::put(Auth::user()->id, $filename, 'email', $request->get('email'));
			} else {
				if ($apply_filter) {
					Filters::forget(Auth::user()->id, $filename, 'email');
				} else {
					if (Filters::get(Auth::user()->id, $filename, 'email')) {
						$where[] = array(
							'email',
							'like',
							'%' . Filters::get(Auth::user()->id, $filename, 'email') . '%'
						);
					}
				}
			}

			if ($request->get('gender') && $request->get('gender') != '') {
				$where[] = array(
					'gender',
					'like',
					'%' . $request->get('gender') . '%'
				);
				Filters::put(Auth::user()->id, $filename, 'gender', $request->get('gender'));
			} else {
				if ($apply_filter) {
					Filters::forget(Auth::user()->id, $filename, 'gender');
				} else {
					if (Filters::get(Auth::user()->id, $filename, 'gender')) {
						$where[] = array(
							'gender',
							'like',
							'%' . Filters::get(Auth::user()->id, $filename, 'gender') . '%'
						);
					}
				}
			}

			if ($request->get('phone') && $request->get('phone') != '') {
				$where[] = array(
					'phone',
					'like',
					'%' . GeneralFunctions::cleanNumber($request->get('phone')) . '%'
				);
				Filters::put(Auth::user()->id, $filename, 'phone', $request->get('phone'));
			} else {
				if ($apply_filter) {
					Filters::forget(Auth::user()->id, $filename, 'phone');
				} else {
					if (Filters::get(Auth::user()->id, $filename, 'phone')) {
						$where[] = array(
							'users.phone',
							'like',
							'%' . GeneralFunctions::cleanNumber(
								Filters::get(Auth::User()->id, $filename, 'phone')
							) . '%'
						);
					}
				}
			}

			if ($request->get('created_from') && $request->get('created_from') != '') {
				$where[] = array(
					'created_at',
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
							'created_at',
							'>=',
							Filters::get(Auth::User()->id, $filename, 'created_from') . ' 00:00:00'
						);
					}
				}
			}

			if ($request->get('created_to') != '') {
				$where[] = array(
					'created_at',
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
							'created_at',
							'<=',
							Filters::get(Auth::User()->id, $filename, 'created_to') . ' 23:59:59'
						);
					}
				}
			}

			if ( $request->get('status') && $request->get('status') != null || $request->get('status') == 0 && $request->get('status') != null ){
				$where[] = array(
					'active',
					'=',
					$request->get('status')
				);
				Filters::put(Auth::user()->id, $filename, 'status', $request->get('status'));
			} else {
				if ( $apply_filter ){
					Filters::forget( Auth::user()->id, $filename, 'status');
				} else {
					if ( Filters::get(Auth::user()->id, $filename, 'status' ) == 0 || Filters::get(Auth::user()->id, $filename, 'status' ) == 1 ){
						if ( Filters::get(Auth::user()->id, $filename, 'status' ) != null ){
							$where[] = array(
								'active',
								'=',
								Filters::get( Auth::user()->id, $filename, 'status')
							);
						}
					}
				}
			}

			return $where;
		}


	}
