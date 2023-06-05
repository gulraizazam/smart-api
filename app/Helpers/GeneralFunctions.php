<?php
/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM.
 */

namespace App\Helpers;

use App\Models\AppointmentLog;
use App\Models\Appointments;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\RoleHasUsers;
use App\Models\Services;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Models\Patients;
use Config;

class GeneralFunctions
{
    public static function cleanNumber($phoneNumber)
    {
        $phoneNumber = str_replace(' ', '', $phoneNumber); // Replaces all spaces with hyphens.
        $phoneNumber = str_replace('-', '', $phoneNumber); // Replaces all spaces with hyphens.

        return self::cleanCountryCodes(preg_replace('/[^0-9\-]/', '', $phoneNumber)); // Removes special chars.
    }

    private static function cleanCountryCodes($phoneNumber)
    {
        //if($_SERVER['REMOTE_ADDR'] == '202.166.167.242'){dd($phoneNumber);}
        // Remove Zero Leading
        if ($phoneNumber[0] == '0') {
            return $phoneNumber = substr($phoneNumber, 1);
        }
        // Remove Coutnry
        if ($phoneNumber[0] == '9' && $phoneNumber[1] == '2') {
            return $phoneNumber = substr($phoneNumber, 2);
        }
        // Remove Zero Leading
        if ($phoneNumber[0] == '0') {
            return $phoneNumber = substr($phoneNumber, 1);
        }

        return $phoneNumber;
    }

    public static function prepareNumber($phoneNumber)
    {
        // Adjust Country Code for Pakistan
        if ($phoneNumber[0] == '3' && (strlen($phoneNumber) >= 9 && strlen($phoneNumber) <= 11)) {
            return '92' . $phoneNumber;
        } else {
            return $phoneNumber;
        }
    }

    public static function prepareNumber4Call($phoneNumber, $type = 0)
    {

        if (!Gate::allows('contact')) {
            return '***********';
        } else {

            if(isset($phoneNumber) && $phoneNumber != ""){
                if ($phoneNumber[0] == '3' && strlen($phoneNumber) == 10 && $type = 0) {
                    return '+92' . $phoneNumber;
                } elseif ($phoneNumber[0] == '3' && strlen($phoneNumber) == 10 && $type = 1) {
                    return '0' . $phoneNumber;
                } else {
                    return $phoneNumber;
                }
            }else{
                return $phoneNumber;
            }
            // Adjust Country Code for Pakistan

        }
    }

    public static function prepareNumber4CallSMS($phoneNumber)
    {
        // Adjust Country Code for Pakistan
        if ($phoneNumber[0] == '3' && strlen($phoneNumber) == 10) {
            return '+92' . $phoneNumber;
        } else {
            return $phoneNumber;
        }
    }

    /**
     * @param $type in string form
     *
     * @return number numeric constant value
     */
    public static function AppointmentType($type)
    {
        return $type == config('constants.appointment_type_consultancy_string') ? config('constants.appointment_type_consultancy') : config('constants.appointment_type_service');
    }

    public static function contactStatus($contact)
    {
        if (!Gate::allows('contact')) {
            return '***********';
        } else {
            return $contact;
        }
    }

    public static function patientSearch($id)
    {
        if (is_numeric($id)) {
            return $id;
        } else {
            if (strpos($id, "C-") == 0) {
                $id = str_replace("C-", "", $id);
                if (strpos($id, "c-") == 0) {
                    return str_replace("c-", "", $id);
                } else {
                    return $id;
                }
            } else {
                return $id;
            }
        }
    }

    public static function patientSearchStringAdd($id)
    {
        if (is_numeric($id)) {
            return 'C-' . $id;
        } else {
            return $id;
        }
    }

    public static function clearnString($string)
    {

        return str_replace([' ', '-', '+'], '', $string);
    }

    public static function getAppointmentType($appointment_id)
    {
        $appointment = Appointments::select('appointment_type_id')->find($appointment_id);

        return $appointment->appointment_type_id ?? 0;
    }

