<?php

namespace App\Models;

use App\Helpers\GeneralFunctions;
use App\Helpers\NodesTree;
use App\Helpers\AppointmentHelper;
use Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;

class Appointments extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'scheduled_date', 'scheduled_time', 'scheduled_at_count', 'first_scheduled_date', 'first_scheduled_time', 'first_scheduled_count', 'active', 'name', 'account_id', 'appointment_type_id', 'base_appointment_status_id',
        'created_by', 'updated_by', 'converted_by', 'msg_count', 'lead_id', 'patient_id', 'send_message', 'appointment_status_allow_message',
        'appointment_status_id', 'service_id', 'cancellation_reason_id', 'reason',
        'resource_id', 'resource_has_rota_day_id', 'resource_has_rota_day_id_for_machine',
        'doctor_id', 'region_id', 'city_id', 'location_id', 'created_at', 'updated_at', 'appointment_id', 'counter', 'consultancy_type', 'coming_from','deleted_by',
        'arrived_at', 'converted_at', 'meta_purchase_sent', 'referred_by'
    ];

    protected $table = 'appointments';

    public static $_table = 'appointments';

    /**
     * used in event
     *
     * @var string
     */
    public $__table = 'appointments';

    public static $_fillable = ['scheduled_date', 'scheduled_time', 'scheduled_at_count', 'first_scheduled_date', 'first_scheduled_time', 'first_scheduled_count', 'active', 'name', 'account_id', 'appointment_type_id', 'base_appointment_status_id',
        'created_by', 'updated_by', 'converted_by', 'msg_count', 'lead_id', 'patient_id', 'send_message', 'appointment_status_allow_message',
        'appointment_status_id', 'service_id', 'cancellation_reason_id', 'reason',
        'resource_id', 'resource_has_rota_day_id', 'resource_has_rota_day_id_for_machine',
        'doctor_id', 'region_id', 'city_id', 'location_id', 'created_at', 'updated_at', 'appointment_id', 'counter', 'consultancy_type', 'coming_from',
        'arrived_at', 'converted_at', 'meta_purchase_sent', 'referred_by',
    ];

    /**
     * used in events
     *
     * @var array
     */
    public $__fillable = ['scheduled_date', 'scheduled_time', 'scheduled_at_count', 'first_scheduled_date', 'first_scheduled_time', 'first_scheduled_count', 'active', 'name', 'account_id', 'appointment_type_id', 'base_appointment_status_id',
        'created_by', 'updated_by', 'converted_by', 'msg_count', 'lead_id', 'patient_id', 'send_message', 'appointment_status_allow_message',
        'appointment_status_id', 'service_id', 'cancellation_reason_id', 'reason',
        'resource_id', 'resource_has_rota_day_id', 'resource_has_rota_day_id_for_machine',
        'doctor_id', 'region_id', 'city_id', 'location_id', 'created_at', 'updated_at', 'appointment_id', 'counter', 'consultancy_type', 'coming_from',
        'arrived_at', 'converted_at', 'meta_purchase_sent', 'referred_by',
    ];

    protected $attributes = [
        'consultancy_type' => 'in_person',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'first_scheduled_date' => 'date',
        'arrived_at' => 'datetime',
        'converted_at' => 'datetime',
        'send_message' => 'boolean',
        'appointment_status_allow_message' => 'boolean',
        'active' => 'boolean',
        'meta_purchase_sent' => 'boolean',
    ];

    public function scopeWithRelations($query)
    {
        return $query->with([
            'appointment_type',
            'appointment_status',
            'service',
            'location.city',
            'doctor',
            'patient',
            'lead'
        ]);
    }

    public function scopeScheduled($query)
    {
        return $query->whereNotNull('scheduled_date')
                     ->whereNotNull('scheduled_time');
    }

    public function scopeNonScheduled($query)
    {
        return $query->whereNull('scheduled_date')
                     ->whereNull('scheduled_time');
    }

    public function scopeByAccount($query, $account_id)
    {
        return $query->where('account_id', $account_id);
    }

    public function scopeByType($query, $appointment_type_id)
    {
        return $query->where('appointment_type_id', $appointment_type_id);
    }

    public function scopeByStatus($query, $appointment_status_id)
    {
        return $query->where('appointment_status_id', $appointment_status_id);
    }

    public function scopeByLocation($query, $location_id)
    {
        return $query->where('location_id', $location_id);
    }

    public function scopeByDoctor($query, $doctor_id)
    {
        return $query->where('doctor_id', $doctor_id);
    }

    public function scopeByPatient($query, $patient_id)
    {
        return $query->where('patient_id', $patient_id);
    }

    public function scopeExcludeCancelled($query, $account_id)
    {
        $cancelledStatus = AppointmentHelper::getCancelledStatus($account_id);
        if ($cancelledStatus) {
            return $query->where(function($q) use ($cancelledStatus) {
                $q->where('appointment_status_id', '!=', $cancelledStatus->id)
                  ->orWhereNull('appointment_status_id');
            });
        }
        return $query;
    }

    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_date', Carbon::today());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_date', '>=', Carbon::today());
    }

    public function scopeDateRange($query, $start_date, $end_date)
    {
        return $query->whereBetween('scheduled_date', [$start_date, $end_date]);
    }

    public static function updateServiceRecord($id, $appointment_data, $account_id)
    {
        // Set Account ID
        $appointment_data['account_id'] = $account_id;
        $appointment_data['updated_at'] = Carbon::parse(Carbon::now())->toDateTimeString();
        $appointment_data['converted_by'] = Auth::User()->id;

        if (isset($appointment_data['start'])) {
            $appointment_data['scheduled_date'] = Carbon::parse($appointment_data['start'])->format('Y-m-d');
            $appointment_data['scheduled_time'] = Carbon::parse($appointment_data['start'])->format('H:i:s');
            if ($appointment_data['first_scheduled_count'] == 0) {
                $appointment_data['first_scheduled_date'] = Carbon::parse($appointment_data['start'])->format('Y-m-d');
                $appointment_data['first_scheduled_time'] = Carbon::parse($appointment_data['start'])->format('H:i:s');
                $appointment_data['first_scheduled_count'] = 1;
            } else {
                $appointment_data['scheduled_at_count'] = $appointment_data['scheduled_at_count'] + 1;
            }
        } else {
            $appointment_data['scheduled_date'] = null;
            $appointment_data['scheduled_time'] = null;
            $appointment_data['first_scheduled_at'] = null;
        }
        if (isset($appointment_data['resourceId'])) {
            $appointment_data['resource_id'] = $appointment_data['resourceId'];
        }

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
            return null;
        }

        $record->update($appointment_data);

        return $record;

    }

    /**
     * Get the lead comments for lead.
     */
    public function appointment_comments()
    {
        return $this->hasMany('App\Models\AppointmentComments', 'appointment_id')->OrderBy('created_at', 'desc');
    }

    /**
     * Get the Service that owns the Appointment.
     */
    public function service()
    {
        return $this->belongsTo('App\Models\Services')->withTrashed();
    }

    /**
     * Get Appointment Type that owns the Appointment.
     */
    public function appointment_type()
    {
        return $this->belongsTo('App\Models\AppointmentTypes')->withTrashed();
    }

    /**
     * Get the Appointment Status that owns the Appointment.
     */
    public function appointment_status()
    {
        return $this->belongsTo(AppointmentStatuses::class)->withTrashed();
    }

    /*
     * Get the Appointment status according to base appointment status
     * */

    public function appointment_status_base()
    {
        return $this->belongsTo('App\Models\AppointmentStatuses', 'base_appointment_status_id')->withTrashed();
    }

    /**
     * Get the Appointment Status that owns the Appointment.
     */
    public function cancellation_reason()
    {
        return $this->belongsTo('App\Models\CancellationReasons')->withTrashed();
    }

    /**
     * Get the Doctors that owns the Appointment.
     */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id')->withTrashed();
    }

    /**
     * Get the City that owns the Appointment.
     */
    public function city()
    {
        return $this->belongsTo('App\Models\Cities')->withTrashed();
    }

    /**
     * Get the Region that owns the Appointment.
     */
    public function region()
    {
        return $this->belongsTo('App\Models\Regions')->withTrashed();
    }

    /**
     * Get the Doctors that owns the Appointment.
     */
    public function location()
    {
        return $this->belongsTo('App\Models\Locations')->withTrashed();
    }

    /**
     * Get the Lead that owns the Appointment.
     */
    public function lead()
    {
        return $this->belongsTo('App\Models\Leads')->withTrashed();
    }

    /**
     * Get the patient that owns the Appointment.
     */
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id')->withTrashed();
    }

    /**
     * Get the patient that owns the Appointment.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /*
     * Get the user by whom appointment is converted
     */

    public function user_converted_by()
    {
        return $this->belongsTo(User::class, 'converted_by')->withTrashed();
    }

    /*
     * Get the user by whom appointment is updated
      */

    public function user_updated_by()
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    /*
     * Get the appointments for City.
     */
    public function sms_logs()
    {
        return $this->hasMany('App\Models\SMSLogs', 'appointment_id')->withTrashed();
    }

    /*
     * Self join on appointment_id
     * */

    public function appointments()
    {
        return $this->hasMany(Appointments::class, 'appointment_id');
    }

    /**
     * Get the package advances information.
     */
    public function packageadvance()
    {

        return $this->hasMany('App\Models\PackageAdvances', 'appointment_id');
    }

    /*
     * Get the packages information
     * */

    public function packages()
    {
        return $this->hasMany(Packages::class, 'appointment_id');
    }

    /*
     * Get the invoices of the appointments
     * */
    public function hasInvoices()
    {
        return $this->hasMany(Invoices::class, 'appointment_id');
    }
    public function invoice() // clearer
{
    return $this->hasOne(Invoices::class, 'appointment_id');
}

    /**
     * Prepare SMS Contnet for Delivery
     *
     * @param: int $appointment_id
     *
     * @param: int $smsContent
     *
     * @return: string
     */
    public static function prepareSMSContent($appointment_id, $smsContent)
    {
        return AppointmentHelper::prepareSMSContent($appointment_id, $smsContent);
    }

    /**
     * Get Doctor based appointments
     *
     * @param: \Illuminate\Http\Request $request
     *
     * @param: $account_id Current organization id
     *
     * @return: string
     */
    public static function getNonScheduledAppointments(Request $request, $appointment_type_id, $account_id)
    {
        $query = self::byAccount($account_id)
            ->nonScheduled()
            ->excludeCancelled($account_id)
            ->withRelations();

        if ($appointment_type_id) {
            $query->byType($appointment_type_id);
        }

        if ($request->get('city_id')) {
            $query->where('city_id', $request->get('city_id'));
        }

        if ($request->get('location_id')) {
            $query->byLocation($request->get('location_id'));
        }

        if ($request->get('doctor_id')) {
            $query->byDoctor($request->get('doctor_id'));
        }

        return $query->get();
    }

    /**
     * Get Doctor based appointments
     *
     * @param: \Illuminate\Http\Request $request
     *
     * @param: integer $appointment_type_id Appointment ID
     *
     * @param: integer $account_id Current organization id
     *
     * @param: boolean $skip_doctor
     *
     * @return: string
     */
    public static function getScheduledAppointments(Request $request, $appointment_type_id, $account_id, $skip_doctor = false)
    {
        $query = self::scheduled()
            ->excludeCancelled($account_id)
            ->withRelations();
 
        if ($appointment_type_id) {
            $query->byType($appointment_type_id);
        }

        if ($request->start) {
            $query->where('scheduled_date', '>=', Carbon::parse($request->start)->format('Y-m-d'));
        }

        if ($request->end) {
            $query->where('scheduled_date', '<=', Carbon::parse($request->end)->format('Y-m-d'));
        }

        if ($request->location_id) {
            $query->byLocation($request->location_id);
        }

        if ($request->doctor_id && !$skip_doctor) {
            $query->where('doctor_id', $request->doctor_id);
        }
        
        if ($request->machine_id) {
            $query->where('resource_id', $request->machine_id);
        }

        return $query->get();
    }

    /**
     * Update Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $appointment_data, $account_id)
    {
        $appointment_data['account_id'] = $account_id;
        $appointment_data['updated_at'] = Carbon::now();
        
        if (isset($appointment_data['reschedule']) && $appointment_data['reschedule'] == 1) {
            $appointment_data['converted_by'] = Auth::id();
        } else {
            $appointment_data['updated_by'] = Auth::id();
        }

        if (isset($appointment_data['start'])) {
            $scheduleData = AppointmentHelper::formatScheduleData(
                $appointment_data['start'],
                $appointment_data['first_scheduled_count'] ?? 0,
                $appointment_data['scheduled_at_count'] ?? 0
            );
            $appointment_data = array_merge($appointment_data, $scheduleData);
        }

        $record = self::byAccount($account_id)->find($id);

        if (!$record) {
            return null;
        }

        $record->update($appointment_data);
        AppointmentHelper::clearAppointmentCache($account_id);

        return $record;
    }

    /**
     * Get Node Services
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function getNodeServices($serviceId, $account_id, $drop_down = false, $remove_spaces = false)
    {
        return AppointmentHelper::getNodeServices($serviceId, $account_id, $drop_down, $remove_spaces);
    }

    public static function boot()
    {

        parent::boot();

        static::created(function ($item) {

            Event::dispatch('appointment.created', $item);

        });

        static::updating(function ($item) {

            Event::dispatch('appointment.updating', $item);

        });

        static::deleting(function ($item) {

            Event::dispatch('appointment.deleting', $item);

        });

    }

    /**
     * Delete Record
     *
     * @param id
     * @return (mixed)
     */
    public static function DeleteRecord($id, $account_id)
    {
        $appointment = self::where(['id' => $id, 'account_id' => $account_id])->first();

        if (! $appointment) {
            return [
                'status' => false,
                'message' => 'Appointment not found.',
            ];
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (self::isChildExists($id, $account_id)) {
            return [
                'status' => false,
                'message' => "Consultation or Treatment can't be deleted when invoice generated.",
            ];
        }
        
        // Log deletion activity before deleting
        $patient = Patients::find($appointment->patient_id);
        $location = Locations::with('city')->find($appointment->location_id);
        $service = Services::find($appointment->service_id);
        \App\Helpers\ActivityLogger::logAppointmentDeleted($appointment, $patient, $location, $service);
        
        AppointmentsDailyStats::where('appointment_id',$id)->delete();
        $appointment->whereId($id)->update([
            'deleted_by' => Auth::id(),
            'arrived_at' => null,
            'converted_at' => null
        ]);
        $appointment->delete();
        Activity::where('appointment_id',$id)->update(['deleted_by'=>Auth::id(),'action'=>'deleted','deleted_date'=>Carbon::now()->format('Y-m-d'),'updated_at'=>Carbon::now()]);
        //log request for delete for audit trail
        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return [
            'status' => true,
            'message' => 'Record has been deleted successfully.',
        ];
    }
    public function feedback()
    {
        return $this->hasOne(Feedback::class, 'appointment_id');
    }

    public function resource()
    {
        return $this->belongsTo(Resources::class, 'resource_id')->withTrashed();
    }
    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    protected static function isChildExists($id, $account_id)
    {
        return AppointmentHelper::isChildExists($id, $account_id);
    }

    /**
     * change scheduled_date format
     *
     * @param $time
     * @return string
     */
    /*public function getScheduledTimeAttribute($time, $format = 'h:i A') { //h:ia
       return Carbon::parse($time)->format($format);
    }*/
}
