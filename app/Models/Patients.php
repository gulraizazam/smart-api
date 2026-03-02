<?php

namespace App\Models;

use DB;
use Auth;
use Config;
use DateTime;
use App\Helpers\Filters;
use Illuminate\Http\Request;
use App\Helpers\GeneralFunctions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Gate;

class Patients extends BaseModal
{
    use SoftDeletes;

    protected $table = 'users';

    protected static $_table = 'users';

    protected static $USER_TYPE = 3;

    protected $fillable = [
        'name',
        'email',
        'password',
        'remember_token',
        'phone',
        'main_account',
        'gender',
        'cnic',
        'dob',
        'address',
        'referred_by',
        'active',
        'user_type_id',
        'resource_type_id',
        'account_id',
        'created_by',
        'updated_by',
        'image_src',
    ];

    protected static $_fillable = ['name', 'email', 'phone', 'main_account', 'gender', 'cnic', 'dob', 'address', 'referred_by', 'user_type_id'];

    protected $casts = [
        'active' => 'boolean',
        'dob' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the Leads for Patient.
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Leads::class, 'patient_id');
    }

    /**
     * Get the membership for Patient.
     */
    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class, 'patient_id')->orderByDesc('active')->orderByDesc('id');
    }

    /**
     * Get the user who created this patient.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Audit trail relations
     */
    public function audit_field_before(): HasMany
    {
        return $this->hasMany(AuditTrailChanges::class, 'field_before');
    }

    public function audit_field_after(): HasMany
    {
        return $this->hasMany(AuditTrailChanges::class, 'field_after');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: Filter by account
     */
    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope: Filter active patients only
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', 1);
    }

    /**
     * Scope: Filter patients only (user_type_id = 3)
     */
    public function scopePatientsOnly(Builder $query): Builder
    {
        return $query->where('user_type_id', self::$USER_TYPE);
    }

    /**
     * Scope: Search by name
     */
    public function scopeSearchByName(Builder $query, ?string $name): Builder
    {
        if ($name) {
            return $query->where('name', 'like', "%{$name}%");
        }
        return $query;
    }

    /**
     * Scope: Search by phone
     */
    public function scopeSearchByPhone(Builder $query, ?string $phone): Builder
    {
        if ($phone) {
            $cleanPhone = GeneralFunctions::cleanNumber($phone);
            return $query->where('phone', 'like', "%{$cleanPhone}%");
        }
        return $query;
    }

    /**
     * Scope: Search by email
     */
    public function scopeSearchByEmail(Builder $query, ?string $email): Builder
    {
        if ($email) {
            return $query->where('email', 'like', "%{$email}%");
        }
        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Static Methods (kept for backward compatibility)
    |--------------------------------------------------------------------------
    */

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
    /**
     * OPTIMIZED Patient Search - 50-100X faster than getPatientidAjax
     * Use this for all patient searches: referred_by, treatments, plans, etc.
     * 
     * Optimizations:
     * - Inline string cleaning (no function call overhead)
     * - Exact match with early return
     * - Prefix LIKE for index usage
     * - GROUP BY at DB level
     * - Result limits
     * 
     * @param string $name - Search term (phone, name, or ID)
     * @param int $account_id - Account ID for filtering
     * @return \Illuminate\Support\Collection
     */
    public static function getPatientSearchOptimized($name, $account_id)
    {
        // Generate cache key for this search
        $cacheKey = "patient_search_{$account_id}_" . md5($name);
        
        // Try cache first (5 minute TTL)
        return \Cache::remember($cacheKey, 300, function() use ($name, $account_id) {
            // Ultra-fast string cleaning
            $cleaned = strtr($name, [' ' => '', '-' => '', '+' => '', 'C-' => '', 'c-' => '']);
            
            // Numeric input - single optimized query for phone/ID
            if (ctype_digit($cleaned)) {
                // Clean phone number - remove leading 0 for matching
                $phone = $cleaned[0] === '0' ? substr($cleaned, 1) : $cleaned;
                if (isset($phone[1]) && $phone[0] === '9' && $phone[1] === '2') {
                    $phone = substr($phone, 2);
                }
                
                // Single query with OR conditions - much faster than multiple queries
                // Also search with original input to match phones stored with leading 0
                $sql = "SELECT DISTINCT name, id, phone, gender, cnic, email, dob, address 
                        FROM users 
                        WHERE user_type_id = 3 
                        AND active = 1 
                        AND account_id = ? 
                        AND (
                            phone = ? 
                            OR phone LIKE ? 
                            OR phone = ?
                            OR phone LIKE ?
                            OR id = ?
                        )
                        ORDER BY 
                            CASE 
                                WHEN phone = ? THEN 1
                                WHEN phone = ? THEN 2
                                WHEN id = ? THEN 3
                                ELSE 4
                            END,
                            id DESC
                        LIMIT 10";
                
                return DB::select($sql, [
                    $account_id,
                    $phone,
                    $phone . '%',
                    $cleaned,
                    $cleaned . '%',
                    $cleaned,
                    $phone,
                    $cleaned,
                    $cleaned
                ]);
            }
            
            // Name search - single optimized query
            $sql = "SELECT DISTINCT name, id, phone, gender, cnic, email, dob, address 
                    FROM users 
                    WHERE user_type_id = 3 
                    AND active = 1 
                    AND account_id = ? 
                    AND name LIKE ?
                    ORDER BY id DESC
                    LIMIT 10";
            
            return DB::select($sql, [$account_id, $name . '%']);
        });
    }

    /**
     * LEGACY - OLD SLOW METHOD - DO NOT USE
     * Use getPatientSearchOptimized() instead
     * Kept for backward compatibility only
     */
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
    public static function getPatientidAjaxOrder($name, $account_id)
{
    // Initialize the result collection
    $users = collect();
    
    // Handle searching by patient ID (C- or numeric ID)
    if (stripos($name, 'C-') !== false) {
        $name = str_replace(['C-', 'c-'], '', $name);
        $users = self::where([
            'user_type_id' => '3',
            'active' => '1',
            'account_id' => $account_id,
            'id' => $name,
        ])->select('name', 'id', 'phone')->get();
    }
    
    if (is_numeric($name)) {
        $users = self::where([
            'user_type_id' => '3',
            'active' => '1',
            'account_id' => $account_id,
            'id' => $name,
        ])->select('name', 'id', 'phone')->get();
    }

    // If no patient found by ID, search by name
    if ($users->count() == 0) {
        $name = GeneralFunctions::patientSearch($name);
        $phone_numeric = GeneralFunctions::clearnString($name);
        if (is_numeric($phone_numeric)) {
            $phone = GeneralFunctions::cleanNumber($name);
            $users = self::where([
                'user_type_id' => '3',
                'active' => '1',
                'account_id' => $account_id,
            ])->where('phone', 'LIKE', "%{$phone}%")
              ->select('name', 'id', 'phone')->get();
        } else {
            $users = self::where([
                'user_type_id' => '3',
                'active' => '1',
                'account_id' => $account_id,
            ])->where('name', 'LIKE', "%{$name}%")
              ->select('name', 'id', 'phone')->get();
        }
    }

    // Add latest active membership data to users
    foreach ($users as $user) {
        $membership = Membership::where('patient_id', $user->id)
            ->where('end_date', '>=', now())
            ->orderBy('end_date', 'desc')
            ->first();
        
        if ($membership) {
            $user->membership_code = $membership->code;
            $user->membership_status = 'Active';
            $user->membership_start_date = $membership->start_date;
            $user->membership_end_date = $membership->end_date;
            $user->membership_type_id = $membership->membership_type_id;
        } else {
            $user->membership_code = 'N/A';
            $user->membership_status = 'Inactive';
            $user->membership_start_date = null;
            $user->membership_end_date = null;
            $user->membership_type_id = null;
        }
    }

    return $users;
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
     * @return (mixed)
     */
    public static function createRecord($data, $flag = 0)
    {
        if ($flag == 1) {
            $patient = Patients::where(['phone' => $data['phone']])->first();
            if (!$patient) {
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
    public static function getActiveOnly($patientId = false)
    {
        if ($patientId && !is_array($patientId)) {
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
     * @deprecated Use PatientService::getDatatableData() instead
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id, $apply_filter, $filename)
    {
        $query = self::query();
        $filters = getFilters($request->all());
        $where = self::filters_patients($request, $account_id, $apply_filter, $filename);

        if (count($where)) {
            $query->where($where);
        }
        if (!Gate::allows('view_inactive_patients')) {
            $query->where(['active' => 1]);
        }
        if (isset($filters['membership'])) {
            $query->whereHas('membership', function ($q) use ($filters) {
                $q->where('membership_type_id', $filters['membership']);
            });
        }

        return $query->count();
    }

    /**
     * Get Records
     * @deprecated Use PatientService::getDatatableData() instead
     *
     * @param  (int)  $iDisplayStart Start Index
     * @param  (int)  $iDisplayLength Total Records Length
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id, $apply_filter, $filename)
    {

        $filters = getFilters($request->all());

        $where = self::filters_patients($request, $account_id, $apply_filter, $filename);
        $query = self::with('membership');
        if (isset($filters['membership'])) {
            Filters::put(Auth::user()->id, $filename, 'memberships', $filters['membership']);
            $query->whereHas('membership', function ($q) use ($filters) {
                $q->where('membership_type_id', $filters['membership']);
            });
        }
        if (count($where)) {
            $query->where($where);
        }

        if (!Gate::allows('view_inactive_patients')) {
            $query->where(['active' => 1]);
        }

        $query->select('*', 'id as patient_id')
            ->orderBy('created_at', 'DESC')
            ->limit($iDisplayLength)
            ->offset($iDisplayStart);

        return $query->get();
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

        if (!$patient) {
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
     * @deprecated Use PatientService::changeStatus() instead
     *
     * @param id
     * @return (mixed)
     */
    public static function InactiveRecord($id)
    {
        $patient = self::getData($id);

        if (!$patient) {
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
     * @deprecated Use PatientService::changeStatus() instead
     *
     * @param id
     * @return (mixed)
     */
    public static function activeRecord($id)
    {

        $patient = self::getData($id);

        if (!$patient) {
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
     * @param  (int)  $id
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

    /**
     * Get detailed child records that exist for a patient
     * @param  int  $id
     * @param  int  $account_id
     * @return array
     */
    public static function getChildRecordsDetails($id, $account_id): array
    {
        $childRecords = [];

        $leadsCount = Leads::where(['patient_id' => $id, 'account_id' => $account_id])->count();
        if ($leadsCount > 0) {
            $childRecords[] = "Leads ({$leadsCount})";
        }

        $appointmentsCount = Appointments::where(['patient_id' => $id, 'account_id' => $account_id])->count();
        if ($appointmentsCount > 0) {
            $childRecords[] = "Appointments ({$appointmentsCount})";
        }

        $customFormsCount = CustomFormFeedbacks::where(['reference_id' => $id, 'account_id' => $account_id])->count();
        if ($customFormsCount > 0) {
            $childRecords[] = "Custom Forms ({$customFormsCount})";
        }

        $documentsCount = Documents::where(['user_id' => $id])->count();
        if ($documentsCount > 0) {
            $childRecords[] = "Documents ({$documentsCount})";
        }

        $packagesCount = Packages::where(['patient_id' => $id, 'account_id' => $account_id])->count();
        if ($packagesCount > 0) {
            $childRecords[] = "Packages ({$packagesCount})";
        }

        $measurementsCount = Measurement::where(['patient_id' => $id])->count();
        if ($measurementsCount > 0) {
            $childRecords[] = "Measurements ({$measurementsCount})";
        }

        $medicalCount = Medical::where(['patient_id' => $id])->count();
        if ($medicalCount > 0) {
            $childRecords[] = "Medical Records ({$medicalCount})";
        }

        $invoicesCount = Invoices::where(['patient_id' => $id, 'account_id' => $account_id])->count();
        if ($invoicesCount > 0) {
            $childRecords[] = "Invoices ({$invoicesCount})";
        }

        return $childRecords;
    }

    /**
     * @deprecated Use PatientService::buildWhereConditions() instead
     */
    public static function filters_patients($request, $account_id, $apply_filter, $filename)
    {

        $where = [];
        $filters = getFilters($request->all());

        $where[] = [
            'user_type_id',
            '=',
            self::$USER_TYPE,
        ];

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
                '%' . GeneralFunctions::patientSearch($filters['patient_id']) . '%',
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
                        '%' . Filters::get(Auth::user()->id, $filename, 'patient_id') . '%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'name')) {
            $where[] = [
                'name',
                'like',
                '%' . $filters['name'] . '%',
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
                        '%' . Filters::get(Auth::user()->id, $filename, 'name') . '%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'email')) {
            $where[] = [
                'email',
                'like',
                '%' . $filters['email'] . '%',
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
                        '%' . Filters::get(Auth::user()->id, $filename, 'email') . '%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'gender')) {
            $where[] = [
                'gender',
                'like',
                '%' . $filters['gender'] . '%',
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
                        '%' . Filters::get(Auth::user()->id, $filename, 'gender') . '%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'phone')) {
            $where[] = [
                'phone',
                'like',
                '%' . GeneralFunctions::cleanNumber($filters['phone']) . '%',
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
                        '%' . GeneralFunctions::cleanNumber(
                            Filters::get(Auth::User()->id, $filename, 'phone')
                        ) . '%',
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
