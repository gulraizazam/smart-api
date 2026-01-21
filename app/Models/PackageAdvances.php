<?php

namespace App\Models;

use Carbon\Carbon;
use App\Helpers\ACL;
use App\Helpers\Filters;
use Illuminate\Http\Request;
use App\Helpers\GeneralFunctions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Packages;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\LeadStatuses;
use App\Models\Leads;
use App\Models\LeadsServices;
use App\Models\PackageBundles;
use App\Models\PackageService;

class PackageAdvances extends BaseModal
{
    use SoftDeletes;

    protected $table = 'package_advances';
    
    protected $primaryKey = 'id';
    
    public $incrementing = true;
    
    protected $keyType = 'int';
    
    protected $guarded = ['id'];

    protected $fillable = ['cash_flow', 'cash_amount', 'active', 'patient_id', 'payment_mode_id', 'account_id', 'appointment_type_id', 'appointment_id', 'location_id', 'created_by', 'updated_by', 'created_at', 'updated_at', 'package_id', 'deleted_at', 'invoice_id', 'is_cancel', 'is_tax','is_setteled'];

    protected static $_fillable = ['cash_flow', 'cash_amount', 'active', 'patient_id', 'payment_mode_id', 'appointment_type_id', 'appointment_id', 'location_id', 'created_by', 'updated_by', 'package_id', 'invoice_id', 'is_cancel', 'is_tax', 'created_at', 'updated_at', 'deleted_at'];

    protected static $_table = 'package_advances';

    /**
     * Override create method to ensure id is never set manually
     */
    public static function create(array $attributes = [])
    {
        // Explicitly unset id to prevent any manual id assignment
        unset($attributes['id']);
        
        return static::query()->create($attributes);
    }

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
        public static function createRecord($data, $parent_data)
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

            // Create corresponding plan_invoice record
            self::createPlanInvoice($record);

