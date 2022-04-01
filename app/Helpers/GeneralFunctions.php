<?php
/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM.
 */

namespace App\Helpers;

use App\Models\Appointments;
use App\Models\Services;
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
            // Adjust Country Code for Pakistan
            if ($phoneNumber[0] == '3' && strlen($phoneNumber) == 10 && $type = 0) {
                return '+92' . $phoneNumber;
            } elseif ($phoneNumber[0] == '3' && strlen($phoneNumber) == 10 && $type = 1) {
                return '0' . $phoneNumber;
            } else {
                return $phoneNumber;
            }
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

        if ($total > 0) {
            $filename = 'services';
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);

            if (hasFilter($filters, 'name')) {
                $where[] = [
                    'name',
                    'like',
                    '%' . $filters['name'] . '%',
                ];
                Filters::put(Auth::user()->id, $filename, 'name', $filters['name']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'name');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'name')) {
                        $where[] = [
                            'name',
                            'like',
                            '%' . Filters::get(Auth::user()->id, $filename, 'name') . '%',
                        ];
                    }
                }
            }
            if (hasFilter($filters, 'status')) {
                $where[] = [
                    'active',
                    '=',
                    $filters['status'],
                ];
                Filters::put(Auth::user()->id, $filename, 'status', $filters['status']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::user()->id, $filename, 'status');
                } else {
                    if (Filters::get(Auth::user()->id, $filename, 'status') == 0 || Filters::get(Auth::user()->id, $filename, 'status') == 1) {
                        if (Filters::get(Auth::user()->id, $filename, 'status') != null) {
                            $where[] = [
                                'active',
                                '=',
                                Filters::get(Auth::user()->id, $filename, 'status'),
                            ];
                        }
                    }
                }
            }
        }

        $allService = Services::where('parent_id', 0)
            ->where('slug', 'all')
            ->first();

        if (count($where) > 0) {
            $allService = null;
        }

        $query = Services::with('children')
            //->where('parent_id', 0)
            ->where('slug', '!=', 'all')
            ->when(isset($where) && count($where) > 0, fn($q) => $q->where($where));

        $services = $query->get();

        $mergedServices = [];
        foreach ($services as $key => $service) {

            $children = collect($service->children)->flatten();
            unset($service->children);

            if ($key === 0 && $allService) {
                $mergedServices[] = !is_null($allService) ? $allService->toArray() : [];
            }

            $mergedServices[] = $service->toArray();
            $children = $children->toArray();
            foreach ($children as $child) {
                $mergedServices[] = $child;
            }

        }

        return $mergedServices;
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

}