    public static function ServicesTree($request = null, $total = 0)
    {
        $where = [];
        if ($total >= 0) {
            $filename = 'services';
            if(isset($request)){
                $filters = getFilters($request->all());
                $filters['status'] = 0;
                $apply_filter = checkFilters($filters, $filename);
                if (hasFilter($filters, 'name')) {
                    $where[] = ['name','like','%' . $filters['name'] . '%', ];
                    Filters::put(Auth::user()->id, $filename, 'name', $filters['name']);
                } else {
                    if ($apply_filter) {
                        Filters::forget(Auth::User()->id, $filename, 'name');
                    } else {
                        if (Filters::get(Auth::User()->id, $filename, 'name')) {
                            $where[] = [ 'name','like','%' . Filters::get(Auth::user()->id, $filename, 'name') . '%',];
                        }
                    }
                }
                if (hasFilter($filters, 'status')) {
                    $where[] = ['active' => $filters['status'],];
                    Filters::put(Auth::user()->id, $filename, 'status', $filters['status']);
                } else {
                    if ($apply_filter) {
                        Filters::forget(Auth::user()->id, $filename, 'status');
                    } else {
                        if (Filters::get(Auth::user()->id, $filename, 'status') == 0 || Filters::get(Auth::user()->id, $filename, 'status') == 1) {
                            if (Filters::get(Auth::user()->id, $filename, 'status') != null) {
                                $where[] = ['active' => Filters::get(Auth::user()->id, $filename, 'status'),
                                ];
                            }
                        }
                    }
                }
                if(hasFilter($filters, 'status') && hasFilter($filters, 'name') && $filters['status']==1 ){
                    $query = Services::with('children')
                    ->where(['parent_id' =>  0])
                    ->where('slug', '!=','all')
                    ->where($where);
                    $services = $query->get();
                    if(count($services)>0){
                        $mergedServices = [];
                        foreach ($services as $key => $service) {
                            $serv = Services::where('id',$service->id)->first();
                            if($serv->parent_id=="0"){
                                if(Gate::allows("view_inactive_services")){
                                    $children = Services::where(['parent_id' => $service->id,'active' => $filters['status']])->orderBy('name')->get();
                                }else{
                                    $children = Services::where(['parent_id' => $service->id,'active' => 1])->orderBy('name')->get();
                                }
                            }else{
                                $children = collect($service->children)->flatten();
                                unset($service->children);
                            }
                            $mergedServices[] = $service->toArray();
                            $children = $children->toArray();
                            foreach ($children as $child) {
                                $mergedServices[] = $child;
                            }
                        }
                        return $mergedServices;
                    }else{
                        $children = Services::where('active',$filters['status'])->where('name','like', '%' . $filters['name'] . '%')->get();
                        return $children;
                    }
                }
                if(hasFilter($filters, 'status') && hasFilter($filters, 'name') && $filters['status']==0 ){
                    $query = Services::with('children')
                    ->where(['parent_id' => 0])
                    ->where('slug' , '!=', 'all')
                    ->where('name','like','%' . $filters['name'] . '%');
                    $services = $query->get();
                    if(count($services)>0){
                        $mergedServices = [];
                        foreach ($services as $key => $service) {
                            $serv = Services::where(['id' => $service->id])->first();
                            if($serv->parent_id=="0"){
                                if(Gate::allows("view_inactive_services")){
                                    $children = Services::where(['parent_id' => $service->id,'active' => $filters['status']])->orderBy('name')->get();
                                }else{
                                    $children = Services::where(['parent_id' => $service->id, 'active' => 1])->orderBy('name')->get();
                                }
                            }else{
                                $children = collect($service->children)->flatten();
                                unset($service->children);
                            }
                            $mergedServices[] = $service->toArray();
                            $children = $children->toArray();
                            foreach ($children as $child) {
                                $mergedServices[] = $child;
                            }
                        }
                        return $mergedServices;
                    }else{
                        $children = Services::where(['active' => $filters['status']])->where( 'name','like','%' . $filters['name'] . '%')->get();
                        return $children;
                    }
                }
                if(hasFilter($filters, 'status') && $filters['status']==1){
                    $query = Services::with('children')
                    ->where(['parent_id' => 0])
                    ->where('slug', '!=','all')
                    ->where($where);
                    $services = $query->get();
                    $mergedServices = [];
                    foreach ($services as $key => $service) {
                        if(Gate::allows("view_inactive_services")){
                            $children = Services::where(['parent_id' => $service->id , 'active' => $filters['status']])->orderBy('name')->get();
                        }else{
                            $children = Services::where(['parent_id' => $service->id , 'active' => 1])->orderBy('name')->get();
                        }
                        $mergedServices[] = $service->toArray();
                        $children = $children->toArray();
                        foreach ($children as $child) {
                            $mergedServices[] = $child;
                        }
                    }
                    return $mergedServices;
                }
                if(hasFilter($filters, 'status') && $filters['status']==0){
                    $query = Services::with('children')
                    ->where(['parent_id' => 0])
                    ->where('slug' ,'!=','all');
                    $services = $query->get();
                    $mergedServices = [];
                    foreach ($services as $key => $service) {
                        if(Gate::allows("view_inactive_services")){
                            $children = Services::where(['parent_id' => $service->id , 'active' => $filters['status']])->orderBy('name')->get();
                        }else{
                            $children = Services::where(['parent_id' => $service->id ,'active' => 1])->orderBy('name')->get();
                        }
                        $mergedServices[] = $service->toArray();
                        $children = $children->toArray();
                        foreach ($children as $child) {
                            $mergedServices[] = $child;
                        }
                    }
                    return $mergedServices;
                }
                if(hasFilter($filters, 'name')){
                    $query = Services::with('children')
                        ->where('slug', '!=', 'all')
                        ->when(isset($where) && count($where) > 0, fn($q) => $q->where($where));
                        $services = $query->get();
                        $mergedServices = [];
                        foreach ($services as $key => $service) {
                            if($service->parent_id=="0"){
                               $children = Services::where('parent_id',$service->id)->orderBy('name')->get()->toArray();
                               $mergedServices[] = $service->toArray();
                                foreach ($children as $child) {
                                    $mergedServices[] = $child;
                                }
                            } else {
                                $mergedServices[] = $service->toArray();
                            }
                        }
                    return $mergedServices;
                }
            }
            $query = Services::with('children')
            ->where(['parent_id' => 0])
            ->where('slug','!=', 'all')
            ->when(isset($where) && count($where) > 0, fn($q) => $q->where($where));
            $services = $query->get();
            $mergedServices = [];
            foreach ($services as $key => $service) {
                if(Gate::allows("view_inactive_services")){
                    $children = Services::where(['parent_id' => $service->id])->orderBy('name')->get();
                }else{
                    $children = Services::where(['parent_id' => $service->id , 'active' => 1])->orderBy('name')->get();
                }
                $mergedServices[] = $service->toArray();
                $children = $children->toArray();
                foreach ($children as $child) {
                    $mergedServices[] = $child;
                }
            }
            return $mergedServices;
        }
    }

