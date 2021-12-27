<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Config;
use App\Models\AuditTrails;
use Auth;
use App\Helpers\GeneralFunctions;
use App\Helpers\ACL;
use App\Models\Telecomprovidernumber;
use App\Helpers\Widgets\AgeCalculatorWidget;
use Carbon\Carbon;

class PabaoRecords extends BaseModal
{
    use SoftDeletes;

    protected $fillable = [
        'client', 'invoice_no', 'issue_date', 'employee',
        'total_amount', 'paid_amount', 'outstanding_amount', 'total_spend',
        'total_visits', 'last_visit_days_ago', 'new_client', 'patient_id',
        'first_name', 'last_name', 'last_modified', 'active',
        'country', 'salutation', 'address_1', 'address_2',
        'post_code', 'mobile', 'phone', 'town',
        'full_address', 'gender', 'email', 'date_of_birth',
        'privacy_policy', 'marketing_optin_email', 'marketing_optin_sms', 'marketing_optin_newsletter',
        'marketing_source', 'age', 'insurer_name', 'contract_client',
        'appointments_attended_total', 'appointments_attended', 'online_bookings', 'appointments_dna',
        'appointments_rescheduled', 'appointments_date_first', 'appointments_date_last', 'outstanding_balance',
        'amount_balance', 'first_booking_with', 'first_booking_service', 'membership_number',
        'future_booking', 'future_booking_date', 'next_appointment', 'client_created_by',
        'episode_id', 'client_sys_id', 'location', 'location_id',
        'created_by', 'updated_by', 'converted_by', 'created_at', 'updated_at', 'account_id'
    ];

    protected static $_fillable = [
        'patient_id', 'region_id', 'city_id', 'pabao_record_status_id',
        'pabao_record_source_id', 'msg_count', 'service_id'
    ];

    protected $table = 'pabao_records';

    protected static $_table = 'pabao_records';

    /**
     * Get the Lead that owns the City.
     */
    public function city()
    {
        return $this->belongsTo('App\Models\Cities')->withTrashed();
    }

    /**
     * Get the Lead that owns the City.
     */
    public function region()
    {
        return $this->belongsTo('App\Models\Regions')->withTrashed();
    }

    /**
     * Get the User that owns the Lead.
     */
    public function user()
    {
        return $this->belongsTo('App\User', 'created_by')->withTrashed();
    }

    /**
     * Get the pabao_record comments for pabao_record.
     */
    public function pabao_record_payments()
    {
        return $this->hasMany('App\Models\PabaoRecordPayments', 'pabao_record_id')->OrderBy('created_at', 'desc');
    }

}
