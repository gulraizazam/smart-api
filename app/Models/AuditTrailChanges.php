<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditTrailChanges extends Model
{
    protected $fillable = ['audit_trail_id', 'field_name', 'field_before', 'field_after', 'created_at', 'updated_at'];

    protected $table = 'audit_trail_changes';

    public function doctors_before(): BelongsTo
    {
        return $this->belongsTo(Doctors::class, 'field_before');
    }

    public function doctors_after(): BelongsTo
    {
        return $this->belongsTo(Doctors::class, 'field_after');
    }

    public function patients_before(): BelongsTo
    {
        return $this->belongsTo(Patients::class, 'field_before');
    }

    public function patients_after(): BelongsTo
    {
        return $this->belongsTo(Patients::class, 'field_after');
    }

    public function accounts_before(): BelongsTo
    {
        return $this->belongsTo(Accounts::class, 'field_before');
    }

    public function accounts_after(): BelongsTo
    {
        return $this->belongsTo(Accounts::class, 'field_after');
    }

    public function invoicestatus_before(): BelongsTo
    {
        return $this->belongsTo(InvoiceStatuses::class, 'field_before');
    }

    public function invoicestatus_after(): BelongsTo
    {
        return $this->belongsTo(InvoiceStatuses::class, 'field_after');
    }

    public function locations_before(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'field_before');
    }

    public function locations_after(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'field_after');
    }

    public function users_before(): BelongsTo
    {
        return $this->belongsTo(User::class, 'field_before');
    }

    public function users_after(): BelongsTo
    {
        return $this->belongsTo(User::class, 'field_after');
    }

    public function services_before(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'field_before');
    }

    public function services_after(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'field_after');
    }

    public function discounts_before(): BelongsTo
    {
        return $this->belongsTo(Discounts::class, 'field_before');
    }

    public function discounts_after(): BelongsTo
    {
        return $this->belongsTo(Discounts::class, 'field_after');
    }

    public function payment_mode_before(): BelongsTo
    {
        return $this->belongsTo(PaymentModes::class, 'field_before');
    }

    public function payment_mode_after(): BelongsTo
    {
        return $this->belongsTo(PaymentModes::class, 'field_after');
    }

    public function appointment_type_before(): BelongsTo
    {
        return $this->belongsTo(AppointmentTypes::class, 'field_before');
    }

    public function appointment_type_after(): BelongsTo
    {
        return $this->belongsTo(AppointmentTypes::class, 'field_after');
    }
    /*
     * Get base appointment status name
     *
     * */

    public function appointmentStatus(): BelongsTo
    {
        return $this->belongsTo(AppointmentStatuses::class, 'field_after', 'id');
    }

    /*
     * Appointment Created By
     *
     * */

    public function appointmentCreatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'field_after', 'id');
    }

    /*
     * Get the service name
     *
     * */

    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'field_after', 'id');
    }

    /*
     * Get the Resource
     *
     * */

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'field_after', 'id');
    }

    /*
     * Get Region
     *
     * */

    public function region(): BelongsTo
    {
        return $this->belongsTo(Regions::class, 'field_after', 'id');
    }

    /*
     * Get the City
     *
     * */
    public function city(): BelongsTo
    {
        return $this->belongsTo(Cities::class, 'field_after', 'id');
    }

    /*
     * Get the location
     *
     * */

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'field_after', 'id');
    }

    /*
     * Get the Appointment Type
     *
     * */

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentTypes::class, 'field_after', 'id');
    }

    /*
     * Get the user according to patient_id
     *
     * */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'field_after', 'id');
    }

    /*
     * Get the Resource Name
     *
     * */

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resources::class, 'field_after', 'id');
    }
}