    public static function ServicesTreeList($request = null, $total = 0, $id = null)
    {
        $where = [];
        if ($total >= 0 && $id == null) {
            $filename = 'services';
            if(isset($request)){
                $filters = getFilters($request->all());
                $filters['status'] = 0;
                $apply_filter = checkFilters($filters, $filename);
                if (hasFilter($filters, 'name')) {
                    $where[] = ['name', 'like','%' . $filters['name'] . '%',
                    ];
                    Filters::put(Auth::user()->id, $filename, 'name', $filters['name']);
                } else {
                    if ($apply_filter) {
                        Filters::forget(Auth::User()->id, $filename, 'name');
                    } else {
                        if (Filters::get(Auth::User()->id, $filename, 'name')) {
                            $where[] = [ 'name', 'like','%' . Filters::get(Auth::user()->id, $filename, 'name') . '%',
                            ];
                        }
                    }
                }
                if (hasFilter($filters, 'status')) {
                    $where[] = [ 'active' => $filters['status'],
                    ];
                    Filters::put(Auth::user()->id, $filename, 'status', $filters['status']);
                } else {
                    if ($apply_filter) {
                        Filters::forget(Auth::user()->id, $filename, 'status');
                    } else {
                        if (Filters::get(Auth::user()->id, $filename, 'status') == 0 || Filters::get(Auth::user()->id, $filename, 'status') == 1) {
                            if (Filters::get(Auth::user()->id, $filename, 'status') != null) {
                                $where[] = ['active' => Filters::get(Auth::user()->id, $filename, 'status'),
                                ];
                            }
                        }
                    }
                }
                if(hasFilter($filters, 'status') && hasFilter($filters, 'name') && $filters['status']==1 ){
                    $query = Services::with('children')
                    ->where('parent_id', 0)
                    ->where('slug', '!=', 'all')
                    ->where($where);
                    $services = $query->get();
                    if(count($services)>0){
                        $mergedServices = [];
                        foreach ($services as $key => $service) {
                            $serv = Services::where('id',$service->id)->first();
                            if($serv->parent_id=="0"){
                                if(Gate::allows("view_inactive_services")){
                                    $children = Services::where('parent_id',$service->id)->where('active',$filters['status'])->orderBy('name')->get();
                                }else{
                                    $children = Services::where('parent_id',$service->id)->where('active',1)->orderBy('name')->get();
                                }
                            }else{
                                $children = collect($service->children)->flatten();
                                unset($service->children);
                            }
                            $mergedServices[] = $service->toArray();
                            $children = $children->toArray();
                            foreach ($children as $child) {
                                $mergedServices[] = $child;
                            }
                        }
                        return $mergedServices;
                    }else{
                        $children = Services::where('active',$filters['status'])->where( 'name',
                            'like',
                            '%' . $filters['name'] . '%')->get();
                            return $children;
                    }
                }
                if(hasFilter($filters, 'status') && hasFilter($filters, 'name') && $filters['status']==0 ){
                    $query = Services::with('children')
                    ->where('parent_id', 0)
                    ->where('slug', '!=', 'all')
                    ->where('name',
                    'like',
                    '%' . $filters['name'] . '%');
                    $services = $query->get();
                    if(count($services)>0){
                        $mergedServices = [];
                        foreach ($services as $key => $service) {
                            $serv = Services::where(['id' => $service->id])->first();
                            if($serv->parent_id=="0"){
                                if(Gate::allows("view_inactive_services")){
                                    $children = Services::where(['parent_id' => $service->id , 'active' => $filters['status']])->orderBy('name')->get();
                                }else{
                                    $children = Services::where(['parent_id' => $service->id,'active' => 1])->orderBy('name')->get();
                                }
                            }else{
                                $children = collect($service->children)->flatten();
                                unset($service->children);

                            }
                            $mergedServices[] = $service->toArray();
                            $children = $children->toArray();
                            foreach ($children as $child) {
                                $mergedServices[] = $child;
                            }
                        }
                        return $mergedServices;
                    }else{
                        $children = Services::where('active',$filters['status'])->where( 'name',
                            'like',
                            '%' . $filters['name'] . '%')->get();
                            return $children;
                    }
                }
                if(hasFilter($filters, 'status') && $filters['status']==1){
                    $query = Services::with('children')
                    ->where('parent_id', 0)
                    ->where('slug', '!=', 'all')
                    ->where($where);
                    $services = $query->get();
                    $mergedServices = [];
                    foreach ($services as $key => $service) {
                        if(Gate::allows("view_inactive_services")){
                            $children = Services::where(['parent_id' => $service->id, 'active' => $filters['status']])->orderBy('name')->get();
                        }else{
                            $children = Services::where(['parent_id' => $service->id ,'active' => 1 ])->orderBy('name')->get();
                        }
                        $mergedServices[] = $service->toArray();
                        $children = $children->toArray();
                        foreach ($children as $child) {
                            $mergedServices[] = $child;
                        }
                    }
                    return $mergedServices;
                }
                if(hasFilter($filters, 'status') && $filters['status']==0){
                    $query = Services::with('children')
                    ->where('parent_id', 0)
                    ->where('slug', '!=', 'all');
                    $services = $query->get();
                    $mergedServices = [];
                    foreach ($services as $key => $service) {
                        if(Gate::allows("view_inactive_services")){
                            $children = Services::where(['parent_id' => $service->id , 'active' => $filters['status']])->where()->orderBy('name')->get();
                        }else{
                            $children = Services::where(['parent_id' => $service->id , 'active' => 1])->orderBy('name')->get();
                        }
                        $mergedServices[] = $service->toArray();
                        $children = $children->toArray();
                        foreach ($children as $child) {
                            $mergedServices[] = $child;
                        }
                    }
                    return $mergedServices;
                }
                if(hasFilter($filters, 'name')){
                    $query = Services::with('children')
                        ->where('slug', '!=', 'all')
                        ->when(isset($where) && count($where) > 0, fn($q) => $q->where($where));
                        $services = $query->get();
                        $mergedServices = [];
                        foreach ($services as $key => $service) {
                            if($service->parent_id=="0"){
                               $children = Services::where(['parent_id' => $service->id])->orderBy('name')->get()->toArray();
                               $mergedServices[] = $service->toArray();
                                foreach ($children as $child) {
                                    $mergedServices[] = $child;
                                }
                            } else {
                                $mergedServices[] = $service->toArray();
                            }
                        }
                    return $mergedServices;
                }
            }
            $query = Services::with(['children'=> function($q){
                $q->orderBy('name');
            }])
            ->where(['parent_id' => 0])
            ->where('slug', '!=', 'all')
            ->when(isset($where) && count($where) > 0, fn($q) => $q->where($where));
            $services = $query->get()->toArray();
            $allserviceslug = Services::where(['slug' => 'all'])->first()->toArray();
            array_unshift($services, $allserviceslug);
            return $services;
        } else {
            $query = Services::with(['children'=> function($q) {
                $q->orderBy('name');
            }])
            ->where(['id' => $id, 'parent_id' => 0])
            ->where('slug', '!=', 'all');
            $services[] = $query->first()->toArray();
            $allserviceslug = Services::where(['slug' => 'all'])->first()->toArray();
            array_unshift($services, $allserviceslug);
            return $services;
        }
    }

