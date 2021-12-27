<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;

class AuditTrailChanges extends Model
{
    protected $fillable = ['audit_trail_id', 'field_name', 'field_before', 'field_after', 'created_at', 'updated_at'];

    protected $table = 'audit_trail_changes';

    public function doctors_before()
    {
        return $this->belongsTo('App\Models\Doctors', 'field_before');
    }
    public function doctors_after()
    {
        return $this->belongsTo('App\Models\Doctors', 'field_after');
    }
    public function patients_before()
    {
        return $this->belongsTo('App\Models\Patients', 'field_before');
    }
    public function patients_after()
    {
        return $this->belongsTo('App\Models\Patients', 'field_after');
    }
    public function accounts_before()
    {
        return $this->belongsTo('App\Models\Accounts', 'field_before');
    }
    public function accounts_after()
    {
        return $this->belongsTo('App\Models\Accounts', 'field_after');
    }
    public function invoicestatus_before()
    {
        return $this->belongsTo('App\Models\InvoiceStatuses', 'field_before');
    }
    public function invoicestatus_after()
    {
        return $this->belongsTo('App\Models\InvoiceStatuses', 'field_after');
    }
    public function locations_before()
    {
        return $this->belongsTo('App\Models\Locations', 'field_before');
    }
    public function locations_after()
    {
        return $this->belongsTo('App\Models\Locations', 'field_after');
    }
    public function users_before()
    {
        return $this->belongsTo('App\User', 'field_before');
    }
    public function users_after()
    {
        return $this->belongsTo('App\User', 'field_after');
    }
    public function services_before()
    {
        return $this->belongsTo('App\Models\Services', 'field_before');
    }
    public function services_after()
    {
        return $this->belongsTo('App\Models\Services', 'field_after');
    }
    public function discounts_before()
    {
        return $this->belongsTo('App\Models\Discounts', 'field_before');
    }
    public function discounts_after()
    {
        return $this->belongsTo('App\Models\Discounts', 'field_after');
    }
    public function payment_mode_before()
    {
        return $this->belongsTo('App\Models\PaymentModes', 'field_before');
    }
    public function payment_mode_after()
    {
        return $this->belongsTo('App\Models\PaymentModes', 'field_after');
    }
    public function appointment_type_before()
    {
        return $this->belongsTo('App\Models\AppointmentTypes', 'field_before');
    }
    public function appointment_type_after()
    {
        return $this->belongsTo('App\Models\AppointmentTypes', 'field_after');
    }
    /*
     * Get base appointment status name
     *
     * */

    public function appointmentStatus()
    {
        return $this->belongsTo(AppointmentStatuses::class,'field_after','id');
    }

    /*
     * Appointment Created By
     *
     * */

    public function appointmentCreatedBy()
    {
        return $this->belongsTo(User::class,'field_after','id');
    }

    /*
     * Get the service name
     *
     * */

    public function service()
    {
        return $this->belongsTo(Services::class, 'field_after','id');
    }

    /*
     * Get the Resource
     *
     * */

    public function doctor()
    {
        return $this->belongsTo(User::class, 'field_after','id');
    }

    /*
     * Get Region
     *
     * */

    public function region()
    {
        return $this->belongsTo(Regions::class,'field_after', 'id');
    }

    /*
     * Get the City
     *
     * */
    public function city()
    {
        return $this->belongsTo(Cities::class,'field_after','id');
    }

    /*
     * Get the location
     *
     * */

    public function location()
    {
        return $this->belongsTo(Locations::class, 'field_after','id');
    }

    /*
     * Get the Appointment Type
     *
     * */

    public function appointmentType()
    {
        return $this->belongsTo(AppointmentTypes::class, 'field_after','id');
    }

    /*
     * Get the user according to patient_id
     *
     * */

    public function user()
    {
        return $this->belongsTo( User::class, 'field_after', 'id');
    }

    /*
     * Get the Resource Name
     *
     * */

    public function resource()
    {
        return $this->belongsTo( Resources::class, 'field_after', 'id');
    }
}
