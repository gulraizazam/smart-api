<?php

namespace App\Models;

use App\Helpers\GeneralFunctions;
use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

class Leads extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['patient_id', 'region_id', 'city_id', 'lead_status_id', 'lead_source_id', 'msg_count', 'active', 'created_by', 'updated_by', 'converted_by', 'town_id', 'created_at', 'updated_at', 'account_id', 'location_id', 'name', 'email', 'phone', 'gender', 'referred_by', 'meta_lead_id'];

    protected static $_fillable = ['region_id', 'city_id', 'lead_status_id', 'lead_source_id', 'msg_count', 'service_id', 'town_id'];

    protected $table = 'leads';

    protected static $_table = 'leads';

    /**
     * Get fillable fields for audit trail
     */
    public static function getFillableFields(): array
    {
        return self::$_fillable;
    }

    /**
     * Get the Treatment that owns the Lead.
     */
    public function lead_service()
    {
        return $this->hasMany(LeadsServices::class, 'lead_id')->with('service:id,name,parent_id', 'childservice:id,name,parent_id', 'leadStatus:id,name');
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
    public static function getData($id)
    {
        return self::with('lead_service')->where([
            ['id', '=', $id],
            ['account_id', '=', Auth::user()->account_id],
        ])->first();
    }

    /**
     * Search leads by phone
     * @deprecated Use LeadService::searchLeadsByPhone() instead
     */
    public static function getLeadPhoneAjax($phone, $account_id)
    {
        return self::where([
            ['active', '=', '1'],
            ['account_id', '=', $account_id],
            ['phone', 'LIKE', "%{$phone}%"],
        ])->select('name', 'id', 'phone')->limit(50)->get();
    }

    /**
     * Search leads by ID or name
     * @deprecated Use LeadService::searchLeadsById() instead
     */
    public static function getLeadidAjax($name, $account_id)
    {
        // Inline string cleaning for better performance (avoid function call overhead)
        $cleaned = str_replace([' ', '-', '+', 'C-', 'c-'], '', $name);
        
        // Optimize: For phone search (most common case ~90%), check first
        if (is_numeric($cleaned)) {
            // Clean phone number inline (remove leading 0 and country code 92)
            $phone = ltrim($cleaned, '0');
            if (substr($phone, 0, 2) === '92') {
                $phone = substr($phone, 2);
            }
            
            // Database stores phones in multiple formats (with/without leading 0)
            // Search for both: original cleaned input AND cleaned phone
            $phoneVariations = array_unique([$cleaned, $phone, '0' . $phone]);
            
            // Try exact match first (fastest - uses composite index)
            // Search all leads regardless of status (including booked)
            $exact_match = self::select('name', 'id', 'phone')
                ->where('account_id', $account_id)
                ->whereIn('phone', $phoneVariations)
                ->limit(10)
                ->get();
            
            if (!$exact_match->isEmpty()) {
                return $exact_match;
            }
            
            // Prefix search with GROUP BY at DB level (uses index efficiently)
            // Search all possible phone variations
            return self::select('name', 'id', 'phone')
                ->where('account_id', $account_id)
                ->where(function($query) use ($phoneVariations) {
                    foreach ($phoneVariations as $variation) {
                        $query->orWhere('phone', 'LIKE', $variation . '%');
                    }
                })
                ->groupBy('phone', 'name', 'id')
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get();
        }
        
        // ID search (less common ~5%)
        if (is_numeric($name) && strlen($name) <= 10) {
            return self::select('name', 'id', 'phone')
                ->where('account_id', $account_id)
                ->where('id', $name)
                ->limit(10)
                ->get();
        }

        // Name search (least common ~5%) - use prefix for index optimization
        return self::select('name', 'id', 'phone')
            ->where('account_id', $account_id)
            ->where('name', 'LIKE', $name . '%')
            ->groupBy('phone', 'name', 'id')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();
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
    public static function createRecord($leads_data, $status = null)
    {

        if ($status == 'Appointment') {
            $leads_data['service_id'] = $leads_data['base_service_id'];
            $record = Leads::updateOrCreate([
                'phone' => $leads_data['phone'],
                'account_id' => Auth::User()->account_id,
                'created_at' => Carbon::now()->timestamp,
            ], $leads_data);
            $final_data = $record;
            $leads_data['lead_id'] = $final_data->id;
            $service = LeadsServices::create($leads_data);
        } else {
            if (isset($leads_data['city_id']) && $leads_data['city_id']) {
                // Set Region ID
                $leads_data['region_id'] = Cities::findOrFail($leads_data['city_id'])->region_id;
            }
            $check_lead_existance = Leads::where([
                'phone' => $leads_data['phone'],
                'account_id' => Auth::User()->account_id,
            ])->first();
            if (! $check_lead_existance) {
                $record = Leads::create($leads_data);
            } else {
                // Get default Open lead status
                $openStatus = LeadStatuses::where(['account_id' => Auth::User()->account_id, 'is_default' => 1])->first();
                if ($openStatus) {
                    $check_lead_existance->lead_status_id = $openStatus->id;
                }
                $check_lead_existance->created_at = Carbon::now()->timestamp;
                $check_lead_existance->update();
                $record = $check_lead_existance;
                $leads_data['lead_id'] = $record->id;
            }
            $final_data = $leads_data;
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
    public static function updateRecord($id, $leads_data, $status = false)
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
        if (isset($leads_data['city_id']) && $leads_data['city_id']) {
            // Set Region ID
            $leads_data['region_id'] = Cities::findOrFail($leads_data['city_id'])->region_id;
        }
        $leads_data['created_at'] = Carbon::now()->timestamp;
        $record->update($leads_data);

        AuditTrails::editEventLogger(self::$_table, 'Edit', $leads_data, self::$_fillable, $old_data, $record);

        return $record;
    }

    /*
     * calculate data for lead report
     *
     * @param $request
     *
     * @return mixed
     * */
    public static function getLeadReport($leads_data)
    {
        $where = [];
        if (isset($leads_data['date_range']) && $leads_data['date_range']) {
            $date_range = explode(' - ', $leads_data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($leads_data['cnic']) && $leads_data['cnic']) {
            $where[] = [
                'users.cnic',
                '=',
                $leads_data['cnic'],
            ];
        }
        if (isset($leads_data['dob']) && $leads_data['dob']) {
            $where[] = [
                'users.dob',
                '=',
                $leads_data['dob'],
            ];
        }
        if (isset($leads_data['patient_id']) && $leads_data['patient_id']) {
            $where[] = [
                'users.id',
                '=',
                $leads_data['patient_id'],
            ];
        }
        if (isset($leads_data['email']) && $leads_data['email']) {
            $where[] = [
                'users.email',
                'like',
                '%'.$leads_data['email'].'%',
            ];
        }
        if (isset($leads_data['gender_id']) && $leads_data['gender_id']) {
            $where[] = [
                'users.gender',
                '=',
                $leads_data['gender_id'],
            ];
        }

        return $query->select('name', 'id', 'phone')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get()
            ->unique('phone');
    }

}