    private static function appendAllService()
    {
        $allService = [];
        $allService['id'] = 0;
        $allService['parent_id'] = 0;
        $allService['name'] = "All Services";
        $allService['slug'] = 'custom';
        $allService['active'] = 1;
        $allService['color'] = "#2d2aea";
        $allService['price'] = 0;
        $allService['complimentory'] = 0;
        $allService['duration'] = 0;

        return $allService;
    }


    public static function duration()
    {
        $timeStep = 5;
        $timeArray = [];
        $startTime = new \DateTime('00:00');
        $endTime = new \DateTime('23:55');

        while ($startTime <= $endTime) {
            $timeArray[] = $startTime->format('H:i');
            $startTime->add(new \DateInterval('PT' . $timeStep . 'M'));
        }

        return $timeArray;
    }

    public static function parentServices()
    {
        return Services::where('parent_id', 0)->where('slug', '!=', 'all')->get(['id', 'name']);
    }

    public static function smsTemplateVariables($slug)
    {
        $options = array();
        if ($slug == 'invoice-ringup') {
            $options['Invoices']['##patient_name##'] = 'Patient Name';
            $options['Invoices']['##service_name##'] = 'Service Name';
            $options['Invoices']['##created_at##'] = 'Invoice Ringup Date';
            $options['Invoices']['##remaining_balance##'] = 'Remaining Balance';
        } elseif ($slug == 'plan-cash') {

            $options['Plans']["##id##"] = 'Plan Id';
            $options['Plans']["##patient_name##"] = 'Patient Name';

            $options['Package Advances']["##cash_amount##"] = 'Cash Amount';
            $options['Package Advances']["##created_at##"] = 'Amount Received Date';
        } elseif ($slug == 'refund-amount') {
            $options['Refund']["##patient_name##"] = 'Patient Name';

            $options['Package Advances']["##cash_amount##"] = 'Cash Amount';
            $options['Package Advances']["##created_at##"] = 'Refund Date';
        } else {
            $options['Appointments']["##patient_name##"] = 'Patient Name';
            $options['Appointments']["##patient_phone##"] = 'Patient Phone';
            $options['Appointments']["##doctor_name##"] = 'Doctor Name';
            $options['Appointments']["##doctor_profile_link##"] = 'Doctor Profile Link';
            $options['Appointments']["##appointment_date##"] = 'Appointment Date';
            $options['Appointments']["##appointment_time##"] = 'Appointment Time';
            $options['Appointments']["##appointment_service##"] = 'Appointment Service';
            $options['Appointments']["##fdo_name##"] = 'FDO Name';
            $options['Appointments']["##fdo_phone##"] = 'FDO Phone';
            $options['Appointments']["##centre_name##"] = 'Centre Name';
            $options['Appointments']["##centre_address##"] = 'Centre Address';
            $options['Appointments']["##centre_google_map##"] = 'Centre Google Map';

            $options['Leads']["##name##"] = 'Full Name';
            $options['Leads']["##email##"] = 'Email';
            $options['Leads']["##phone##"] = 'Phone';
            $options['Leads']["##gender##"] = 'Gender';
            $options['Leads']["##city_name##"] = 'City';
            $options['Leads']["##lead_source_name##"] = 'Lead Source';
            $options['Leads']["##lead_status_name##"] = 'Lead Status';

            $options['Others']["##head_office_phone##"] = 'Head Office Phone';
        }
        return $options;
    }