            // Update lead status to Converted when payment is received
            self::updateLeadStatusToConverted($data['package_id'], $data['account_id'] ?? Auth::user()->account_id);

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
    public static function createRecord_forinvoice($data)
    {
        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        // Create corresponding plan_invoice record if cash_flow is 'in'
        if (isset($data['cash_flow']) && $data['cash_flow'] === 'in') {
            self::createPlanInvoice($record);
        }

        // Update lead status to Converted when payment is received (cash_flow = 'in')
        if (isset($data['cash_flow']) && $data['cash_flow'] === 'in' && isset($data['package_id'])) {
            self::updateLeadStatusToConverted($data['package_id'], $data['account_id'] ?? Auth::user()->account_id);
        }

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
        public static function updateRecord($data, $parent_data)
        {
            $id = $parent_data->id;
            
            // Get the original package creation date
            $package = Packages::find($data['package_id']);
            $packageCreatedAt = $package ? $package->created_at : null;
            
            // Default to the package's current appointment_id
            $appointment_id = $parent_data->appointment_id;
            
            // Get the arrived appointment status
            $arrivedStatus = AppointmentStatuses::where([
                'account_id' => Auth::User()->account_id,
                'is_arrived' => 1
            ])->first();
            
            \Log::info('PackageAdvances::updateRecord Debug', [
                'package_id' => $data['package_id'],
                'patient_id' => $data['patient_id'],
                'packageCreatedAt' => $packageCreatedAt,
                'arrivedStatus' => $arrivedStatus ? $arrivedStatus->id : null,
                'default_appointment_id' => $appointment_id,
            ]);
            
            if ($arrivedStatus && $packageCreatedAt) {
                // Get package bundle IDs for this package (all bundles, not just allocated)
                $packagebundleIds = PackageBundles::where('package_id', $data['package_id'])->pluck('id');
                
                \Log::info('Package Bundle IDs', ['ids' => $packagebundleIds->toArray()]);
                
                // Check if any package services were added AFTER the original package was created
                // (these are new services added during subsequent visits)
                $newPackageServices = PackageService::whereIn('package_bundle_id', $packagebundleIds)
                    ->where('created_at', '>', $packageCreatedAt)
                    ->count();
                
                \Log::info('New Package Services Count', ['count' => $newPackageServices]);
                
                // If new services were added after package creation,
                // find the latest arrived consultation and link payment to it
                if ($newPackageServices > 0) {
                    // Get the latest ARRIVED consultation appointment for this patient
                    // that arrived AFTER the package was created
                    $latestArrivedAppointment = Appointments::where([
                        'patient_id' => $data['patient_id'],
                        'appointment_type_id' => 1, // Consultation
                        'base_appointment_status_id' => $arrivedStatus->id
                    ])
                    ->where('updated_at', '>', $packageCreatedAt)
                    ->orderBy('updated_at', 'desc')
                    ->first();
                    
                    \Log::info('Latest Arrived Appointment', [
                        'found' => $latestArrivedAppointment ? true : false,
                        'appointment_id' => $latestArrivedAppointment ? $latestArrivedAppointment->id : null,
                        'doctor_id' => $latestArrivedAppointment ? $latestArrivedAppointment->doctor_id : null,
                    ]);
                    
                    if ($latestArrivedAppointment) {
                        $appointment_id = $latestArrivedAppointment->id;
                    }
                }
            }
            
            \Log::info('Final appointment_id for payment', ['appointment_id' => $appointment_id]);
            
            // Create the payment record
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
            $record->appointment_id = $appointment_id;
            $record->save();
            
            $old_data = '0';

            AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

            // Update lead status to Converted when payment is received
            self::updateLeadStatusToConverted($data['package_id'], $data['account_id'] ?? Auth::user()->account_id);

            return $record;
        }

    /*
         * Update Record from treatment plan finance edit
         *
         * @param $data
         *
         * $return mixed
         */

        public static function updateRecordFinanceedit($request, $account_id, $amount_status)
        {

            $old_data = (self::find($request->package_advances_id))->toArray();
            if ($amount_status) {
                $data['cash_amount'] = $request->cash_amount;
            }
            $data['payment_mode_id'] = $request->payment_mode_id;
            $data['payment_mode_id'] = $request->payment_mode_id;
            $data['created_at'] = $request->created_at.' '.Carbon::now()->toTimeString();
           //$data['created_at'] =Carbon::now()->timezone('Asia/Karachi');
            $data['updated_at'] = now();
           //$data['updated_at'] =Carbon::now()->timezone('Asia/Karachi');
            $record = PackageAdvances::where(['id' => $request->package_advances_id, 'account_id' => $account_id])->first();
            if (! $record) {
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
    public static function createRecord_onlyadvances($data)
    {

        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        // Create corresponding plan_invoice record if cash_flow is 'in'
        if (isset($data['cash_flow']) && $data['cash_flow'] === 'in') {
            self::createPlanInvoice($record);
        }

        // If payment is received (cash_flow = 'in'), update lead status to Converted
        if (isset($data['cash_flow']) && $data['cash_flow'] === 'in' && isset($data['package_id'])) {
            self::updateLeadStatusToConverted($data['package_id'], $data['account_id'] ?? Auth::user()->account_id);
        }

        return $record;
    }

    /**
     * Update lead status to Converted when payment is received
     * Flow: package_advances -> packages (appointment_id) -> appointments (lead_id) -> leads
     */
    public static function updateLeadStatusToConverted($packageId, $accountId)
    {
        try {
            // Get the package to find the appointment_id
            $package = Packages::find($packageId);
            if (!$package || !$package->appointment_id) {
                return;
            }

            // Get the appointment to find the lead_id
            $appointment = Appointments::find($package->appointment_id);
            if (!$appointment || !$appointment->lead_id) {
                return;
            }

            // Get the default Converted lead status
            $convertedStatus = LeadStatuses::where([
                'account_id' => $accountId,
                'is_converted' => 1,
            ])->first();

            if (!$convertedStatus) {
                return;
            }

            // Update the lead status to Converted
            Leads::where('id', $appointment->lead_id)->update([
                'lead_status_id' => $convertedStatus->id,
            ]);

            // Also update the lead_services status
            LeadsServices::where('lead_id', $appointment->lead_id)
                ->where('service_id', $appointment->service_id)
                ->update(['lead_status_id' => $convertedStatus->id]);

        } catch (\Exception $e) {
            \Log::error('Failed to update lead status to converted: ' . $e->getMessage());
        }
    }

    /**
     * Create corresponding plan_invoice record when package_advance is created with cash_flow = 'in'
     */
    protected static function createPlanInvoice($packageAdvance)
    {
        try {
            // Only create plan_invoice for incoming payments (cash_flow = 'in')
            if ($packageAdvance->cash_flow !== 'in' || !$packageAdvance->package_id) {
                return;
            }

            // Generate invoice number
            $invoiceNumber = \App\Models\PlanInvoice::generateInvoiceNumber(
                $packageAdvance->patient_id,
                $packageAdvance->package_id
            );

            // Create plan_invoice record
            \App\Models\PlanInvoice::create([
                'invoice_number' => $invoiceNumber,
                'total_price' => $packageAdvance->cash_amount,
                'account_id' => $packageAdvance->account_id,
                'patient_id' => $packageAdvance->patient_id,
                'created_by' => $packageAdvance->created_by,
                'location_id' => $packageAdvance->location_id,
                'payment_mode_id' => $packageAdvance->payment_mode_id,
                'active' => 1,
                'package_id' => $packageAdvance->package_id,
                'package_advance_id' => $packageAdvance->id,
                'invoice_type' => 'exempt',
                'created_at' => $packageAdvance->created_at,
                'updated_at' => now(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to create plan_invoice for package_advance: ' . $e->getMessage(), [
                'package_advance_id' => $packageAdvance->id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /*
     * update Record
     *
     * */
    public static function updateRecord_onlyadvances($data, $id)
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
     * @return (mixed)
     */
    public static function inactiveRecord($id)
    {

        $packagesadvances = PackageAdvances::getData($id);

        if (! $packagesadvances) {
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
         * @return (mixed)
         */
        public static function activeRecord($id)
        {
            $packagesadvances = PackageAdvances::getData($id);
            if (! $packagesadvances) {
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
         * @return (mixed)
         */
        public static function DeleteRecord($id)
        {
            $packagesadvances = PackageAdvances::getData($id);
            if (! $packagesadvances) {
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
    public static function deletefinaceRecord($request)
    {

        $package_advance = self::withTrashed()->find($request->package_advance_id);

        $record = $package_advance->delete();

        $data = $package_advance->toArray();

        //        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $request->package_advance_id);

        AuditTrails::softDeleteEventLogger(self::$_table, 'delete', $data, self::$_fillable, $request->package_advance_id);

        return $record;
    }

    /**
     * Cancel Record
     *
     *
     * @return (mixed)
     */
    public static function CancelRecord($id, $account_id)
    {

        $old_data = (PackageAdvances::find($id))->toArray();

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
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

        /**
         * Get Total Records
         *
         * @param  (int)  $account_id Current Organization's ID
         * @return (mixed)
         */
        public static function getTotalRecords(Request $request, $account_id, $id, $apply_filter, $filename)
        {
            $where = self::filters_packageAdvances($request, $account_id, $id, $apply_filter, $filename);
            if (count($where)) {
                return self::where($where)->count();
            } else {
                return self::where('cash_amount', '!=', 0)->count();
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
        [$orderBy, $order] = getSortBy($request, 'created_at', 'DESC');

        $where = self::filters_packageAdvances($request, $account_id, $id, $apply_filter, $filename);
        if (count($where)) {
            return self::where($where)->where('cash_amount', '!=', 0)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
        } else {
            return self::where('cash_amount', '!=', 0)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
        }
    }

        public static function filters_packageAdvances($request, $account_id, $id, $apply_filter, $filename)
        {
            $where = [];
            $filters = getFilters($request->all());
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
                $where[] = ['patient_id', '=', $id];
                Filters::put(Auth::user()->id, $filename, 'id', $id);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::user()->id, $filename, 'id');
                } else {
                    if (Filters::get(Auth::user()->id, $filename, 'id')) {
                        $where[] = ['patient_id', '=', Filters::get(Auth::user()->id, $filename, 'id')];
                    }
                }
            }
            if ($account_id) {
                $where[] = ['account_id', '=', $account_id];
                Filters::put(Auth::user()->id, $filename, 'account_id', $account_id);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::user()->id, $filename, 'account_id');
                } else {
                    if (Filters::get(Auth::user()->id, $filename, 'account_id')) {
                        $where[] = ['account_id', '=', Filters::get(Auth::user()->id, $filename, 'account_id')];
                    }
                }
            }
            if (hasFilter($filters, 'patient_id')) {
                $where[] = ['patient_id', '=', GeneralFunctions::patientSearch($filters['patient_id'])];
                Filters::put(Auth::user()->id, $filename, 'patient_id', $filters['patient_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::user()->id, $filename, 'patient_id');
                } else {
                    if (Filters::get(Auth::user()->id, $filename, 'patient_id')) {
                        $where[] = ['patient_id', '=', Filters::get(Auth::user()->id, $filename, 'patient_id')];
                    }
                }
            }
            if (hasFilter($filters, 'package_id')) {
                $where[] = ['package_id', 'like', '%'.$filters['package_id'].'%'];
            }
            if (hasFilter($filters, 'cash_flow')) {
                $where[] = ['cash_flow', 'like', '%'.$filters['cash_flow'].'%'];
            }
            if (hasFilter($filters, 'payment_mode_id')) {
                $where[] = ['payment_mode_id', 'like', '%'.$filters['payment_mode_id'].'%'];
            }
            if (hasFilter($filters, 'is_refund')) {
                $where[] = ['is_refund', '=', $filters['is_refund']];
            }
            if (hasFilter($filters, 'is_cancel')) {
                $where[] = ['is_cancel', '=', $filters['is_cancel']];
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

            return $where;
        }

    public static function getAppointmentPackage($appointment_id, $patient_id, $id = null)
    {
        if (is_null($id)) {
            $cash_amount = self::where([
                ['appointment_id', '=', $appointment_id],
                ['patient_id', '=', $patient_id],
                ['cash_flow', '=', 'out'],
            ])->sum('cash_amount');
        } else {
            $cash_amount = self::where([
                ['id', '=', $id],
                ['appointment_id', '=', $appointment_id],
                ['patient_id', '=', $patient_id],
                ['cash_flow', '=', 'out'],
            ])->value('cash_amount');
        }

        return $cash_amount;
    }
    public static function getRefundedRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id, $id, $apply_filter, $filename)
    {

        $where = PackageAdvances::filters($request, $account_id, $id, $apply_filter, $filename);

        [$orderBy, $order] = getSortBy($request, 'id', 'DESC');
        if (\Illuminate\Support\Facades\Gate::allows('view_inactive_plans')) {
            return PackageAdvances::when(count($where), fn ($query) => $query->where($where))->where(['is_refund'=>1])->whereIn('location_id', ACL::getUserCentres())
                ->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->groupBy('package_id')
                ->orderby('created_at','desc')
                ->get();
        } else {
            return PackageAdvances::when(count($where), fn ($query) => $query->where($where))->where(['active' => 1,'is_refund'=>1])->whereIn('location_id', ACL::getUserCentres())
                ->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->groupBy('package_id')
                ->orderby('created_at','desc')
                ->get();
        }
    }
    /**
     * Get refunded records for a specific patient with all required data
     */
    public static function getPatientRefundedRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id, $patient_id, $apply_filter, $filename)
    {
        $where = self::filters($request, $account_id, $patient_id, $apply_filter, $filename);

        [$orderBy, $order] = getSortBy($request, 'id', 'DESC');
        
        $query = PackageAdvances::with(['user', 'location.city', 'package'])
            ->when(count($where), fn ($query) => $query->where($where))
            ->where(['is_refund' => 1])
            ->whereIn('location_id', ACL::getUserCentres());
        
        if (!\Illuminate\Support\Facades\Gate::allows('view_inactive_plans')) {
            $query->where('active', 1);
        }
        
        return $query->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->groupBy('package_id')
            ->orderby('created_at', 'desc')
            ->get();
    }

    /**
     * Get total count of refunded records for a specific patient
     */
    public static function getTotalPatientRefundedRecords(Request $request, $account_id, $patient_id, $apply_filter, $filename)
    {
        $where = self::filters($request, $account_id, $patient_id, $apply_filter, $filename);

        $query = Packages::where('is_refund', 1)
            ->whereIn('location_id', ACL::getUserCentres());
        
        if (count($where)) {
            $query->where($where);
        }
        
        if (!\Illuminate\Support\Facades\Gate::allows('view_inactive_plans')) {
            $query->where('active', 1);
        }
        
        return $query->count();
    }

    public static function getTotalRefundedRecords(Request $request, $account_id, $id, $apply_filter, $filename)
    {
        $where = self::filters($request, $account_id, $id, $apply_filter, $filename);

        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_plans')) {
                return Packages::where($where)->where('is_refund',1)->whereIn('location_id', ACL::getUserCentres())->count();
            } else {
                return Packages::where($where)->where('active', 1)->where('is_refund',1)->whereIn('location_id', ACL::getUserCentres())->count();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_plans')) {
                return Packages::whereIn('location_id', ACL::getUserCentres())->count();
            } else {
                return Packages::whereIn('location_id', ACL::getUserCentres())->where('active', 1)->count();
            }
        }
    }
    public static function filters($request, $account_id, $id, $apply_filter, $filename)
    {

        $where = [];

        $filters = getFilters($request->all());
        $apply_filter = checkFilters($filters, $filename);

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
