<?php

namespace App\Models;

use App\Helpers\GeneralFunctions;
use App\Helpers\NodesTree;
use Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class Appointments extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'scheduled_date', 'scheduled_time', 'scheduled_at_count', 'first_scheduled_date', 'first_scheduled_time', 'first_scheduled_count', 'active', 'name', 'account_id', 'appointment_type_id', 'base_appointment_status_id',
        'created_by', 'updated_by', 'converted_by', 'msg_count', 'lead_id', 'patient_id', 'send_message', 'appointment_status_allow_message',
        'appointment_status_id', 'service_id', 'cancellation_reason_id', 'reason',
        'resource_id', 'resource_has_rota_day_id', 'resource_has_rota_day_id_for_machine',
        'doctor_id', 'region_id', 'city_id', 'location_id', 'created_at', 'updated_at', 'appointment_id', 'counter', 'consultancy_type', 'coming_from','deleted_by'
    ];

    protected $table = 'appointments';

    protected static $_table = 'appointments';

    /**
     * used in event
     *
     * @var string
     */
    public $__table = 'appointments';

    protected static $_fillable = ['scheduled_date', 'scheduled_time', 'scheduled_at_count', 'first_scheduled_date', 'first_scheduled_time', 'first_scheduled_count', 'active', 'name', 'account_id', 'appointment_type_id', 'base_appointment_status_id',
        'created_by', 'updated_by', 'converted_by', 'msg_count', 'lead_id', 'patient_id', 'send_message', 'appointment_status_allow_message',
        'appointment_status_id', 'service_id', 'cancellation_reason_id', 'reason',
        'resource_id', 'resource_has_rota_day_id', 'resource_has_rota_day_id_for_machine',
        'doctor_id', 'region_id', 'city_id', 'location_id', 'created_at', 'updated_at', 'appointment_id', 'counter', 'consultancy_type', 'coming_from',
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
    ];

    protected $attributes = [
        'consultancy_type' => 'in_person',
    ];

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
        if (! $appointment_id) {
            return $smsContent;
        } else {
            $appointment = self::find($appointment_id);
            $patient = Patients::find($appointment->patient_id);

            // Load Globar Setting for Head Office
            $Setting = Settings::getBySlug('sys-headoffice', $appointment->account_id);
            $smsContent = str_replace('##head_office_phone##', $Setting->data, $smsContent);

            if ($appointment) {
                // Replace Patient Information
                $smsContent = str_replace('##patient_name##', ($appointment->name) ? $appointment->name : $patient->name, $smsContent);
                $smsContent = str_replace('##patient_phone##', $patient->phone, $smsContent);

                // Replace Schedule Information
                $smsContent = str_replace('##appointment_date##', Carbon::parse($appointment->scheduled_date)->format('l, F d,Y'), $smsContent);
                $smsContent = str_replace('##appointment_time##', Carbon::parse($appointment->scheduled_time)->format('h:i A'), $smsContent);

                // Replace Service Information
                $service = Services::find($appointment->service_id);
                if ($service) {
                    $smsContent = str_replace('##appointment_service##', $service->name, $smsContent);
                }

                // Load and Replace Centre Information
                $Location = Locations::find($appointment->location_id);
                if ($Location) {
                    $smsContent = str_replace('##fdo_name##', $Location->fdo_name, $smsContent);
                    $smsContent = str_replace('##fdo_phone##', GeneralFunctions::prepareNumber4CallSMS($Location->fdo_phone), $smsContent);
                    $smsContent = str_replace('##centre_name##', $Location->name, $smsContent);
                    $smsContent = str_replace('##centre_address##', $Location->address, $smsContent);
                    $smsContent = str_replace('##centre_google_map##', $Location->google_map, $smsContent);
                }

                // Load and Replace Doctor Information
                $Doctor = Doctors::find($appointment->doctor_id);
                if ($Doctor) {
                    $smsContent = str_replace('##doctor_name##', $Doctor->name, $smsContent);
                    $smsContent = str_replace('##doctor_profile_link##', $Doctor->profile_url, $smsContent);
                }

            }

            return $smsContent;
        }
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
        $where = [];
        $where[] = ['account_id', '=', $account_id];

        /*
         * Get default cancelled appointment status
         */
        $cancelled_appointment_status = AppointmentStatuses::getCancelledStatusOnly($account_id);
        if ($cancelled_appointment_status) {
            $where[] = ['base_appointment_status_id', '!=', $cancelled_appointment_status->id];
        }

        if ($appointment_type_id) {
            $where[] = ['appointment_type_id', '=', $appointment_type_id];
        }

        if ($request->get('city_id')) {
            $where[] = ['city_id', '=', $request->get('city_id')];
        }

        if ($request->get('location_id')) {
            $where[] = ['location_id', '=', $request->get('location_id')];
        }

        if ($request->get('doctor_id')) {
            $where[] = ['doctor_id', '=', $request->get('doctor_id')];
        }

        return self::where($where)
            ->whereNull('scheduled_date')
            ->whereNull('scheduled_time')
            ->get();
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

        DB::enableQueryLog();
        $where = [];
        $where[] = ['account_id', '=', $account_id];
        $cancelled_appointment_status = AppointmentStatuses::getCancelledStatusOnly($account_id);

        if ($cancelled_appointment_status) {
            $where[] = ['base_appointment_status_id', '!=', $cancelled_appointment_status->id];
        }

        if ($appointment_type_id) {
            $where[] = ['appointment_type_id', '=', $appointment_type_id];
        }

        return self::where($where)
            ->when($request->start, function ($query) use ($request) {
                return $query->where('scheduled_date', '>=', Carbon::parse($request->get('start'))->format('Y-m-d'));
            })
            ->when($request->location_id, function ($query) use ($request) {
                return $query->where('location_id', '=', $request->get('location_id'));
            })
            ->when($request->machine_id || $request->doctor_id, function ($query) use ($request) {
                return $query->where(function ($q) use ($request) {
                    $q->where('resource_id', '=', $request->machine_id)
                        ->orWhere('doctor_id', '=', $request->doctor_id);
                });
            })
            ->whereNotNull('scheduled_date')
            ->whereNotNull('scheduled_time')
            ->get();

        return self::where($where)
            ->whereNotNull('scheduled_date')
            ->whereNotNull('scheduled_time')
            ->get();
    }

    /**
     * Update Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $appointment_data, $account_id)
    {
        // Set Account ID
        $appointment_data['account_id'] = $account_id;
        $appointment_data['updated_at'] = Carbon::parse(Carbon::now())->toDateTimeString();
        if ($appointment_data['reschedule'] == 1) {
            $appointment_data['converted_by'] = Auth::User()->id;
        } else {
            $appointment_data['updated_by'] = Auth::User()->id;
        }

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
     * Get Node Services
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function getNodeServices($serviceId, $account_id, $drop_down = false, $remove_spaces = false)
    {
        /*
         * That function use Appointment Report (Appointment by status) and Treatment Management
         */

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(($serviceId) ? $serviceId : 0, $account_id, true, true);
        $parentGroups->toList($parentGroups, -1);
        $services = $parentGroups->nodeList;

        $nodeList = [];

        if (count($services)) {
            foreach ($services as $key => $service) {
                if ($key < 0) {
                    continue;
                }

                if ($drop_down) {
                    if ($remove_spaces) {
                        $nodeList[$key] = str_replace('&nbsp;', '', trim($service['name']));
                    } else {
                        $nodeList[$key] = trim($service['name']);
                    }
                } else {
                    if ($remove_spaces) {
                        $service['name'] = str_replace('&nbsp;', '', trim($service['name']));
                    }
                    $nodeList[$key] = $service;
                }
            }
        }

        return $nodeList;
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
                'message' => 'Child records exist, unable to delete appointment',
            ];
        }
        $appointment->update('deleted_by',Auth::id());
        $appointment->delete();

        //log request for delete for audit trail
        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return [
            'status' => true,
            'message' => 'Record has been deleted successfully.',
        ];
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    protected static function isChildExists($id, $account_id)
    {
        if (
            PackageAdvances::where(['appointment_id' => $id, 'account_id' => $account_id])->count() ||
            Invoices::where(['appointment_id' => $id, 'account_id' => $account_id])->count() ||
            Measurement::where(['appointment_id' => $id])->count() ||
            Appointmentimage::where(['appointment_id' => $id])->count()
        ) {
            return true;
        }

        return false;
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
