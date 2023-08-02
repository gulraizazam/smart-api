<?php

/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM.
 */

namespace App\Helpers;

use Config;
use App\Models\User;
use App\Models\Leads;
use App\Models\Patients;
use App\Models\Services;
use App\Models\Locations;
use App\Models\Appointments;
use App\Models\AppointmentLog;
use Illuminate\Support\Carbon;
use App\Models\PackageAdvances;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

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

            if (isset($phoneNumber) && $phoneNumber != '') {
                if ($phoneNumber[0] == '3' && strlen($phoneNumber) == 10 && $type = 0) {
                    return '+92' . $phoneNumber;
                } elseif ($phoneNumber[0] == '3' && strlen($phoneNumber) == 10 && $type = 1) {
                    return '0' . $phoneNumber;
                } else {
                    return $phoneNumber;
                }
            } else {
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
            if (strpos($id, 'C-') == 0) {
                $id = str_replace('C-', '', $id);
                if (strpos($id, 'c-') == 0) {
                    return str_replace('c-', '', $id);
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

    public static function servicesList($request = null, $total = 0)
    {
        $where = [];
        if ($total >= 0) {
            $filename = 'services';
            if (isset($request)) {
                $filters = getFilters($request->all());
                $apply_filter = checkFilters($filters, $filename);
                if (hasFilter($filters, 'name')) {
                    Filters::put(Auth::user()->id, $filename, 'name', $filters['name']);
                } else {
                    if ($apply_filter) {
                        Filters::forget(Auth::User()->id, $filename, 'name');
                    }
                }
                if (hasFilter($filters, 'status')) {
                    Filters::put(Auth::user()->id, $filename, 'status', $filters['status']);
                } else {
                    if ($apply_filter) {
                        Filters::forget(Auth::user()->id, $filename, 'status');
                    }
                }
            }
            $services = Services::where('slug', '!=', 'all')
                ->where(['parent_id' => 0])
                ->when(hasFilter($filters, 'name'), fn ($q) => $q->where('name', 'like', '%' . $filters['name'] . '%'))
                ->orderBy('id', 'asc')
                ->get();
            $mergedServices = [];
            foreach ($services as $service) {
                $children = Services::where(['parent_id' => $service->id])->when(hasFilter($filters, 'status'), fn ($q) => $q->where(['active' => $filters['status']]))->orderBy('id', 'asc')->get()->toArray();

                $mergedServices[] = $service->toArray();
                foreach ($children as $child) {
                    $mergedServices[] = $child;
                }
            }

            return $mergedServices;
        }
    }

    public static function ServicesTree($request = null, $total = 0)
    {
        $where = [];
        if ($total >= 0) {
            $filename = 'services';
            if (isset($request)) {
                $filters = getFilters($request->all());
                $filters['status'] = 0;
                $apply_filter = checkFilters($filters, $filename);
                if (hasFilter($filters, 'name')) {
                    $where[] = ['name', 'like', '%' . $filters['name'] . '%'];
                    Filters::put(Auth::user()->id, $filename, 'name', $filters['name']);
                } else {
                    if ($apply_filter) {
                        Filters::forget(Auth::User()->id, $filename, 'name');
                    } else {
                        if (Filters::get(Auth::User()->id, $filename, 'name')) {
                            $where[] = ['name', 'like', '%' . Filters::get(Auth::user()->id, $filename, 'name') . '%'];
                        }
                    }
                }
                if (hasFilter($filters, 'status')) {
                    $where[] = ['active' => $filters['status']];
                    Filters::put(Auth::user()->id, $filename, 'status', $filters['status']);
                } else {
                    if ($apply_filter) {
                        Filters::forget(Auth::user()->id, $filename, 'status');
                    } else {
                        if (Filters::get(Auth::user()->id, $filename, 'status') == 0 || Filters::get(Auth::user()->id, $filename, 'status') == 1) {
                            if (Filters::get(Auth::user()->id, $filename, 'status') != null) {
                                $where[] = [
                                    'active' => Filters::get(Auth::user()->id, $filename, 'status'),
                                ];
                            }
                        }
                    }
                }
                if (hasFilter($filters, 'status') && hasFilter($filters, 'name') && $filters['status'] == 1) {
                    $query = Services::with('children')
                        ->where(['parent_id' => 0])
                        ->where('slug', '!=', 'all')
                        ->where($where);
                    $services = $query->get();
                    if (count($services) > 0) {
                        $mergedServices = [];
                        foreach ($services as $key => $service) {
                            $serv = Services::where('id', $service->id)->first();
                            if ($serv->parent_id == '0') {
                                if (Gate::allows('view_inactive_services')) {
                                    $children = Services::where(['parent_id' => $service->id, 'active' => $filters['status']])->orderBy('name')->get();
                                } else {
                                    $children = Services::where(['parent_id' => $service->id, 'active' => 1])->orderBy('name')->get();
                                }
                            } else {
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
                    } else {
                        $children = Services::where('active', $filters['status'])->where('name', 'like', '%' . $filters['name'] . '%')->get();

                        return $children;
                    }
                }
                if (hasFilter($filters, 'status') && hasFilter($filters, 'name') && $filters['status'] == 0) {
                    $query = Services::with('children')
                        ->where(['parent_id' => 0])
                        ->where('slug', '!=', 'all')
                        ->where('name', 'like', '%' . $filters['name'] . '%');
                    $services = $query->get();
                    if (count($services) > 0) {
                        $mergedServices = [];
                        foreach ($services as $key => $service) {
                            $serv = Services::where(['id' => $service->id])->first();
                            if ($serv->parent_id == '0') {
                                if (Gate::allows('view_inactive_services')) {
                                    $children = Services::where(['parent_id' => $service->id, 'active' => $filters['status']])->orderBy('name')->get();
                                } else {
                                    $children = Services::where(['parent_id' => $service->id, 'active' => 1])->orderBy('name')->get();
                                }
                            } else {
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
                    } else {
                        $children = Services::where(['active' => $filters['status']])->where('name', 'like', '%' . $filters['name'] . '%')->get();

                        return $children;
                    }
                }
                if (hasFilter($filters, 'status') && $filters['status'] == 1) {
                    $query = Services::with('children')
                        ->where(['parent_id' => 0])
                        ->where('slug', '!=', 'all')
                        ->where($where);
                    $services = $query->get();
                    $mergedServices = [];
                    foreach ($services as $key => $service) {
                        if (Gate::allows('view_inactive_services')) {
                            $children = Services::where(['parent_id' => $service->id, 'active' => $filters['status']])->orderBy('name')->get();
                        } else {
                            $children = Services::where(['parent_id' => $service->id, 'active' => 1])->orderBy('name')->get();
                        }
                        $mergedServices[] = $service->toArray();
                        $children = $children->toArray();
                        foreach ($children as $child) {
                            $mergedServices[] = $child;
                        }
                    }

                    return $mergedServices;
                }
                if (hasFilter($filters, 'status') && $filters['status'] == 0) {
                    $query = Services::with('children')
                        ->where(['parent_id' => 0])
                        ->where('slug', '!=', 'all');
                    $services = $query->get();
                    $mergedServices = [];
                    foreach ($services as $key => $service) {
                        if (Gate::allows('view_inactive_services')) {
                            $children = Services::where(['parent_id' => $service->id, 'active' => $filters['status']])->orderBy('name')->get();
                        } else {
                            $children = Services::where(['parent_id' => $service->id, 'active' => 1])->orderBy('name')->get();
                        }
                        $mergedServices[] = $service->toArray();
                        $children = $children->toArray();
                        foreach ($children as $child) {
                            $mergedServices[] = $child;
                        }
                    }

                    return $mergedServices;
                }
                if (hasFilter($filters, 'name')) {
                    $query = Services::with('children')
                        ->where('slug', '!=', 'all')
                        ->when(isset($where) && count($where) > 0, fn ($q) => $q->where($where));
                    $services = $query->get();
                    $mergedServices = [];
                    foreach ($services as $key => $service) {
                        if ($service->parent_id == '0') {
                            $children = Services::where('parent_id', $service->id)->orderBy('name')->get()->toArray();
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
                ->where('slug', '!=', 'all')
                ->when(isset($where) && count($where) > 0, fn ($q) => $q->where($where));
            $services = $query->get();
            $mergedServices = [];
            foreach ($services as $key => $service) {
                if (Gate::allows('view_inactive_services')) {
                    $children = Services::where(['parent_id' => $service->id])->orderBy('name')->get();
                } else {
                    $children = Services::where(['parent_id' => $service->id, 'active' => 1])->orderBy('name')->get();
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
            if (isset($request)) {
                $filters = getFilters($request->all());
                $filters['status'] = 0;
                $apply_filter = checkFilters($filters, $filename);
                if (hasFilter($filters, 'name')) {
                    $where[] = [
                        'name', 'like', '%' . $filters['name'] . '%',
                    ];
                    Filters::put(Auth::user()->id, $filename, 'name', $filters['name']);
                } else {
                    if ($apply_filter) {
                        Filters::forget(Auth::User()->id, $filename, 'name');
                    } else {
                        if (Filters::get(Auth::User()->id, $filename, 'name')) {
                            $where[] = [
                                'name', 'like', '%' . Filters::get(Auth::user()->id, $filename, 'name') . '%',
                            ];
                        }
                    }
                }
                if (hasFilter($filters, 'status')) {
                    $where[] = [
                        'active' => $filters['status'],
                    ];
                    Filters::put(Auth::user()->id, $filename, 'status', $filters['status']);
                } else {
                    if ($apply_filter) {
                        Filters::forget(Auth::user()->id, $filename, 'status');
                    } else {
                        if (Filters::get(Auth::user()->id, $filename, 'status') == 0 || Filters::get(Auth::user()->id, $filename, 'status') == 1) {
                            if (Filters::get(Auth::user()->id, $filename, 'status') != null) {
                                $where[] = [
                                    'active' => Filters::get(Auth::user()->id, $filename, 'status'),
                                ];
                            }
                        }
                    }
                }
                if (hasFilter($filters, 'status') && hasFilter($filters, 'name') && $filters['status'] == 1) {
                    $query = Services::with('children')
                        ->where('parent_id', 0)
                        ->where('slug', '!=', 'all')
                        ->where($where);
                    $services = $query->get();
                    if (count($services) > 0) {
                        $mergedServices = [];
                        foreach ($services as $key => $service) {
                            $serv = Services::where('id', $service->id)->first();
                            if ($serv->parent_id == '0') {
                                if (Gate::allows('view_inactive_services')) {
                                    $children = Services::where('parent_id', $service->id)->where('active', $filters['status'])->orderBy('name')->get();
                                } else {
                                    $children = Services::where('parent_id', $service->id)->where('active', 1)->orderBy('name')->get();
                                }
                            } else {
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
                    } else {
                        $children = Services::where('active', $filters['status'])->where(
                            'name',
                            'like',
                            '%' . $filters['name'] . '%'
                        )->get();

                        return $children;
                    }
                }
                if (hasFilter($filters, 'status') && hasFilter($filters, 'name') && $filters['status'] == 0) {
                    $query = Services::with('children')
                        ->where('parent_id', 0)
                        ->where('slug', '!=', 'all')
                        ->where(
                            'name',
                            'like',
                            '%' . $filters['name'] . '%'
                        );
                    $services = $query->get();
                    if (count($services) > 0) {
                        $mergedServices = [];
                        foreach ($services as $key => $service) {
                            $serv = Services::where(['id' => $service->id])->first();
                            if ($serv->parent_id == '0') {
                                if (Gate::allows('view_inactive_services')) {
                                    $children = Services::where(['parent_id' => $service->id, 'active' => $filters['status']])->orderBy('name')->get();
                                } else {
                                    $children = Services::where(['parent_id' => $service->id, 'active' => 1])->orderBy('name')->get();
                                }
                            } else {
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
                    } else {
                        $children = Services::where('active', $filters['status'])->where(
                            'name',
                            'like',
                            '%' . $filters['name'] . '%'
                        )->get();

                        return $children;
                    }
                }
                if (hasFilter($filters, 'status') && $filters['status'] == 1) {
                    $query = Services::with('children')
                        ->where('parent_id', 0)
                        ->where('slug', '!=', 'all')
                        ->where($where);
                    $services = $query->get();
                    $mergedServices = [];
                    foreach ($services as $key => $service) {
                        if (Gate::allows('view_inactive_services')) {
                            $children = Services::where(['parent_id' => $service->id, 'active' => $filters['status']])->orderBy('name')->get();
                        } else {
                            $children = Services::where(['parent_id' => $service->id, 'active' => 1])->orderBy('name')->get();
                        }
                        $mergedServices[] = $service->toArray();
                        $children = $children->toArray();
                        foreach ($children as $child) {
                            $mergedServices[] = $child;
                        }
                    }

                    return $mergedServices;
                }
                if (hasFilter($filters, 'status') && $filters['status'] == 0) {
                    $query = Services::with('children')
                        ->where('parent_id', 0)
                        ->where('slug', '!=', 'all');
                    $services = $query->get();
                    $mergedServices = [];
                    foreach ($services as $key => $service) {
                        if (Gate::allows('view_inactive_services')) {
                            $children = Services::where(['parent_id' => $service->id, 'active' => $filters['status']])->where()->orderBy('name')->get();
                        } else {
                            $children = Services::where(['parent_id' => $service->id, 'active' => 1])->orderBy('name')->get();
                        }
                        $mergedServices[] = $service->toArray();
                        $children = $children->toArray();
                        foreach ($children as $child) {
                            $mergedServices[] = $child;
                        }
                    }

                    return $mergedServices;
                }
                if (hasFilter($filters, 'name')) {
                    $query = Services::with('children')
                        ->where('slug', '!=', 'all')
                        ->when(isset($where) && count($where) > 0, fn ($q) => $q->where($where));
                    $services = $query->get();
                    $mergedServices = [];
                    foreach ($services as $key => $service) {
                        if ($service->parent_id == '0') {
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
            $query = Services::with(['children' => function ($q) {
                $q->orderBy('name');
            }])
                ->where(['parent_id' => 0])
                ->where('slug', '!=', 'all')
                ->when(isset($where) && count($where) > 0, fn ($q) => $q->where($where));
            $services = $query->get()->toArray();
            $allserviceslug = Services::where(['slug' => 'all'])->first()->toArray();
            array_unshift($services, $allserviceslug);

            return $services;
        } else {
            $query = Services::with(['children' => function ($q) {
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

    public static function getServiceId($service_id)
    {
        if (str_contains($service_id, 'bold-')) {
            return str_replace('bold-', '', $service_id);
        }

        return $service_id;
    }

    private static function appendAllService()
    {
        $allService = [];
        $allService['id'] = 0;
        $allService['parent_id'] = 0;
        $allService['name'] = 'All Services';
        $allService['slug'] = 'custom';
        $allService['active'] = 1;
        $allService['color'] = '#2d2aea';
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
        $options = [];
        if ($slug == 'invoice-ringup') {
            $options['Invoices']['##patient_name##'] = 'Patient Name';
            $options['Invoices']['##service_name##'] = 'Service Name';
            $options['Invoices']['##created_at##'] = 'Invoice Ringup Date';
            $options['Invoices']['##remaining_balance##'] = 'Remaining Balance';
        } elseif ($slug == 'plan-cash') {

            $options['Plans']['##id##'] = 'Plan Id';
            $options['Plans']['##patient_name##'] = 'Patient Name';

            $options['Package Advances']['##cash_amount##'] = 'Cash Amount';
            $options['Package Advances']['##created_at##'] = 'Amount Received Date';
        } elseif ($slug == 'refund-amount') {
            $options['Refund']['##patient_name##'] = 'Patient Name';

            $options['Package Advances']['##cash_amount##'] = 'Cash Amount';
            $options['Package Advances']['##created_at##'] = 'Refund Date';
        } else {
            $options['Appointments']['##patient_name##'] = 'Patient Name';
            $options['Appointments']['##patient_phone##'] = 'Patient Phone';
            $options['Appointments']['##doctor_name##'] = 'Doctor Name';
            $options['Appointments']['##doctor_profile_link##'] = 'Doctor Profile Link';
            $options['Appointments']['##appointment_date##'] = 'Appointment Date';
            $options['Appointments']['##appointment_time##'] = 'Appointment Time';
            $options['Appointments']['##appointment_service##'] = 'Appointment Service';
            $options['Appointments']['##fdo_name##'] = 'FDO Name';
            $options['Appointments']['##fdo_phone##'] = 'FDO Phone';
            $options['Appointments']['##centre_name##'] = 'Centre Name';
            $options['Appointments']['##centre_address##'] = 'Centre Address';
            $options['Appointments']['##centre_google_map##'] = 'Centre Google Map';

            $options['Leads']['##name##'] = 'Full Name';
            $options['Leads']['##email##'] = 'Email';
            $options['Leads']['##phone##'] = 'Phone';
            $options['Leads']['##gender##'] = 'Gender';
            $options['Leads']['##city_name##'] = 'City';
            $options['Leads']['##lead_source_name##'] = 'Lead Source';
            $options['Leads']['##lead_status_name##'] = 'Lead Status';

            $options['Others']['##head_office_phone##'] = 'Head Office Phone';
        }

        return $options;
    }

    public static function saveAppointmentLogs($action, $screen, $data)
    {

        try {

            AppointmentLog::create([
                'user_id' => auth()->id(),
                'action_by' => auth()->user()->name ?? 'Admin',
                'action_for' => $data->name ?? '',
                'action' => $action,
                'screen' => $screen,
                'address' => Locations::find($data->location_id ?? 0)->name ?? '',
                'date' => Carbon::now()->timezone('Asia/Karachi')->format('Y-m-d'),
                'time' => Carbon::now()->timezone('Asia/Karachi')->format('H:i:s'),
                'type' => $action,
            ]);
        } catch (\Exception $e) {
            //
        }
    }

    public static function getFDM($location_ids = null)
    {
        $fdo_ids = [];
        $fdm_ids = [];
        if ($location_ids && count($location_ids) > 0) {
            $fdo_phones = Locations::whereIn('id', $location_ids)->pluck('fdo_phone');
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

    public static function patientNameUpdate($phone, $name)
    {
        $accountId = Auth::user()->account_id;
        $patient_phone = GeneralFunctions::cleanNumber($phone);
        Leads::where(['phone' => $patient_phone])->update([
            'name' => $name,
        ]);

        Patients::where([
            'phone' => $patient_phone,
            'user_type_id' => Config::get('constants.patient_id'),
            'account_id' => $accountId,
        ])->update(['name' => $name]);

        Appointments::whereIn('patient_id', function ($query) use ($patient_phone, $accountId) {
            $query->select('id')
                ->from('users')
                ->where([
                    'phone' => $patient_phone,
                    'user_type_id' => Config::get('constants.patient_id'),
                    'account_id' => $accountId,
                ]);
        })->update(['name' => $name]);
    }
    public static function GetPeriods()
    {
        $periods = [
            'today' => [
                'start_date' => Carbon::now()->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
            ],
            'yesterday' => [
                'start_date' => Carbon::now()->subDay(1)->format('Y-m-d'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d'),
            ],
            'last7days' => [
                'start_date' => Carbon::now()->subDay(6)->format('Y-m-d'),
                'end_date' => Carbon::now()->format('Y-m-d'),
            ],
            'week' => [
                'start_date' => Carbon::now()->startOfWeek()->format('Y-m-d'),
                'end_date' => Carbon::now()->endOfWeek()->format('Y-m-d'),
            ],
            'thismonth' => [
                'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d'),
                'end_date' => Carbon::now()->endOfMonth()->format('Y-m-d'),
            ],
            'lastmonth' => [
                'start_date' => Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d'),
                'end_date' => Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d')
            ]
        ];
        return $periods;
    }
    public static function genericfunctionforstaffwiserevenue($packagesadvance)
    {
        $balance = 0;
        $total_balance = 0;
        if (
            ($packagesadvance->cash_flow == 'in' &&
                $packagesadvance->is_adjustment == '0' &&
                $packagesadvance->is_tax == '0' &&
                $packagesadvance->is_cancel == '0'
            )
            ||
            ($packagesadvance->cash_flow == 'out' &&
                $packagesadvance->is_refund == '1'
            )
        ) {
            switch ($packagesadvance->cash_flow) {
                case 'in':
                    $balance = $balance + $packagesadvance->cash_amount;
                    break;
                case 'out':
                    $balance = $balance - $packagesadvance->cash_amount;
                    break;
                default:
                    break;
            }
            $total_balance = $balance;
            if ($packagesadvance->cash_amount != 0) {
                if ($packagesadvance->package_id) {
                    $transtype = Config::get('constants.trans_type.advance_in');
                }
                if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'in') {
                    $transtype = Config::get('constants.trans_type.advance_in');
                }
                if ($packagesadvance->is_adjustment == '1') {
                    $transtype = Config::get('constants.trans_type.adjustment');
                }
                if ($packagesadvance->is_cancel == '1') {
                    $transtype = Config::get('constants.trans_type.invoice_cancel');
                }
                if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'out') {
                    $transtype = Config::get('constants.trans_type.invoice_create');
                }
                if ($packagesadvance->is_refund == '1') {
                    $transtype = Config::get('constants.trans_type.refund_in');
                }
                if ($packagesadvance->is_tax == '1') {
                    $transtype = Config::get('constants.trans_type.tax_out');
                }
                if ($packagesadvance->cash_flow == 'in') {
                    $revenue = $packagesadvance->cash_amount;
                    $refund_out = '';
                } else {
                    $revenue = '';
                    $refund_out = $packagesadvance->cash_amount;
                }
                $report_data = array(
                    'patient' => $packagesadvance->user->name,
                    'phone' => \App\Helpers\GeneralFunctions::prepareNumber4Call($packagesadvance->user->phone),
                    'transtype' => $transtype,
                    'payment_mode_id' => $packagesadvance->payment_mode_id,
                    'cash_flow' => $packagesadvance->cash_flow,
                    'revenue' => $revenue,
                    'refund_out' => $refund_out,
                    'Balance' => $balance,
                    'created_at' => Carbon::parse($packagesadvance->created_at)->format('F j,Y h:i A')
                );

                return $report_data;
            }
        }
    }
    public static function GetConvertedAppointments($period, $periods, $consultant)
    {
        $converted_appointments =  Appointments::with('location:id,name')
            ->leftjoin('package_advances', 'package_advances.appointment_id', '=', 'appointments.id')
            ->where([
                'appointments.base_appointment_status_id' => config('constants.appointment_status_arrived'),
                'appointments.appointment_type_id' => 1
            ])
            ->whereIn('appointments.doctor_id', $consultant)
            ->where('package_advances.cash_amount', '>', 0)
            ->select('appointments.service_id', 'appointments.id')
            ->when($period == 'today', function ($query) use ($periods, $period) {
                $query->whereDate('package_advances.created_at', $periods[$period]['start_date']);
            })
            ->when($period != 'today', function ($query) use ($periods, $period) {
                $query->whereBetween('package_advances.created_at', [
                    $periods[$period]['start_date'],
                    $periods[$period]['end_date']
                ]);
            })
            ->get();

        $total_appointments =  Appointments::with('location:id,name')
            ->where([
                'appointments.base_appointment_status_id' => config('constants.appointment_status_arrived'),
                'appointments.appointment_type_id' => 1
            ])
            ->whereIn('appointments.doctor_id', $consultant)
            ->select('appointments.*')
            ->when($period == 'today', function ($query) use ($periods, $period) {
                $query->whereDate('appointments.scheduled_date', $periods[$period]['start_date']);
            })
            ->when($period != 'today', function ($query) use ($periods, $period) {
                $query->whereBetween('appointments.scheduled_date', [
                    $periods[$period]['start_date'],
                    $periods[$period]['end_date']
                ]);
            })
            ->get();
        $total_appointments->merge($converted_appointments);
        return $total_appointments;
    }
    public static function PatientFollowUpReport($data, $where)
    {
        $center_id = $data['location_id'] ? [$data['location_id']] : ACL::getUserCentres();
        $appointments = Appointments::select('appointments.id', 'appointments.patient_id')
            ->join(DB::raw('(
                SELECT appointment.patient_id, MAX(appointment.created_at) AS created_at
                FROM appointments appointment
                WHERE appointment.appointment_type_id = 1
                    AND appointment.base_appointment_status_id = 2
                    AND appointment.location_id IN (' . implode(',', $center_id) . ')

                GROUP BY appointment.patient_id
            ) latest_appointments'), function ($join) {
                $join->on('appointments.patient_id', '=', 'latest_appointments.patient_id')
                    ->on('appointments.created_at', '=', 'latest_appointments.created_at');
            })
            ->orderByDesc('appointments.id')
            ->pluck('patient_id');


        $cashReceivedAmounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS cash_receive'))
            ->where([
                'cash_flow' => 'in',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',
                'is_refund' => '0',
            ])
            ->whereIn('patient_id', $appointments)
            ->groupBy('patient_id')
            ->pluck('cash_receive', 'patient_id');

        $settleAmounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS settle_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',

            ])
            ->whereIn('patient_id', $appointments)
            ->groupBy('patient_id')
            ->pluck('settle_amount', 'patient_id');

        $settleTaxAmounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS settle_tax_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '1',
                'is_adjustment' => '0',

            ])
            ->whereIn('patient_id', $appointments)
            ->groupBy('patient_id')
            ->pluck('settle_tax_amount', 'patient_id');

        $plans_check = PackageAdvances::select('package_advances.id', 'package_advances.patient_id', 'package_advances.created_at', 'package_advances.location_id')
            ->whereIn('package_advances.patient_id', $appointments)
            ->whereIn('package_advances.location_id', $center_id)
            ->where($where)
            ->groupBy('package_advances.patient_id')
            ->orderBy('package_advances.patient_id', 'DESC')
            ->get();
        $plans_check = $plans_check->map(function ($item) use ($cashReceivedAmounts, $settleAmounts, $settleTaxAmounts) {
            $item->cash_receive = $cashReceivedAmounts[$item->patient_id] ?? null;
            $item->settle_amount = $settleAmounts[$item->patient_id] ?? null;
            $item->settle_tax_amount = $settleTaxAmounts[$item->patient_id] ?? null;
            return $item;
        });

        $not_treatment = [];
        $is_treatment = [];
        $patient_data = [];
        $plan_check_no_treatment = collect($plans_check)->where('cash_receive', '>', 0)
            ->where('created_at', '<', Carbon::now()->subDays(7))
            ->pluck('patient_id')->toArray();
        foreach ($plans_check as $data) {
            $treatments = Appointments::where([
                'appointment_type_id' => Config::get('constants.appointment_type_service'),
                'patient_id' => $data['patient_id'],
            ])
                ->whereIn('location_id', ACL::getUserCentres())
                ->get();

            $patient = Patients::where(['id' => $data['patient_id'], 'user_type_id' => 3, 'active' => 1])->first();
            $data['patient_id'] = $patient->id;
            $data['name'] = $patient->name;
            $data['phone'] = $patient->phone;
            $data['settle_amount_with_tax'] = $data['settle_amount'] + $data['settle_tax_amount'];
            if (count($treatments) > 0) {
                $has_treatment_with_status_2 = collect($treatments)->contains('base_appointment_status_id', 2);
                $check_treatments = collect($treatments)->sortByDesc('id')->first();
                $future_treatments = collect($treatments)->Where('scheduled_date', '>', Carbon::now()->format('Y-m-d'));
                if (!$has_treatment_with_status_2 && $check_treatments->scheduled_date <= Carbon::now()->subDays(1)->format('Y-m-d') && $future_treatments->isEmpty() && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 0) {
                    $data['is_treatment'] = 1;
                    array_push($is_treatment, $data);
                }
            } else {
                if (in_array($data['patient_id'], $plan_check_no_treatment) && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 0) {
                    $data['is_treatment'] = 0;
                    array_push($not_treatment, $data);
                }
            }
        }
        $patient_data = array_merge($is_treatment, $not_treatment);
        usort($patient_data, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        return $patient_data;
    }
    public static function LoadPatientFollowUpReportMonthly($data, $where)
    {

        $center_id = $data['location_id'] ? [$data['location_id']] : ACL::getUserCentres();
        $patient_ids = Appointments::select('appointments.id', 'appointments.patient_id')
            ->join(DB::raw('(
                SELECT appointment.patient_id, MAX(appointment.created_at) AS created_at
                FROM appointments appointment
                WHERE appointment.appointment_type_id = 1
                    AND appointment.base_appointment_status_id = 2
                    AND appointment.location_id IN (' . implode(',', $center_id) . ')
                GROUP BY appointment.patient_id
            ) latest_appointments'), function ($join) {
                $join->on('appointments.patient_id', '=', 'latest_appointments.patient_id')
                    ->on('appointments.created_at', '=', 'latest_appointments.created_at');
            })
            ->orderByDesc('appointments.id')
            ->pluck('patient_id');

        $cash_received_amounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS cash_receive'))
            ->where([
                'cash_flow' => 'in',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',
                'is_refund' => '0',
            ])
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->pluck('cash_receive', 'patient_id');

        $settle_amounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS settle_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',
            ])
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->pluck('settle_amount', 'patient_id');

        $settle_tax_amounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS settle_tax_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '1',
                'is_adjustment' => '0',
            ])
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->pluck('settle_tax_amount', 'patient_id');

        $plans_check = PackageAdvances::select('id', 'patient_id', 'created_at', 'location_id')
            ->whereIn('patient_id', $patient_ids)
            ->whereIn('location_id', $center_id)
            ->groupBy('patient_id')
            ->orderBy('patient_id', 'DESC')
            ->get();
        $plans_check = $plans_check->map(function ($item) use ($cash_received_amounts, $settle_amounts, $settle_tax_amounts) {
            $item->cash_receive = $cash_received_amounts[$item->patient_id] ?? null;
            $item->settle_amount = $settle_amounts[$item->patient_id] ?? null;
            $item->settle_tax_amount = $settle_tax_amounts[$item->patient_id] ?? null;
            return $item;
        });
        $patient_data = [];
        $plan_check_amount = collect($plans_check)->where('cash_receive', '>', 0)->where('created_at', '<', Carbon::now()->subDays(7))->pluck('patient_id')->toArray();

        foreach ($plans_check as $data) {
            $treatments = Appointments::where([
                'appointment_type_id' => Config::get('constants.appointment_type_service'),
                'patient_id' => $data['patient_id'],
            ])
                ->whereIn('location_id', $center_id)
                ->where($where)
                ->get();

            $patient = Patients::where(['id' => $data['patient_id'], 'user_type_id' => 3, 'active' => 1])->first();
            $data['patient_id'] = $patient->id;
            $data['name'] = $patient->name;
            $data['phone'] = $patient->phone;

            $data['settle_amount_with_tax'] = $data['settle_amount'] + $data['settle_tax_amount'];

            if (count($treatments) > 0) {
                $has_treatment_with_status_2 = collect($treatments)->contains('base_appointment_status_id', 2);
                $check_treatments = collect($treatments)->sortByDesc('id')->first();
                $future_treatments = Appointments::where([
                    'appointment_type_id' => Config::get('constants.appointment_type_service'),
                    'patient_id' => $data['patient_id'],
                ])
                    ->whereIn('location_id', $center_id)
                    ->Where('scheduled_date', '>=', Carbon::now()->format('Y-m-d'))
                    ->get();
                if ($has_treatment_with_status_2 && $check_treatments->base_appointment_status_id != 1 && $check_treatments->scheduled_date <= Carbon::now()->subDays(31)->format('Y-m-d') && $future_treatments->isEmpty()) {
                    if (in_array($data['patient_id'], $plan_check_amount) && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 0) {
                        $data['is_treatment'] = 1;
                        $data['scheduled_date'] = $check_treatments->scheduled_date ;
                        array_push($patient_data, $data);
                    }
                }
            }
        }
        usort($patient_data, function ($a, $b) {
            return strtotime($b['scheduled_date']) - strtotime($a['scheduled_date']);
        });
        return $patient_data;

    }
}
