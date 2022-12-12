<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Doctors extends BaseModal
{

    protected $fillable = ['name', 'email', 'password', 'remember_token','mobile','main_account','gender','user_type_id','resource_type_id','account_id'];

    protected  $USER_TYPE = 5;
    static protected  $USER_TYPE_STATIC = 5;

    protected $table = 'users';

    /**
     * Get the Location name with City Name.
     */
    public function getFullNameAttribute($value)
    {
        return ucfirst($this->name) . ' - ' . strtolower($this->email);
    }

    /**
     * Get the Doctors that owns the City.
     */
    public function city()
    {
        return $this->belongsTo('App\Models\Cities')->withTrashed();
    }


    /**
     * Get the Doctors that owns the City.
     */
    public function region()
    {
        return $this->belongsTo('App\Models\Regions')->withTrashed();
    }

    /**
     * Get the Doctors that owns the City.
     */
    public static function getAll($account_id)
    {
        return self::where(['user_type_id' => self::$USER_TYPE_STATIC, 'account_id' => $account_id])->get();
    }

    /**
     * Get the Doctors that owns the Location.
     */
    public function location()
    {
        return $this->belongsTo('App\Models\Locations')->withTrashed();
    }
    /**
     * Get the Appointments for Doctors.
     */
    public function appointments()
    {
        return $this->hasMany('App\Models\Appointments', 'doctor_id');
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
    /**
     * Get active and sorted data only.
     */
    static public function getActiveOnly($locationId = false, $account_id = false, $doctor_id = false, $pluck_columns = true)
    {
        if($locationId && !is_array($locationId)) {
            $locationId = array($locationId);
        }
        if ($doctor_id && !is_array($doctor_id)) {
            $doctor_id = array($doctor_id);
        }

        if($locationId) {
            if($account_id) {
                if ($doctor_id) {
                    $query = self::join('doctor_has_locations', function ($join) use ($account_id) {
                        $join->on('users.id', '=', 'doctor_has_locations.user_id')
                            ->where('users.user_type_id', '=', config('constants.asthatic_operator_id'))
                            ->where('users.active', '=', 1)
                            ->where('users.account_id', '=', $account_id);
                    })
                        ->whereIn('doctor_has_locations.location_id', $locationId)
                        ->whereIn('users.id', $doctor_id)
                        ->get();
                    if($pluck_columns) {
                        $query = $query->pluck('name', 'user_id');
                    }
                    return $query;
                } else {
                    $query = self::join('doctor_has_locations', function ($join) use ($account_id) {
                        $join->on('users.id', '=', 'doctor_has_locations.user_id')
                            ->where('users.user_type_id', '=', config('constants.asthatic_operator_id'))
                            ->where('users.active', '=', 1)
                            ->where('users.account_id', '=', $account_id);
                    })
                        ->whereIn('doctor_has_locations.location_id', $locationId)
                        ->get();
                    if($pluck_columns) {
                        $query = $query->pluck('name', 'user_id');
                    }
                    return $query;
                }
            }

            if ($doctor_id) {
                $query = self::join('doctor_has_locations', function ($join) {
                    $join->on('users.id', '=', 'doctor_has_locations.user_id')
                        ->where('users.user_type_id', '=', config('constants.asthatic_operator_id'))
                        ->where('users.active', '=', 1);
                })
                    ->whereIn('users.id', $doctor_id)
                    ->whereIn('doctor_has_locations.location_id', $locationId)
                    ->get();
                if($pluck_columns) {
                    $query = $query->pluck('name', 'user_id');
                }
                return $query;
            } else {
                $query = self::join('doctor_has_locations', function ($join) {
                    $join->on('users.id', '=', 'doctor_has_locations.user_id')
                        ->where('users.user_type_id', '=', config('constants.asthatic_operator_id'))
                        ->where('users.active', '=', 1);
                })
                    ->whereIn('doctor_has_locations.location_id', $locationId)
                    ->get();
                if($pluck_columns) {
                    $query = $query->pluck('name', 'user_id');
                }
                return $query;
            }
//            return self::whereIn('location_id',$locationId)->get()->pluck('name','id');
        } else {
            if($account_id) {
                if ($doctor_id) {
                    $query = self::where('users.user_type_id', '=', config('constants.asthatic_operator_id'))
                        ->where('users.active', '=', 1)
                        ->where('users.account_id', '=', $account_id)
                        ->whereIn('users.id', $doctor_id)
                        ->get();
                    if($pluck_columns) {
                        $query = $query->pluck('name', 'id');
                    }
                    return $query;
                } else {
                    $query = self::where('users.user_type_id', '=', config('constants.asthatic_operator_id'))
                        ->where('users.active', '=', 1)
                        ->where('users.account_id', '=', $account_id)
                        ->get();
                    if($pluck_columns) {
                        $query = $query->pluck('name', 'id');
                    }
                    return $query;
                }
            }

            if ($doctor_id) {
                $query = self::where('users.user_type_id', '=', config('constants.asthatic_operator_id'))
                    ->where('users.active', '=', 1)
                    ->whereIn('users.id', $doctor_id)
                    ->get();
                if($pluck_columns) {
                    $query = $query->pluck('name', 'id');
                }
                return $query;
            } else {
                $query = self::where('users.user_type_id', '=', config('constants.asthatic_operator_id'))
                    ->where('users.active', '=', 1)->get();
                if($pluck_columns) {
                    $query = $query->pluck('name', 'id');
                }
                return $query;
            }
//            return self::get()->pluck('name','id');
        }
    }


    /**
     * Get Location based Doctors
     */
    static public function getLocationDoctors()
    {
        $doctors =  self::join('doctor_has_locations', function ($join) {
            $join->on('users.id', '=', 'doctor_has_locations.user_id')
                ->where('users.user_type_id', '=', config('constants.asthatic_operator_id'))
                ->where('users.active', '=', 1);
        })->get();

        $data = array();

        $locations = array();

        if($doctors) {
            $doctors = $doctors->toArray();
            foreach($doctors as $doctor) {
                if(!in_array($doctor['location_id'], $locations)) {
                    $data[$doctor['location_id']][$doctor['user_id']] = $doctor;
                    $locations[] = $doctor['location_id'];
                } else {
                    $data[$doctor['location_id']][$doctor['user_id']] = $doctor;
                }
            }
        }

        return $data;
    }


}
