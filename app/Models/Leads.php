<?php

namespace App\Models;

use App\Helpers\GeneralFunctions;
use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

class Leads extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['region_id', 'city_id', 'lead_status_id', 'lead_source_id', 'msg_count', 'active', 'created_by', 'updated_by', 'converted_by', 'town_id', 'created_at', 'updated_at', 'account_id', 'location_id', 'name', 'email', 'phone', 'gender', 'referred_by', 'meta_lead_id'];

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
        if (is_numeric($name)) {
            $leads = self::where([
                'active' => '1',
                'account_id' => $account_id,
                'id' => $name,
            ])->select('name', 'id', 'phone')->get();
            
            if ($leads->count() > 0) {
                return $leads;
            }
        }

        $name = GeneralFunctions::patientSearch($name);
        $phone_numeric = GeneralFunctions::clearnString($name);

        $query = self::where(['active' => '1', 'account_id' => $account_id]);
        
        if (is_numeric($phone_numeric)) {
            $phone = GeneralFunctions::cleanNumber($name);
            $query->where('phone', 'LIKE', "%{$phone}%");
        } else {
            $query->where('name', 'LIKE', "%{$name}%");
        }

        return $query->select('name', 'id', 'phone')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get()
            ->unique('phone');
    }

}