    public static function saveAppointmentLogs($action, $screen, $data) {

        try {

            AppointmentLog::create([
                'user_id' => auth()->id(),
                'action_by' => auth()->user()->name ?? 'Admin',
                'action_for' => $data->name ?? '',
                'action' => $action,
                'screen' => $screen,
                'address' => Locations::find($data->location_id ?? 0)->name ?? '',
                'date' => Carbon::now()->timezone("Asia/Karachi")->format("Y-m-d"),
                'time' => Carbon::now()->timezone("Asia/Karachi")->format("H:i:s"),
                'type' => $action,
            ]);
        } catch (\Exception $e) {
           //
        }

    }

    public static function getFDM($location_ids = null) {
        $fdo_ids = [];
        $fdm_ids = [];
        if ($location_ids && count($location_ids) > 0) {
            $fdo_phones = Locations::whereIn('id', $location_ids)->pluck('fdo_phone');;
            if ($fdo_phones->count()) {
                foreach ($fdo_phones as $fdo_phone) {
                    $fdo_ids[] = User::where('phone', GeneralFunctions::cleanNumber($fdo_phone ?? 0))
                        ->where('user_type_id', 2)->value('id');
                }
            }

            $fdm_ids = count($fdo_ids) > 0 ? array_filter($fdo_ids) : [0];
        }

        if (count($fdm_ids) > 0) {
            return $fdm_ids;
        }

        $fdm_ids = DB::table('role_has_users')
            ->whereIn('role_id', ['4'])
            ->pluck('user_id')->toArray();

        return $fdm_ids;

    }

