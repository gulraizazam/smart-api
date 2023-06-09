<?php

namespace App\Models;

use App\Helpers\ACL;
use App\Helpers\GeneralFunctions;
use App\Helpers\Widgets\LocationsWidget;
use Auth;
use Carbon\Carbon;
use Config;
use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Leads extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['region_id', 'city_id', 'lead_status_id', 'lead_source_id', 'msg_count', 'active', 'created_by', 'updated_by', 'converted_by', 'town_id', 'created_at', 'updated_at', 'account_id', 'location_id', 'name', 'email', 'phone', 'gender', 'referred_by'];

    protected static $_fillable = ['region_id', 'city_id', 'lead_status_id', 'lead_source_id', 'msg_count', 'service_id','town_id'];

    protected $table = 'leads';

    protected static $_table = 'leads';

    /**
     * Get the Treatment that owns the Lead.
     */
    public function lead_service()
    {
        return $this->hasMany(LeadsServices::class, 'lead_id')->with('service:id,name,parent_id', 'childservice:id,name,parent_id');
    }

    public function active_lead_service()
    {
        return $this->hasMany(LeadsServices::class, 'lead_id')->where(['status' => 1]);
    }

    public function patient()
    {
        return $this->belongsTo(Patients::class);
    }

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
     * Get the Lead Status that owns the Lead.
     */
    public function lead_status()
    {
        return $this->belongsTo('App\Models\LeadStatuses')->withTrashed();
    }

    /**
     * Get the Leads Source that owns the Lead.
     */
    public function lead_source()
    {
        return $this->belongsTo('App\Models\LeadSources')->withTrashed();
    }

    /**
     * Get the User that owns the Lead.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * Get the lead comments for lead.
     */
    public function lead_comments()
    {
        return $this->hasMany('App\Models\LeadComments', 'lead_id')->OrderBy('created_at', 'desc');
    }

    /**
     * Get the lead appointments for lead.
     */
    public function appointments()
    {
        return $this->hasMany('App\Models\Appointments', 'lead_id');
    }

    /**
     * Get the Town Name owns the Appointment.
     */
    public function towns()
    {
        return $this->belongsTo('App\Models\Locations', 'location_id', 'id')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder|Model|object|null
     */
    static public function getData($id) {
        return self::with('lead_service')->where([
            ['id','=',$id],
            ['account_id','=',Auth::user()->account_id]
        ])->first();
    }

    static public function getLeadPhoneAjax($phone, $account_id)
		{
			if (is_numeric($phone)) {
				return self::where([
					['active', '=', '1'],
					['account_id', '=', $account_id],
					['phone', 'LIKE', "%{$phone}%"]
				])->select('name', 'id', 'phone')->get();
			} else {
				return self::where([
					['active', '=', '1'],
					['account_id', '=', $account_id],
					['phone', 'LIKE', "%{$phone}%"]
				])->select('name', 'id', 'phone')->get();
			}
		}

        /*
		 * Ajax base result of patient according to id or name
		 * */
		static public function getLeadidAjax($name, $account_id)
		{
            $leads = collect();
            if (is_numeric($name)) {
                $leads =  self::where([
                    'active' => '1',
                    'account_id' => $account_id,
                    'id' => $name
                ])->select('name', 'id', 'phone')->get();
            }
            if ($leads->count() > 0) {
                return $leads;
            }
		    $name = GeneralFunctions::patientSearch($name);
            $phone_numeric = GeneralFunctions::clearnString($name);
			if (is_numeric($phone_numeric)) {
                $phone = GeneralFunctions::cleanNumber($name);
				return self::where([
					['active', '=', '1'],
					['account_id', '=', $account_id],
					['phone', 'LIKE', "%{$phone}%"]
				])->select('name', 'id', 'phone')->orderBy('id', 'desc')->get()->unique('phone');
			} else {
				return self::where([
					['active', '=', '1'],
					['account_id', '=', $account_id],
					['name', 'LIKE', "%{$name}%"]
				])->select('name', 'id', 'phone')->orderBy('id', 'desc')->get()->unique('phone');
			}
		}

    /**
     * Prepare SMS Contnet for Delivery
     *
     * @param: int $lead_id
     *
     * @param: int $smsContent
     *
     * @return: string
     */
    public static function prepareSMSContent($lead_id, $smsContent)
    {
        if (! $lead_id) {
            return $smsContent;
        } else {
            // Load Globar Setting for Head Office
            $Setting = Settings::find(5);
            $smsContent = str_replace('##head_office_phone##', $Setting->data, $smsContent);
            $lead = self::find($lead_id);

            if ($lead) {
                $Patient = Patients::find($lead->patient_id);
                // Replace Patient Information
                $smsContent = str_replace('##full_name##', $Patient->full_name, $smsContent);
                $smsContent = str_replace('##email##', $Patient->email, $smsContent);
                $smsContent = str_replace('##phone##', $Patient->phone, $smsContent);
                $smsContent = str_replace('##gender##', Config::get('constants.gender_array')[$Patient->gender], $smsContent);

                // Load and Replace City Information
                $Citie = Cities::find($lead->city_id);
                if ($Citie) {
                    $smsContent = str_replace('##city_name##', $Citie->name, $smsContent);
                }

                // Load and Replace Lead Source Information
                $LeadSource = LeadSources::find($lead->lead_source_id);
                if ($LeadSource) {
                    $smsContent = str_replace('##lead_source_name##', $LeadSource->name, $smsContent);
                }

                // Load and Replace Lead Status Information
                $LeadStatus = LeadStatuses::find($lead->lead_source_id);
                if ($LeadStatus) {
                    $smsContent = str_replace('##lead_status_name##', $LeadStatus->name, $smsContent);
                }

            }

            return $smsContent;
        }
    }

    /**
     * Create Record
     *
     * @param data,parent_data
     * @return (mixed)
     */
    static public function createRecord($data, $status = null)
    {

        if ($status == 'Appointment') {
            $data['service_id'] = $data['base_service_id'];
            $record = Leads::updateOrCreate(array(
                'phone' => $data['phone'],
                'account_id' => Auth::User()->account_id,
                'created_at' => Carbon::now()->timestamp,
            ), $data);
            $final_data = $record;
            $data['lead_id'] = $final_data->id;
            $service = LeadsServices::create($data);
        } else {
            if (isset($data['city_id']) && $data['city_id']) {
                // Set Region ID
                $data['region_id'] = Cities::findOrFail($data['city_id'])->region_id;
            }
            $checkLeadExistance = Leads::where(array(
                'phone' => $data['phone'],
                'account_id' => Auth::User()->account_id
            ))->first();
            if(!$checkLeadExistance){
                $record = Leads::create($data);
            } else {
                $checkLeadExistance->lead_status_id = 1;
                $checkLeadExistance->created_at = Carbon::now()->timestamp;
                $checkLeadExistance->update();
                $record = $checkLeadExistance;
                $data['lead_id'] = $record->id;
            }
            $final_data = $data;
        }
        AuditTrails::addEventLogger(self::$_table, 'create', $final_data, self::$_fillable, $record);
        return $record;
    }

    /**
     * Create Record
     *
     * @param data,parent_data
     * @return (mixed)
     */
    static public function updateRecord($id, $data, $status = false)
    {
        if ($status == 'Appointment') {
            $old_data = (Leads::find($id))->toArray();
        } else {
            $old_data = '0';
        }
        $record = self::where(['id' => $id])->first();
        if (! $record) {
            return null;
        }
        if (isset($data['city_id']) && $data['city_id']) {
            // Set Region ID
            $data['region_id'] = Cities::findOrFail($data['city_id'])->region_id;
        }
        $data['created_at'] = Carbon::now()->timestamp;
        $record->update($data);

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $record);
        return $record;
    }

    /*
     * calculate data for lead report
     *
     * @param $request
     *
     * @return mixed
     * */
    public static function getLeadReport($data)
    {
        $where = array();
        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['cnic']) && $data['cnic']) {
            $where[] = [
                'users.cnic',
                '=',
                $data['cnic'],
            ];
        }
        if (isset($data['dob']) && $data['dob']) {
            $where[] = [
                'users.dob',
                '=',
                $data['dob'],
            ];
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'users.id',
                '=',
                $data['patient_id'],
            ];
        }
        if (isset($data['email']) && $data['email']) {
            $where[] = [
                'users.email',
                'like',
                '%'.$data['email'].'%',
            ];
        }
        if (isset($data['gender_id']) && $data['gender_id']) {
            $where[] = [
                'users.gender',
                '=',
                $data['gender_id'],
            ];
        }
        if (isset($data['region_id']) && $data['region_id']) {
            $where[] = [
                'leads.region_id',
                '=',
                $data['region_id'],
            ];
        }
        if (isset($data['city_id']) && $data['city_id']) {
            $where[] = [
                'leads.city_id',
                '=',
                $data['city_id'],
            ];
        }
        if (isset($data['lead_status_id']) && $data['lead_status_id']) {
            $where[] = [
                'leads.lead_status_id',
                '=',
                $data['lead_status_id'],
            ];
        }
        if (isset($data['service_id']) && $data['service_id']) {
            $where[] = [
                'leads.service_id',
                '=',
                $data['service_id'],
            ];
        }
        if (isset($data['phone']) && $data['phone']) {
            $where[] = [
                'users.phone',
                'like',
                '%'.GeneralFunctions::cleanNumber($data['phone']).'%',
            ];
        }
        if (isset($data['user_id']) && $data['user_id']) {
            $where[] = [
                'leads.created_by',
                '=',
                $data['user_id'],
            ];
        }
        if (isset($data['town_id']) && $data['town_id']) {
            $where[] = [
                'leads.town_id',
                '=',
                $data['town_id'],
            ];
        }
        if (isset($data['age_group_range']) && $data['age_group_range']) {
            $age_range = explode(':', $data['age_group_range']);
            $from = Carbon::now()->subYears((int) $age_range[1])->toDateString();
            $to = Carbon::now()->subYears((int) $age_range[0])->toDateString();
        }
        $resultQuery = self::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', '=', Config::get('constants.patient_id'))
            ->where(function ($query) {
                $query->whereIn('leads.city_id', ACL::getUserCities());
                $query->orWhereNull('leads.city_id');
            })
            ->whereDate('leads.created_at', '>=', $start_date)
            ->whereDate('leads.created_at', '<=', $end_date);

        if (count($where)) {
            $resultQuery->where($where);
        }
        if (isset($data['age_group_range']) && $data['age_group_range']) {
            $resultQuery->whereBetween('users.dob', [$from, $to]);
        }

        if (isset($data['telecomprovider_id']) && $data['telecomprovider_id']) {
            $telecomprovider = Telecomprovidernumber::whereIn('id', $data['telecomprovider_id'])->get();

            $newPrefix = [];
            foreach ($telecomprovider as $provider) {
                $newPrefix[] = ltrim($provider['pre_fix'], '0');
            }
            $y = 0;
            foreach ($newPrefix as $prefix) {
                $y++;
                if ($y == 1) {
                    $resultQuery->where('users.phone', 'like', $prefix.'%');
                } else {
                    $resultQuery->orWhere('users.phone', 'like', $prefix.'%');
                }
            }
        }

        return $resultQuery->select('*', 'leads.created_by as lead_created_by', 'leads.id as lead_id', 'leads.created_at as lead_created_at', 'users.id as PatientId')->get();
    }

    /*
     * Marketing Report
     * @param $request
     * @return mixed
     */
    public static function getMarketingReport($data)
    {

        $where = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['cnic']) && $data['cnic']) {
            $where[] = [
                'users.cnic',
                '=',
                $data['cnic'],
            ];
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'users.id',
                '=',
                $data['patient_id'],
            ];
        }
        if (isset($data['email']) && $data['email']) {
            $where[] = [
                'users.email',
                'like',
                '%'.$data['email'].'%',
            ];
        }
        if (isset($data['gender_id']) && $data['gender_id']) {
            $where[] = [
                'users.gender',
                '=',
                $data['gender_id'],
            ];
        }
        if (isset($data['region_id']) && $data['region_id']) {
            $where[] = [
                'leads.region_id',
                '=',
                $data['region_id'],
            ];
        }
        if (isset($data['city_id']) && $data['city_id']) {
            $where[] = [
                'leads.city_id',
                '=',
                $data['city_id'],
            ];
        }
        if (isset($data['lead_status_id']) && $data['lead_status_id']) {
            $where[] = [
                'leads.lead_status_id',
                '=',
                $data['lead_status_id'],
            ];
        }
        if (isset($data['phone']) && $data['phone']) {
            $where[] = [
                'users.phone',
                'like',
                '%'.GeneralFunctions::cleanNumber($data['phone']).'%',
            ];
        }
        if (isset($data['user_id']) && $data['user_id']) {
            $where[] = [
                'leads.created_by',
                '=',
                $data['user_id'],
            ];
        }
        if (isset($data['referred_id']) && $data['referred_id']) {
            $where[] = [
                'users.referred_by',
                '=',
                $data['referred_id'],
            ];
        }

        // Process Lead Status
        $DefaultJunkLeadStatus = LeadStatuses::where([
            'account_id' => Auth::User()->account_id,
            'is_junk' => 1,
        ])->first();

        if ($DefaultJunkLeadStatus) {
            $default_junk_lead_status_id = $DefaultJunkLeadStatus->id;
        } else {
            $default_junk_lead_status_id = Config::get('constants.lead_status_junk');
        }

        $resultQuery = self::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', '=', Config::get('constants.patient_id'))
            ->where(function ($query) {
                $query->whereIn('leads.city_id', ACL::getUserCities());
                $query->orWhereNull('leads.city_id');
            })
            ->whereDate('users.created_at', '>=', $start_date)
            ->whereDate('users.created_at', '<=', $end_date)
            ->whereNotIn('leads.lead_status_id', [$default_junk_lead_status_id]);

        if (count($where)) {
            $resultQuery->where($where);
        }

        return $resultQuery->select('*', 'leads.created_by as lead_created_by', 'leads.id as lead_id', 'leads.created_at as lead_created_at', 'users.id as PatientId')->get();
    }

    /*
    * calculate data for lead report
    *
    * @param $request
    *
    * @return mixed
    * */
    public static function getLeadSummaryReport($data)
    {

        $where = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['region_id']) && $data['region_id']) {
            $where[] = [
                'leads.region_id',
                '=',
                $data['region_id'],
            ];
        }
        if (isset($data['city_id']) && $data['city_id']) {
            $where[] = [
                'leads.city_id',
                '=',
                $data['city_id'],
            ];
        }
        $resultQuery = self::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', '=', Config::get('constants.patient_id'))
            ->where(function ($query) {
                $query->whereIn('leads.city_id', ACL::getUserCities());
                $query->orWhereNull('leads.city_id');
            })
            ->whereDate('leads.created_at', '>=', $start_date)
            ->whereDate('leads.created_at', '<=', $end_date);

        if (count($where)) {
            $resultQuery->where($where);
        }

        return $resultQuery->select('*', 'leads.created_by as lead_created_by', 'leads.id as lead_id', 'leads.created_at as lead_created_at', 'users.id as PatientId')->get();
    }

    /**
     * Conversation Rate a notion wide Centers
     *
     * @param (mixed) $request
     * @return (mixed)
     */
    public static function conversionrateatnationwideCenters($data, $account_id)
    {
        /*In future We discuss it*/
    }

    /*
     * calculate data for lead report
     *
     * @param $request
     *
     * @return mixed
     * */
    public static function getNowReport($data, $account_id)
    {
        $where = [];
        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        $junk_status = LeadStatuses::where('is_junk', '=', '1')->first();
        /*$appointment_info = DB::Select(DB::raw("SELECT A.*,MAX(A.created_at) created_at
        FROM leads AS L JOIN appointments AS A ON L.id = A.lead_id
        WHERE A.base_appointment_status_id = '3'
        AND L.lead_status_id != '5'
        AND A.created_at >= '$start_date'
        AND A.created_at <= '$end_date'
        GROUP BY A.patient_id,A.service_id
        ORDER BY A.created_at DESC"));*/
        $appointment_info = DB::table('leads')->join('appointments', 'leads.id', '=', 'appointments.lead_id')->where([
            ['leads.lead_status_id', '!=', $junk_status->id],
            ['appointments.base_appointment_status_id', '=', Config::get('constants.appointment_status_not_show')],
        ])
            ->whereDate('appointments.created_at', '>=', $start_date)
            ->whereDate('appointments.created_at', '<=', $end_date)
            ->select('appointments.*', DB::raw('max(appointments.created_at) created_at'))
            ->groupby('appointments.patient_id', 'appointments.service_id')
            ->orderby('appointments.created_at', 'DESC')
            ->get();

        $searchServices = Services::where([
            'account_id' => $account_id,
        ])->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id');

        $arrived = AppointmentStatuses::where('is_arrived', '=', '1')->first();
        $pending = AppointmentStatuses::where('is_default', '=', '1')->first();

        foreach ($appointment_info as $key => $infor) {
            $rootService = LocationsWidget::findRoot($infor->service_id, $searchServices);
            $next_appointment_info = DB::table('leads')->join('appointments', 'leads.id', '=', 'appointments.lead_id')
                ->where([
                    ['leads.lead_status_id', '!=', $junk_status->id],
                    ['appointments.patient_id', '=', $infor->patient_id],
                ])
                ->whereIn('appointments.base_appointment_status_id', [$arrived->id, $pending->id])
                ->whereDate('appointments.created_at', '>', $end_date)
                ->select('appointments.*')
                ->get();
            if (count($next_appointment_info) > 0) {
                foreach ($next_appointment_info as $next) {
                    $rootService_next = LocationsWidget::findRoot($next->service_id, $searchServices);
                    if ($rootService_next == $rootService) {
                        unset($appointment_info[$key]);
                        break;
                    }
                }
            }
        }

        return $appointment_info;
    }
}
