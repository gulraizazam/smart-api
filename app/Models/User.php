<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected static $PATIENT_GROUP = 3;

    protected static $DOCTOR_GROUP = 5;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $fillable = ['name', 'email', 'password', 'phone', 'main_account', 'gender', 'dob', 'address', 'commission', 'can_perform_consultation', 'user_type_id', 'resource_type_id', 'referred_by', 'account_id', 'active', 'select_all', 'is_advance_eligible'];

    protected static $_fillable = ['name', 'email', 'password', 'phone', 'main_account', 'gender', 'dob', 'address', 'commission', 'can_perform_consultation', 'user_type_id', 'resource_type_id', 'referred_by', 'active', 'select_all'];

    protected static $_table = 'users';

    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * Get the Location name with City Name.
     */
    public function getFullNameAttribute($value)
    {
        return ucfirst($this->name) . ' - ' . strtolower($this->email);
    }
    public function membership()
    {
        return $this->hasOne(Membership::class, 'patient_id');
    }
    public function scopeIsActive($query, $status = 1)
    {
        return $query->where('active', $status);
    }

    /**
     * Get the refunds.
     */
    public function refund()
    {
        return $this->hasMany('App\Models\Refunds', 'patient_id');
    }

    /**
     * Get the invoice.
     */
    public function invoice()
    {
        return $this->hasMany('App\Models\Invoices', 'patient_id');
    }

    /**
     * Get the package infornation.
     */
    public function package()
    {
        return $this->hasMany('App\Models\Packages', 'patient_id');
    }

    /**
     * Hash password
     */
    public function setPasswordAttribute($input)
    {
        if ($input) {
            $this->attributes['password'] = app('hash')->needsRehash($input) ? Hash::make($input) : $input;
        }
    }

    public function role()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function user_roles()
    {
        return $this->belongsToMany(Role::class, 'role_has_users');
    }

    public function getRoles()
    {

        if ($this->user_roles()->count() > 0) {
            return implode(',', $this->user_roles()->pluck('name')->toArray());
        }

        return '';
    }

    /**
     * Get the Users.
     */
    public function doctorhaslocation()
    {

        return $this->hasMany('App\Models\DoctorHasLocations', 'user_id');
    }

    /**
     * Get the Doctors for User.
     */
    public function leads()
    {
        return $this->hasMany('App\Models\Leads', 'created_by');
    }

    /**
     * Get the Appointments for User.
     */
    public function appointments()
    {
        return $this->hasMany('App\Models\Appointments', 'created_by');
    }
    public function appointmentsPatient()
    {
        return $this->hasMany(Appointments::class, 'patient_id');
    }
    public function appointmentsDoc()
    {
        return $this->hasMany('App\Models\Appointments', 'doctor_id');
    }
    public function account()
    {
        return $this->belongsTo('App\Models\Accounts');
    }

    public function audit()
    {
        return $this->hasMany('App\Models\AuditTrails', 'user_id');
    }

    /**
     * Get the Patients for User.
     */
    public function patients()
    {
        return $this->hasMany('App\Models\Patients', 'created_by');
    }

    /*
     * Get the name by whom patient is Referred by
     * */

    public function referredBy()
    {
        return $this->belongsTo(User::class, 'referred_by')->withTrashed();
    }

    /**
     * Get the User Locations for User.
     */
    public function user_has_locations()
    {
        return $this->hasMany('App\Models\UserHasLocations', 'user_id')->withoutGlobalScope(SoftDeletingScope::class);
    }

    /**
     * Get the location for the user.
     */
    public function location()
    {
        return $this->belongsTo('App\Models\Locations', 'location_id')->withTrashed();
    }

    public function user_has_warehouse()
    {
        return $this->hasMany('App\Models\UserHasWarehouse', 'user_id')->withoutGlobalScope(SoftDeletingScope::class);
    }

    /**
     * Get the role has users.
     */
    public function role_has_users()
    {
        return $this->hasMany('App\Models\RoleHasUsers', 'user_id')->withoutGlobalScope(SoftDeletingScope::class);
    }

    /**
     * Get the doctor service.
     */
    public function doctor_has_services()
    {
        return $this->hasMany('App\Models\DoctorHasServices', 'user_id')->withoutGlobalScope(SoftDeletingScope::class);
    }

    /**
     * Get the package advances.
     */
    public function packagesadvances()
    {

        return $this->hasMany('App\Models\PackageAdvances', 'patient_id');
    }

    /**
     * Get the measurement for user.
     */
    public function measurement()
    {
        return $this->hasMany('App\Models\Measurement', 'user_id');
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
    public static function getData($id)
    {

        return self::where([
            ['id', '=', $id],
            ['account_id', '=', Auth::User()->account_id],
        ])->first();
    }

    public static function getUsers()
    {

        return self::where('account_id', '=', Auth::User()->account_id)->whereNull('resource_type_id')->pluck('name', 'id');
    }

    /**
     * Check if user has child records (appointments, locations)
     *
     * @param id, account id
     * @return bool
     */
    public static function isExists($id, $account_id)
    {
        if (
            DoctorHasLocations::where('is_allocated',1)->where(['user_id' => $id])->count() ||
            Appointments::where(['doctor_id' => $id])->count()
        ) {
            return true;
        }

        return false;
    }

    /**
     * Get patients
     * @deprecated Move to PatientService when optimizing Patients module
     *
     * @return (mixed)
     */
    public static function getPatients()
    {

        return self::where([
            ['user_type_id', '=', Config::get('constants.patient_id')],
            ['account_id', '=', Auth::User()->account_id],
        ])->get();
    }

    /**
     * Find user for patient profile and checkout account id
     * @deprecated Move to PatientService when optimizing Patients module
     *
     * @param id
     *
     * @return patient
     */
    public static function finduser($id)
    {
        return self::where([
            ['account_id', '=', Auth::User()->account_id],
            ['id', '=', $id],
        ])->first();
    }

    /**
     * Get All Records
     * @deprecated Move to appropriate service when optimizing related module
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getAllRecords($account_id)
    {
        return self::where(['account_id' => $account_id])->whereNotIn('user_type_id', [self::$PATIENT_GROUP])->get();
    }

    /**
     * Get All Patient Records
     * @deprecated Move to PatientService when optimizing Patients module
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getAllPatientRecords($account_id, array $ids = [])
    {
        if (is_array($ids) && count($ids)) {
            return self::where(['account_id' => $account_id])
                ->whereIn('user_type_id', [self::$PATIENT_GROUP])
                ->whereIn('id', $ids)
                ->get();
        }

        return self::where(['account_id' => $account_id])
            ->whereIn('user_type_id', [self::$PATIENT_GROUP])
            ->get();
    }

    /**
     * Get All Active Records
     * @deprecated Move to appropriate service when optimizing related module
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getAllActiveRecords($account_id, $locationId = false)
    {
        if ($locationId && !is_array($locationId)) {
            $locationId = [$locationId];
        }

        if ($locationId) {
            $query = self::join('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                ->where([
                    ['users.user_type_id', '!=', self::$PATIENT_GROUP],
                    ['users.active', '=', 1],
                    ['users.account_id', '=', $account_id],
                ])->whereIn('user_has_locations.location_id', $locationId)->get();

            return $query;
        } else {
            return self::where(['active' => 1, 'account_id' => $account_id])->whereNotIn('user_type_id', [self::$PATIENT_GROUP])->get();
        }
    }

    /**
     * Get All Active Records for employee
     * @deprecated Move to appropriate service when optimizing related module
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getAllActiveEmployeeRecords($account_id, $locationId = false)
    {
        if ($locationId && !is_array($locationId)) {
            $locationId = [$locationId];
        }

        if ($locationId) {
            $query = self::join('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                ->where([
                    ['users.user_type_id', '!=', self::$PATIENT_GROUP],
                    ['users.active', '=', 1],
                    ['users.account_id', '=', $account_id],
                ])->whereIn('user_has_locations.location_id', $locationId)
                ->select('users.id', 'users.name')
                ->get();

            return $query;
        } else {
            return self::where(['active' => 1, 'account_id' => $account_id])->whereNotIn('user_type_id', [self::$PATIENT_GROUP])->get();
        }
    }

    /**
     * Get All Active Records for practitioners
     * @deprecated Move to DoctorService when optimizing Doctors module
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getAllActivePractionersRecords($account_id, $locationId = false)
    {
        if ($locationId && !is_array($locationId)) {
            $locationId = [$locationId];
        }

        if ($locationId) {
            $query = self::join('doctor_has_locations', 'users.id', '=', 'doctor_has_locations.user_id')
                ->where([
                    ['users.user_type_id', '!=', self::$PATIENT_GROUP],
                    ['users.active', '=', 1],
                    ['users.account_id', '=', $account_id],
                ])->whereIn('doctor_has_locations.location_id', $locationId)
                ->select('users.id', 'users.name')
                ->get();

            return $query;
        } else {
            return self::where(['active' => 1, 'account_id' => $account_id])->whereNotIn('user_type_id', [self::$PATIENT_GROUP])->get();
        }
    }

    /**
     * Get All System Users Active Records
     * @deprecated Move to appropriate service when optimizing related module
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getAllSystemUsersActiveRecords($account_id)
    {
        return self::where(['active' => 1, 'account_id' => $account_id])->whereNotIn('user_type_id', [self::$PATIENT_GROUP, self::$DOCTOR_GROUP])->get();
    }

    /**
     * Get active and sorted data only.
     * @deprecated Move to appropriate service when optimizing related module
     */
    public static function getActiveOnly($locationId = false, $account_id = false, $user_id = false, $pluck_columns = true)
    {
        if ($locationId && !is_array($locationId)) {
            $locationId = [$locationId];
        }
        if ($user_id && !is_array($user_id)) {
            $user_id = [$user_id];
        }

        if ($locationId) {
            if ($account_id) {
                if ($user_id) {
                    $query = self::join('user_has_locations', function ($join) use ($account_id) {
                        $join->on('users.id', '=', 'user_has_locations.user_id')
                            ->where('users.user_type_id', '=', config('constants.application_user_id'))
                            ->where('users.active', '=', 1)
                            ->where('users.account_id', '=', $account_id);
                    })
                        ->whereIn('user_has_locations.location_id', $locationId)
                        ->whereIn('users.id', $user_id)
                        ->get();
                    if ($pluck_columns) {
                        $query = $query->pluck('name', 'user_id');
                    }

                    return $query;
                } else {
                    $query = self::join('user_has_locations', function ($join) use ($account_id) {
                        $join->on('users.id', '=', 'user_has_locations.user_id')
                            ->where('users.user_type_id', '=', config('constants.application_user_id'))
                            ->where('users.active', '=', 1)
                            ->where('users.account_id', '=', $account_id);
                    })
                        ->whereIn('user_has_locations.location_id', $locationId)
                        ->get();
                    if ($pluck_columns) {
                        $query = $query->pluck('name', 'user_id');
                    }

                    return $query;
                }
            }

            if ($user_id) {
                $query = self::join('user_has_locations', function ($join) {
                    $join->on('users.id', '=', 'user_has_locations.user_id')
                        ->where('users.user_type_id', '=', config('constants.application_user_id'))
                        ->where('users.active', '=', 1);
                })
                    ->whereIn('users.id', $user_id)
                    ->whereIn('user_has_locations.location_id', $locationId)
                    ->get();
                if ($pluck_columns) {
                    $query = $query->pluck('name', 'user_id');
                }

                return $query;
            } else {
                $query = self::join('user_has_locations', function ($join) {
                    $join->on('users.id', '=', 'user_has_locations.user_id')
                        ->where('users.user_type_id', '=', config('constants.application_user_id'))
                        ->where('users.active', '=', 1);
                })
                    ->whereIn('user_has_locations.location_id', $locationId)
                    ->get();
                if ($pluck_columns) {
                    $query = $query->pluck('name', 'user_id');
                }

                return $query;
            }
            //            $query = self::whereIn('location_id',$locationId)->get()->pluck('name','id');
        } else {
            if ($account_id) {
                if ($user_id) {
                    $query = self::where('users.user_type_id', '=', config('constants.application_user_id'))
                        ->where('users.active', '=', 1)
                        ->where('users.account_id', '=', $account_id)
                        ->whereIn('users.id', $user_id)
                        ->get();
                    if ($pluck_columns) {
                        $query = $query->pluck('name', 'id');
                    }

                    return $query;
                } else {
                    $query = self::where('users.user_type_id', '=', config('constants.application_user_id'))
                        ->where('users.active', '=', 1)
                        ->where('users.account_id', '=', $account_id)
                        ->get();
                    if ($pluck_columns) {
                        $query = $query->pluck('name', 'id');
                    }

                    return $query;
                }
            }

            if ($user_id) {
                $query = self::where('users.user_type_id', '=', config('constants.application_user_id'))
                    ->where('users.active', '=', 1)
                    ->whereIn('users.id', $user_id)
                    ->get();
                if ($pluck_columns) {
                    $query = $query->pluck('name', 'id');
                }

                return $query;
            } else {
                $query = self::where('users.user_type_id', '=', config('constants.application_user_id'))
                    ->where('users.active', '=', 1)->get();
                if ($pluck_columns) {
                    $query = $query->pluck('name', 'id');
                }

                return $query;
            }
            //            $query = self::get()->pluck('name','id');
        }
    }
}