    public static function getCSR()
    {
        $csr_user_ids = DB::table('role_has_users')
            ->whereIn('role_id', ['2', '3'])
            ->pluck('user_id')->toArray();

        return $csr_user_ids;

    }

    public static function getLocationIds($location_id)
    {
        if ($location_id) {

            $location_ids = null;
            if (is_string($location_id)) {
                $location_id = explode(',', $location_id);
            }
            $locationIds = array_filter($location_id);
            if (isset($locationIds) && count($locationIds)) {
                $location_ids = $locationIds;
            }

            return $location_ids;
        }

        return null;
    }

    public static function patientNameUpdate($phone, $name){
        $accountId = Auth::user()->account_id;
        $patientPhone = GeneralFunctions::cleanNumber($phone);
        Leads::where(['phone' => $patientPhone])->update([
            'name' => $name,
        ]);

        Patients::where([
            'phone' => $patientPhone,
            'user_type_id' => Config::get('constants.patient_id'),
            'account_id' => $accountId
        ])->update(['name' => $name]);

        Appointments::whereIn('patient_id', function ($query) use ($patientPhone, $accountId) {
            $query->select('id')
                ->from('users')
                ->where([
                    'phone' => $patientPhone,
                    'user_type_id' => Config::get('constants.patient_id'),
                    'account_id' => $accountId
                ]);
        })->update(['name' => $name]);
    }

}
