<?php

	namespace App\Models;

	use App\Helpers\Filters;
    use App\Helpers\GeneralFunctions;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Database\Eloquent\SoftDeletes;
	use Carbon\Carbon;

	class PackageAdvances extends BaseModal
	{
		use SoftDeletes;

		protected $fillable = ['cash_flow', 'cash_amount', 'active', 'patient_id', 'payment_mode_id', 'account_id', 'appointment_type_id', 'appointment_id','location_id', 'created_by', 'updated_by', 'created_at', 'updated_at', 'package_id', 'deleted_at','invoice_id','is_cancel','is_tax'];

		protected static $_fillable = ['cash_flow', 'cash_amount', 'active', 'patient_id', 'payment_mode_id', 'appointment_type_id', 'appointment_id','location_id', 'created_by', 'updated_by', 'package_id','invoice_id','is_cancel','is_tax', 'created_at', 'updated_at', 'deleted_at'];

		protected $table = 'package_advances';

		protected static $_table = 'package_advances';
		
		/*
		 * get the payment modes
		 * */
		public function paymentmode()
		{
			return $this->belongsTo('App\Models\PaymentModes', 'payment_mode_id')->withTrashed();
		}

		/*
		 * get the payment modes
		 * */
		public function package()
		{
			return $this->belongsTo('App\Models\Packages', 'package_id')->withTrashed();
		}
		/*
		 * get the location according to package advance location
		 */
		public function location()
		{
			return $this->belongsTo('App\Models\Locations', 'location_id')->withTrashed();
		}

		/*
		* get the user
		* */
		public function user()
		{
			return $this->belongsTo(User::class, 'patient_id')->withTrashed();
		}

		/*
		* get the Invoice information
		*/
		public function invoice()
		{
			return $this->belongsTo('App\Models\Invoices', 'invoice_id')->withTrashed();
		}

		/*
		* get the appointment information
		*/
		public function appointment()
		{
			return $this->belongsTo('App\Models\Appointments', 'appointment_id')->withTrashed();
		}

		/*
		 * Create Record
		 *
		 * @param $data
		 *
		 * $return mixed
		 *
		 * */
		static public function createRecord($data, $parent_data)
		{
			
			$parent_id = $parent_data->id;
			$record = new PackageAdvances();
			$record->cash_flow = 'in';
			$record->cash_amount = $data['cash_amount'];
			$record->account_id = Auth::User()->account_id;
			$record->patient_id = $data['patient_id'];
			$record->payment_mode_id = $data['payment_mode_id'];
			$record->created_by = Auth::User()->id;
			$record->updated_by = Auth::User()->id;
			$record->package_id = $data['package_id'];
			$record->location_id = $data['location_id'];
			$record->updated_at = Filters::getCurrentTimeStamp();
			$record->appointment_id = $parent_data->appointment_id;
			$record->save();
			AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record, $parent_id);
			return $record;
		}

		/*
		 * Create Record
		 *
		 * @param $data
		 *
		 * $return mixed
		 *
		 * */
		static public function createRecord_forinvoice($data)
		{
			$record = self::create($data);

			AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

			return $record;
		}

		/*
		 * Update Record
		 *
		 * @param $data
		 *
		 * $return mixed
		 *
		 * */
		static public function updateRecord($data, $parent_data)
		{
			$packagebundleIds = PackageBundles::where([
				'package_id' => $data['package_id'],
				'is_allocate' => '1'
			])->pluck('id');
			
			$GetAppointment = Appointments::join('invoices','appointments.id','invoices.appointment_id')
			->where(['appointments.patient_id' => $data['patient_id'] , 'appointments.appointment_type_id' => 1])
			->select('appointments.id')
			->latest('invoices.created_at')->first();
			$GetInvoiceInfo = Invoices::where(['appointment_id' => $GetAppointment->id])->first();
			$packageservicez = PackageService::with('service')->whereIn('package_bundle_id',$packagebundleIds)
			->where('created_at','>',Carbon::parse($GetInvoiceInfo->created_at))
			->get();
			$id = $parent_data->id;
			if(count($packageservicez)  > 0){
				$record = new PackageAdvances();
				$record->cash_flow = 'in';
				$record->cash_amount = $data['cash_amount'];
				$record->account_id = Auth::User()->account_id;
				$record->patient_id = $data['patient_id'];
				$record->payment_mode_id = $data['payment_mode_id'];
				$record->created_by = Auth::User()->id;
				$record->updated_by = Auth::User()->id;
				$record->package_id = $data['package_id'];
				$record->location_id = $data['location_id'];
				$record->updated_at = Filters::getCurrentTimeStamp();
				$record->appointment_id = $GetAppointment->id;
				$record->save();
			}else{
				$record = new PackageAdvances();
				$record->cash_flow = 'in';
				$record->cash_amount = $data['cash_amount'];
				$record->account_id = Auth::User()->account_id;
				$record->patient_id = $data['patient_id'];
				$record->payment_mode_id = $data['payment_mode_id'];
				$record->created_by = Auth::User()->id;
				$record->updated_by = Auth::User()->id;
				$record->package_id = $data['package_id'];
				$record->location_id = $data['location_id'];
				$record->updated_at = Filters::getCurrentTimeStamp();
				$record->appointment_id = $parent_data->appointment_id;
				$record->save();
			}
			$old_data = '0';

			AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

			return $record;
		}

		/*
		 * Update Record from treatment plan finance edit
		 *
		 * @param $data
		 *
		 * $return mixed
		 */

		static public function updateRecordFinanceedit($request,$account_id,$amount_status)
		{
			
			$old_data = (self::find($request->package_advances_id))->toArray();
			if($amount_status){
				$data['cash_amount'] = $request->cash_amount;
			}
			$data['payment_mode_id'] = $request->payment_mode_id;
			$data['payment_mode_id'] = $request->payment_mode_id;
			$data['created_at'] = $request->created_at.' '.Carbon::now()->toTimeString();
			$data['updated_at'] = now();
			$record = PackageAdvances::where(['id' => $request->package_advances_id,'account_id' => $account_id])->first();
			if (!$record) {
				return null;
			}
			$record->update($data);
			AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $request->package_advances_id);
			return true;
		}


		/*
		 * Create Record
		 *
		 * */
		static public function createRecord_onlyadvances($data)
		{

			$record = self::create($data);

			AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

			return $record;
		}

		/*
		 * update Record
		 *
		 * */
		static public function updateRecord_onlyadvances($data, $id)
		{

			$old_data = (PackageAdvances::find($id))->toArray();

			$record = self::where([
				'id' => $id,
			])->first();

			$record->update($data);

			AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

			return $record;
		}


		/**
		 * inactive Record
		 *
		 * @param id
		 *
		 * @return (mixed)
		 */
		static public function inactiveRecord($id)
		{

			$packagesadvances = PackageAdvances::getData($id);

			if (!$packagesadvances) {
				flash('Resource not found.')->error()->important();
				return redirect()->route('admin.packageadvances.index');
			}

			$record = $packagesadvances->update(['active' => 0]);

			flash('Record has been inactivated successfully.')->success()->important();

			AuditTrails::InactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

			return $record;
		}

		/**
		 * active Record
		 *
		 * @param id
		 *
		 * @return (mixed)
		 */
		static function activeRecord($id)
		{

			$packagesadvances = PackageAdvances::getData($id);

			if (!$packagesadvances) {

				flash('Resource not found.')->error()->important();
				return redirect()->route('admin.packagesadvances.index');
			}

			$record = $packagesadvances->update(['active' => 1]);

			flash('Record has been activated successfully.')->success()->important();

			AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

			return $record;
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
			$packagesadvances = PackageAdvances::getData($id);

			if (!$packagesadvances) {

				flash('Resource not found.')->error()->important();
				return redirect()->route('admin.packagesadvances.index');
			}

			// Check if child records exists or not, If exist then disallow to delete it.
			if (PackageAdvances::isChildExists($id, Auth::User()->account_id)) {

				flash('Child records exist, unable to delete resource')->error()->important();
				return redirect()->route('admin.packagesadvances.index');
			}

			$record = $packagesadvances->delete();

			//log request for delete for audit trail

			AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

			flash('Record has been deleted successfully.')->success()->important();

			return $record;

		}

		/*
		 *Delete the rocord of cash in finance editing
		 */
		static public function deletefinaceRecord($request){

			$package_advance = Self::withTrashed()->find($request->package_advance_id);


			$record = $package_advance->delete();

			$data = $package_advance->toArray() ;

//        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $request->package_advance_id);

			AuditTrails::softDeleteEventLogger(self::$_table, 'delete', $data, self::$_fillable, $request->package_advance_id );

			return $record;
		}

		/**
		 * Cancel Record
		 *
		 * @param $id
		 *
		 * @return (mixed)
		 */
		static public function CancelRecord($id,$account_id){

			$old_data = (PackageAdvances::find($id))->toArray();

			$record = self::where([
				'id' => $id,
				'account_id' => $account_id
			])->first();

			if (!$record) {
				return null;
			}
			$record->update(['is_cancel' => '1']);

			$data = (PackageAdvances::find($id))->toArray();

			//AuditTrails::EditEventLogger(self::$_table, 'cancel', $data, self::$_fillable, $old_data, $id);

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
			//        if (
			//        InvoiceDetails::where(['package_id' => $id])->count()
			//        ) {
			//            return true;
			//        }
			//
			//        return false;
		}

		/**
		 * Get Total Records
		 *
		 * @param \Illuminate\Http\Request $request
		 * @param (int) $account_id Current Organization's ID
		 *
		 * @return (mixed)
		 */
		static public function getTotalRecords(Request $request, $account_id = false, $id = false, $apply_filter = false, $filename )
		{

			$where = self::filters_packageAdvances( $request , $account_id , $id , $apply_filter,$filename ) ;

			if (count($where)) {
				return self::where($where)
				//->where('cash_amount', '!=', 0)
				->count();
			} else {
				return self::where('cash_amount', '!=', 0)->count();
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
		static public function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $id = false , $apply_filter = false,$filename )
		{
            list($orderBy, $order) = getSortBy($request, 'created_at', 'DESC');

			$where = self::filters_packageAdvances( $request , $account_id , $id , $apply_filter, $filename ) ;
			if (count($where)) {
				return self::where($where)->where('cash_amount', '!=', 0)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
			} else {
				return self::where('cash_amount', '!=', 0)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
			}
		}

		static public function filters_packageAdvances( $request , $account_id , $id = false , $apply_filter = false,$filename ){


            $where = array();

            $filters = getFilters($request->all());

			if($id != false){
				$where[] = array(
					'patient_id',
					'=',
					$id
				);
				Filters::put(Auth::user()->id , $filename, 'id', $id ) ;
			} else {
				if ($apply_filter){
					Filters::forget(Auth::user()->id , $filename, 'id');
				} else {
					if (Filters::get(Auth::user()->id, $filename, 'id')){
						$where[] = array(
							'patient_id',
							'=',
							Filters::get(Auth::user()->id , $filename, 'id')
						);
					}
				}
			}

			if ($account_id) {
				$where[] = array(
					'account_id',
					'=',
					$account_id
				);
				Filters::put(Auth::user()->id , $filename, 'account_id' , $account_id);
			} else {
				if ($apply_filter){
					Filters::forget( Auth::user()->id , $filename , 'account_id') ;
				} else {
					if (Filters::get(Auth::user()->id , $filename, 'account_id')){
						$where[] = array(
							'account_id',
							'=',
							Filters::get(Auth::user()->id , $filename, 'account_id')
						);
					}
				}
			}
			if (hasFilter($filters, 'patient_id')) {
				$where[] = array(
					'patient_id',
					'=',
                    $filters['patient_id']
				);
				Filters::put(Auth::user()->id , $filename, 'patient_id',  $filters['patient_id']);
			} else {
				if ( $apply_filter ){
					Filters::forget( Auth::user()->id , $filename, 'patient_id');
				} else {
					if (Filters::get(Auth::user()->id , $filename , 'patient_id')){
						$where[] = array(
							'patient_id',
							'=',
							Filters::get(Auth::user()->id , $filename, 'patient_id')
						);
					}
				}
			}
			if (hasFilter($filters, 'package_id')) {
				$where[] = array(
					'package_id',
					'like',
					'%' . $filters['package_id'] . '%'
				);
			}

            /*if (hasFilter($filters, 'id')) {
				$where[] = array(
					'patient_id',
					'like',
					'%' . \App\Helpers\GeneralFunctions::patientSearch($filters['id']) . '%'
				);
			}*/


            if (hasFilter($filters, 'id')) {
                $where[] = array(
                    'patient_id',
                    'like',
                    '%' . GeneralFunctions::patientSearch($filters['id']) . '%'
                );
                Filters::put(Auth::user()->id , $filename, 'id',  $filters['id']);
            } else {
                if ( $apply_filter ){
                    Filters::forget( Auth::user()->id , $filename, 'id');
                } else {
                    if (Filters::get(Auth::user()->id , $filename , 'id')){
                        $where[] = array(
                            'patient_id',
                            'like',
                            '%' . GeneralFunctions::patientSearch(Filters::get(Auth::user()->id , $filename, 'id')) . '%'
                        );
                    }
                }
            }


			if (hasFilter($filters, 'cash_flow')) {
				$where[] = array(
					'cash_flow',
					'like',
					'%' . $filters['cash_flow'] . '%'
				);
			}
			if (hasFilter($filters, 'payment_mode_id')) {
				$where[] = array(
					'payment_mode_id',
					'like',
					'%' . $filters['payment_mode_id'] . '%'
				);
			}
			if (hasFilter($filters, 'is_refund')) {
				$where[] = array(
					'is_refund',
					'=',
                    $filters['is_refund']
				);
			}
			if (hasFilter($filters, 'is_cancel')) {
				$where[] = array(
					'is_cancel',
					'=',
					$filters['is_cancel']
				);
			}


			if (hasFilter($filters, 'created_from')) {
				$where[] = array(
					'created_at',
					'>=',
                    $filters['created_from'] . ' 00:00:00'
				);
				Filters::put(Auth::User()->id, $filename, 'created_from', $filters['created_from'] . ' 00:00:00');
			} else {
				if ($apply_filter) {
					Filters::forget(Auth::User()->id, $filename, 'created_from');
				} else {
					if (Filters::get(Auth::User()->id, $filename, 'created_from')) {
						$where[] = array(
							'created_at',
							'>=',
							Filters::get(Auth::User()->id, $filename, 'created_from')
						);
					}
				}
			}

			if (hasFilter($filters, 'created_to')) {
				$where[] = array(
					'created_at',
					'<=',
                    $filters['created_to'] . ' 23:59:59'
				);
				Filters::put(Auth::User()->id, $filename, 'created_to', $filters['created_to'] . ' 23:59:59');
			} else {
				if ($apply_filter) {
					Filters::forget(Auth::User()->id, $filename, 'created_to');
				} else {
					if (Filters::get(Auth::User()->id, $filename, 'created_to')) {
						$where[] = array(
							'created_at',
							'<=',
							Filters::get(Auth::User()->id, $filename, 'created_to')
						);
					}
				}
			}
			return $where ;
		}

		static public function getAppointmentPackage($appointment_id, $patient_id, $id = null){
			if (is_null($id)) {
				$cash_amount=self::where([
					['appointment_id', '=', $appointment_id],
					['patient_id', '=', $patient_id],
					['cash_flow', '=', 'out']
				])->sum('cash_amount');
			} else {
				$cash_amount=self::where([
					['id', '=', $id],
					['appointment_id', '=', $appointment_id],
					['patient_id', '=', $patient_id],
					['cash_flow', '=', 'out']
				])->value('cash_amount');
			}

			return $cash_amount;
		}
	}
