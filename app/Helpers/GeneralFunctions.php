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
use App\Models\Stock;
use App\Models\Patients;
use App\Models\Services;
use App\Models\Locations;
use App\Models\Appointments;
use App\Models\AppointmentLog;
use Illuminate\Support\Carbon;
use App\HelperModule\ApiHelper;
use App\Models\Activity;
use App\Models\Inventory;
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
            if (Gate::allows('view_inactive_services')) {
                $services = Services::where('slug', '!=', 'all')
                    ->where(['parent_id' => 0])
                    ->orderBy('id', 'asc')
                    ->get();
            } else {
                $services = Services::where('slug', '!=', 'all')
                    ->where(['parent_id' => 0])
                    ->where(['active' => 1])
                    ->orderBy('id', 'asc')
                    ->get();
            }
            $mergedServices = [];
            foreach ($services as $service) {
                // Check if parent matches the name filter
                $parentMatches = !hasFilter($filters, 'name') || stripos($service->name, $filters['name']) !== false;

                if ($parentMatches) {
                    // Parent matches: get ALL children (only filter by status, not by name)
                    if (Gate::allows('view_inactive_services')) {
                        $children = Services::where(['parent_id' => $service->id])
                            ->when(hasFilter($filters, 'status'), fn ($q) => $q->where(['active' => $filters['status']]))
                            ->orderBy('sort_number', 'asc')
                            ->get()->toArray();
                    } else {
                        $children = Services::where(['parent_id' => $service->id, 'active' => 1])
                            ->when(hasFilter($filters, 'status'), fn ($q) => $q->where(['active' => $filters['status']]))
                            ->orderBy('sort_number', 'asc')
                            ->get()->toArray();
                    }

                    $mergedServices[] = $service->toArray();
                    foreach ($children as $child) {
                        $mergedServices[] = $child;
                    }
                } else {
                    // Parent doesn't match: get children that match the name filter
                    if (Gate::allows('view_inactive_services')) {
                        $children = Services::where(['parent_id' => $service->id])
                            ->where('name', 'like', '%' . $filters['name'] . '%')
                            ->when(hasFilter($filters, 'status'), fn ($q) => $q->where(['active' => $filters['status']]))
                            ->orderBy('sort_number', 'asc')
                            ->get()->toArray();
                    } else {
                        $children = Services::where(['parent_id' => $service->id, 'active' => 1])
                            ->where('name', 'like', '%' . $filters['name'] . '%')
                            ->when(hasFilter($filters, 'status'), fn ($q) => $q->where(['active' => $filters['status']]))
                            ->orderBy('sort_number', 'asc')
                            ->get()->toArray();
                    }

                    // Only include parent if it has matching children
                    if (count($children) > 0) {
                        $mergedServices[] = $service->toArray();
                        foreach ($children as $child) {
                            $mergedServices[] = $child;
                        }
                    }
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
                //->where('slug', '!=', 'all')
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
    public static function ServicesTreeMachineType()
    {


        $filename = 'services';

        $query = Services::with(['children' => function ($q) {
            $q->where('active', 1)->orderBy('name');
        }])
            ->where(['parent_id' => 0])
            ->where('slug', '!=', 'all');
        $services = $query->get();
        $mergedServices = [];
        foreach ($services as $key => $service) {

            $children = Services::where(['parent_id' => $service->id, 'active' => 1])->orderBy('name')->get();

            $mergedServices[] = $service->toArray();
            $children = $children->toArray();
            foreach ($children as $child) {
                $mergedServices[] = $child;
            }
        }

        return $mergedServices;
    }
    public static function ServicesTreeList($request = null, $total = 0, $id = null)
    {
        $where = [];
        if ($total >= 0 && $id == null) {

            try {
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
                            //->where('active', 1)
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
                    $q->where('active', 1)->orderBy('name');
                }])
                    ->where(['parent_id' => 0])
                    ->where('slug', '!=', 'all')
                    ->when(isset($where) && count($where) > 0, fn ($q) => $q->where($where));
                $services = $query->get()->toArray();

                $allserviceslug = Services::where(['slug' => 'all'])->first();
                if ($allserviceslug) {
                    $allserviceslug = $allserviceslug->toArray();
                }
                array_unshift($services, $allserviceslug);

                return $services;
            } catch (\Exception $e) {
                return false;
            }
        } else {

            try {
                $query = Services::with(['children' => function ($q) {
                    $q->where('active', 1)->orderBy('name');
                }])
                    ->where(['id' => $id, 'parent_id' => 0])
                    ->where('slug', '!=', 'all');
                $services[] = $query->first()->toArray();
                $allserviceslug = Services::where(['slug' => 'all'])->first()->toArray();
                array_unshift($services, $allserviceslug);

                return $services;
            } catch (\Exception $e) {
                return false;
            }
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
        $centerIdsStr = implode(',', array_map('intval', $center_id));
        $sevenDaysAgo = Carbon::now()->subDays(7)->format('Y-m-d H:i:s');
        $today = Carbon::now()->format('Y-m-d');
        
        // Get arrived and converted appointment status IDs
        $arrivedStatus = \App\Models\AppointmentStatuses::where(['account_id' => Auth::User()->account_id, 'is_arrived' => 1])->first();
        $convertedStatus = \App\Models\AppointmentStatuses::where(['account_id' => Auth::User()->account_id, 'is_converted' => 1])->first();
        $arrivedStatusId = $arrivedStatus ? $arrivedStatus->id : 2;
        $convertedStatusId = $convertedStatus ? $convertedStatus->id : null;
        $statusCondition = $convertedStatusId 
            ? "base_appointment_status_id IN ({$arrivedStatusId}, {$convertedStatusId})"
            : "base_appointment_status_id = {$arrivedStatusId}";

        // Optimized single query approach - patients with NO treatment appointments
        $sqlNoTreatment = "
            SELECT 
                u.id as patient_id,
                u.name,
                u.phone,
                bal.cash_in as cash_receive,
                bal.cash_out as settle_amount_with_tax,
                bal.conversion_date as created_at,
                bal.location_id,
                0 as is_treatment
            FROM users u
            INNER JOIN (
                SELECT DISTINCT patient_id
                FROM appointments
                WHERE appointment_type_id = 1 
                    AND {$statusCondition}
                    AND location_id IN ({$centerIdsStr})
            ) apt ON u.id = apt.patient_id
            INNER JOIN (
                SELECT 
                    patient_id,
                    COALESCE(SUM(CASE WHEN cash_flow = 'in' AND is_cancel = 0 AND is_tax = 0 AND is_adjustment = 0 AND is_refund = 0 THEN cash_amount ELSE 0 END), 0) as cash_in,
                    COALESCE(SUM(CASE WHEN cash_flow = 'out' AND is_cancel = 0 AND is_adjustment = 0 AND is_refund = 0 THEN cash_amount ELSE 0 END), 0) as cash_out,
                    MIN(CASE WHEN cash_flow = 'in' AND cash_amount > 0 AND is_tax = 0 THEN created_at END) as conversion_date,
                    MIN(location_id) as location_id
                FROM package_advances
                GROUP BY patient_id
                HAVING (cash_in - cash_out) > 100
            ) bal ON u.id = bal.patient_id
            WHERE u.user_type_id = 3 AND u.active = 1
                AND bal.conversion_date IS NOT NULL
                AND bal.conversion_date <= ?
                AND NOT EXISTS (
                    SELECT 1 FROM appointments t 
                    WHERE t.patient_id = u.id 
                    AND t.appointment_type_id = 2
                    AND t.location_id IN ({$centerIdsStr})
                )
            ORDER BY bal.conversion_date DESC
        ";

        $patientsNoTreatment = DB::select($sqlNoTreatment, [$sevenDaysAgo]);
        
        $patient_data = [];
        foreach ($patientsNoTreatment as $p) {
            $patient_data[] = [
                'patient_id' => $p->patient_id,
                'name' => $p->name,
                'phone' => $p->phone,
                'cash_receive' => (float) $p->cash_receive,
                'settle_amount_with_tax' => (float) $p->settle_amount_with_tax,
                'created_at' => $p->created_at,
                'location_id' => $p->location_id,
                'is_treatment' => 0,
            ];
        }

        return $patient_data;
    }
    public static function LoadPatientFollowUpReportMonthly($data, $where)
    {
        $center_id = $data['location_id'] ? [$data['location_id']] : ACL::getUserCentres();
        $centerIdsStr = implode(',', array_map('intval', $center_id));
        $thirtyOneDaysAgo = Carbon::now()->subDays(31)->format('Y-m-d');
        $today = Carbon::now()->format('Y-m-d');

        // Optimized single query approach - patients with overdue treatments
        // Criteria:
        // 1. Has treatment appointments (appointment_type_id = 2) that arrived (status = 2)
        // 2. Last treatment >= 31 days ago
        // 3. No future treatments scheduled
        // 4. Balance > 500
        $sql = "
            SELECT 
                u.id as patient_id,
                u.name,
                u.phone,
                apt.last_arrived as scheduled_date,
                bal.cash_in as cash_receive,
                bal.cash_out as settle_amount_with_tax,
                bal.location_id,
                1 as is_treatment
            FROM users u
            INNER JOIN (
                SELECT patient_id, MAX(scheduled_date) as last_arrived
                FROM appointments
                WHERE appointment_type_id = 2
                    AND base_appointment_status_id = 2 
                    AND location_id IN ({$centerIdsStr})
                GROUP BY patient_id
                HAVING MAX(scheduled_date) <= ?
            ) apt ON u.id = apt.patient_id
            INNER JOIN (
                SELECT 
                    patient_id,
                    COALESCE(SUM(CASE WHEN cash_flow = 'in' AND is_cancel = 0 AND is_tax = 0 AND is_adjustment = 0 AND is_refund = 0 THEN cash_amount ELSE 0 END), 0) as cash_in,
                    COALESCE(SUM(CASE WHEN cash_flow = 'out' AND is_cancel = 0 AND is_adjustment = 0 AND is_refund = 0 THEN cash_amount ELSE 0 END), 0) as cash_out,
                    MIN(location_id) as location_id
                FROM package_advances
                GROUP BY patient_id
                HAVING (cash_in - cash_out) > 100
            ) bal ON u.id = bal.patient_id
            WHERE u.user_type_id = 3 AND u.active = 1
                AND NOT EXISTS (
                    SELECT 1 FROM appointments f 
                    WHERE f.patient_id = u.id 
                    AND f.appointment_type_id = 2
                    AND f.scheduled_date >= ?
                    AND f.location_id IN ({$centerIdsStr})
                )
            ORDER BY apt.last_arrived DESC
        ";

        $patients = DB::select($sql, [$thirtyOneDaysAgo, $today]);
        
        $patient_data = [];
        foreach ($patients as $p) {
            $patient_data[] = [
                'patient_id' => $p->patient_id,
                'name' => $p->name,
                'phone' => $p->phone,
                'cash_receive' => (float) $p->cash_receive,
                'settle_amount_with_tax' => (float) $p->settle_amount_with_tax,
                'scheduled_date' => $p->scheduled_date,
                'location_id' => $p->location_id,
                'is_treatment' => 1,
            ];
        }

        return $patient_data;
    }


    public static function stockCheck($id)
    {
        $count_product_in_quantity = Stock::where('stock_type', 'in')->where('product_id', $id)->sum('quantity');
        $count_product_out_quantity = Stock::where('stock_type', 'out')->where('product_id', $id)->sum('quantity');
        $stock_quantity = $count_product_in_quantity - $count_product_out_quantity;
        $stock_available = ($stock_quantity > 0) ? true : false;

        return [
            'stock_quantity' => $stock_quantity,
            'stock_available' => $stock_available,
        ];
    }
    public static function inventoryCheck($request)
    {
        if ($request->from_location_id) {
            $count_product_in_quantity = Inventory::where(['product_id' => $request->product_id, 'location_id' => $request->from_location_id])->first();
        } else {
            $count_product_in_quantity = Inventory::where(['product_id' => $request->product_id, 'warehouse_id' => $request->from_warehouse_id])->first();
        }

        return $count_product_in_quantity->quantity;
    }
    public static function stockC($id)
    {
        $count_product_in_quantity = Stock::where('stock_type', 'in')->where('product_id', $id)->sum('quantity');
        $count_product_out_quantity = Stock::where('stock_type', 'out')->where('product_id', $id)->sum('quantity');
        $stock_quantity = $count_product_in_quantity - $count_product_out_quantity;

        return $stock_quantity;
    }
    public static function saveActivityLogs($action, $activityType, $data, $appointment_id)
    {

        try {
            $location = Locations::find($data['location_id']);
            $service = Services::find($data['service_id']);
            $patient = Patients::find($data['patient_id']);

            Activity::create([
                'created_by' => auth()->id(),
                'user_id' => auth()->id(),
                'action' => $action,
                'appointment_type' => $activityType,
                'appointment_id' => $appointment_id,
                'activity_type' => $activityType,
                'location' => $location ? $location->name : '',
                'centre_id' => $location ? $location->id : NULL,
                'service_id' => $service ? $service->id : NULL,
                'service' => $service ? $service->name : NULL,
                'patient_id' => $patient ? $patient->id : NULL,
                'patient' => $patient ? $patient->name : NULL,
                'schedule_date' => $data['scheduled_date'],
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),

            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
