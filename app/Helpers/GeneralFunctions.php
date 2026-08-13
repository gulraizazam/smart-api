<?php

declare(strict_types=1);
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
use App\Models\Activity;
use App\Models\Inventory;
use App\Models\PackageAdvances;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class GeneralFunctions
{
    public static function cleanNumber(string|int|null $phoneNumber): string
    {
        return \App\Services\Phone\PhoneFormattingService::cleanNumber($phoneNumber);
    }

    private static function cleanCountryCodes(string|int|null $phoneNumber): string
    {
        return \App\Services\Phone\PhoneFormattingService::cleanNumber($phoneNumber);
    }

    public static function prepareNumber(string $phoneNumber): string
    {
        return \App\Services\Phone\PhoneFormattingService::prepareNumber($phoneNumber);
    }

    public static function prepareNumber4Call(string $phoneNumber, int $type = 0): string
    {
        return \App\Services\Phone\PhoneFormattingService::prepareNumber4Call($phoneNumber, $type);
    }

    public static function prepareNumber4CallSMS(string $phoneNumber): string
    {
        return \App\Services\Phone\PhoneFormattingService::prepareNumber4CallSMS($phoneNumber);
    }

    public static function contactStatus(?string $contact): string
    {
        return \App\Services\PatientManagement\PatientSearchService::contactStatus($contact);
    }

    public static function patientSearch(string|int $id): string|int
    {
        return \App\Services\PatientManagement\PatientSearchService::patientSearch($id);
    }

    public static function patientSearchStringAdd(string|int $id): string
    {
        return \App\Services\PatientManagement\PatientSearchService::patientSearchStringAdd($id);
    }

    public static function clearnString(string $string): string
    {
        return \App\Services\Phone\PhoneFormattingService::clearnString($string);
    }

    public static function servicesList(?\Illuminate\Http\Request $request = null, int $total = 0): ?array
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
                        Filters::forget(Auth::user()->id, $filename, 'name');
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
            if (Gate::allows('services.list.view_inactive')) {
                $services = Services::where('slug', '!=', 'all')
                    ->whereNull('parent_id')
                    ->orderBy('id', 'asc')
                    ->get();
            } else {
                $services = Services::where('slug', '!=', 'all')
                    ->whereNull('parent_id')
                    ->where(['active' => 1])
                    ->orderBy('id', 'asc')
                    ->get();
            }
            $mergedServices = [];
            foreach ($services as $service) {
                // Check if parent matches the name filter
                $parentMatches = !hasFilter($filters, 'name') || str_contains(strtolower($service->name), strtolower($filters['name']));

                if ($parentMatches) {
                    // Parent matches: get ALL children (only filter by status, not by name)
                    if (Gate::allows('services.list.view_inactive')) {
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
                    if (Gate::allows('services.list.view_inactive')) {
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
                    if (!empty($children)) {
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

    public static function ServicesTree(?\Illuminate\Http\Request $request = null, int $total = 0): array|\Illuminate\Database\Eloquent\Collection|null
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
                        Filters::forget(Auth::user()->id, $filename, 'name');
                    } else {
                        if (Filters::get(Auth::user()->id, $filename, 'name')) {
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
                    if (!empty($services)) {
                        $mergedServices = [];
                        foreach ($services as $key => $service) {
                            // $service is already loaded from the parent query — no need to re-fetch.
                            if ($service->parent_id == '0') {
                                if (Gate::allows('services.list.view_inactive')) {
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
                    if (!empty($services)) {
                        $mergedServices = [];
                        foreach ($services as $key => $service) {
                            // $service is already loaded from the parent query — no need to re-fetch.
                            if ($service->parent_id == '0') {
                                if (Gate::allows('services.list.view_inactive')) {
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
                        if (Gate::allows('services.list.view_inactive')) {
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
                        if (Gate::allows('services.list.view_inactive')) {
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
                        ->when(isset($where) && !empty($where), fn ($q) => $q->where($where));
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
                ->when(isset($where) && !empty($where), fn ($q) => $q->where($where));
            $services = $query->get();
            $mergedServices = [];
            foreach ($services as $key => $service) {
                if (Gate::allows('services.list.view_inactive')) {
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
    public static function ServicesTreeMachineType(): array
    {
        $accountId = Auth::user()->account_id;

        $services = Services::where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            })
            ->where('account_id', $accountId)
            ->where('slug', '!=', 'all')
            ->orderBy('name')
            ->get();

        $mergedServices = [];
        foreach ($services as $service) {
            $children = Services::where('parent_id', $service->id)
                ->where('account_id', $accountId)
                ->where('active', 1)
                ->orderBy('name')
                ->get();

            $mergedServices[] = $service->toArray();
            foreach ($children->toArray() as $child) {
                $mergedServices[] = $child;
            }
        }

        return $mergedServices;
    }
    public static function ServicesTreeList(?\Illuminate\Http\Request $request = null, int $total = 0, ?int $id = null): array|false|null
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
                            Filters::forget(Auth::user()->id, $filename, 'name');
                        } else {
                            if (Filters::get(Auth::user()->id, $filename, 'name')) {
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
                        if (!empty($services)) {
                            $mergedServices = [];
                            foreach ($services as $key => $service) {
                                // $service is already loaded from the parent query — no need to re-fetch.
                                if ($service->parent_id == '0') {
                                    if (Gate::allows('services.list.view_inactive')) {
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
                        if (!empty($services)) {
                            $mergedServices = [];
                            foreach ($services as $key => $service) {
                                // $service is already loaded from the parent query — no need to re-fetch.
                                if ($service->parent_id == '0') {
                                    if (Gate::allows('services.list.view_inactive')) {
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
                            if (Gate::allows('services.list.view_inactive')) {
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
                            if (Gate::allows('services.list.view_inactive')) {
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
                            ->when(isset($where) && !empty($where), fn ($q) => $q->where($where));
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
                // Root services are stored with parent_id = NULL (legacy data may use 0).
                $query = Services::with(['children' => function ($q) {
                    $q->where('active', 1)->orderBy('name');
                }])
                    ->where(function ($q) {
                        $q->whereNull('parent_id')->orWhere('parent_id', 0);
                    })
                    ->where('slug', '!=', 'all')
                    ->when(isset($where) && !empty($where), fn ($q) => $q->where($where));
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
                // Root services are stored with parent_id = NULL (legacy data may use 0).
                $query = Services::with(['children' => function ($q) {
                    $q->where('active', 1)->orderBy('name');
                }])
                    ->where('id', $id)
                    ->where(function ($q) {
                        $q->whereNull('parent_id')->orWhere('parent_id', 0);
                    })
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

    public static function getServiceId(string $service_id): string
    {
        if (str_contains($service_id, 'bold-')) {
            return str_replace('bold-', '', $service_id);
        }

        return $service_id;
    }

    private static function appendAllService(): array
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

    public static function parentServices(): \Illuminate\Database\Eloquent\Collection
    {
        return Services::where(function ($q) {
            $q->whereNull('parent_id')->orWhere('parent_id', 0);
        })->where('slug', '!=', 'all')->get(['id', 'name']);
    }

    public static function smsTemplateVariables(string $slug): array
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

    public static function saveAppointmentLogs(string $action, string $screen, mixed $data): void
    {
        \App\Helpers\ActivityLogger::saveAppointmentLogs($action, $screen, $data);
    }

    public static function getFDM(?array $location_ids = null): array
    {
        return \App\Services\Location\LocationService::getFDM($location_ids);
    }

    public static function getCSR(): array
    {
        return \App\Services\Location\LocationService::getCSR();
    }

    public static function getLocationIds(mixed $location_id): ?array
    {
        return \App\Services\Location\LocationService::getLocationIds($location_id);
    }

    public static function patientNameUpdate(string $phone, string $name): void
    {
        \App\Services\PatientManagement\PatientSearchService::patientNameUpdate($phone, $name);
    }

    public static function genericfunctionforstaffwiserevenue(PackageAdvances $packagesadvance): ?array
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
            $balance += match ($packagesadvance->cash_flow) {
                'in' => $packagesadvance->cash_amount,
                'out' => -$packagesadvance->cash_amount,
                default => 0,
            };
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

        return null;
    }

    public static function PatientFollowUpReport(array $data, array $where): array
    {
        $rawCentres = $data['location_id'] ?? null;
        $center_id = !empty($rawCentres) ? (array) $rawCentres : ACL::getUserCentres();
        $centerIds = array_values(array_filter(array_map('intval', $center_id), fn (int $id): bool => $id > 0));
        if (empty($centerIds)) {
            $centerIds = array_map('intval', ACL::getUserCentres());
        }
        $centerPlaceholders = implode(',', array_fill(0, count($centerIds), '?'));
        $sevenDaysAgo = Carbon::now()->subDays(7)->format('Y-m-d H:i:s');
        $today = Carbon::now()->format('Y-m-d');

        // Optional caller-supplied filters layered on top of the hard
        // 7-days-ago cutoff: a search term (name / phone partial match)
        // and a date range narrowing conversion_date.
        $search = isset($data['search']) ? trim((string) $data['search']) : '';
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;

        // Get arrived and converted appointment status IDs
        $arrivedStatus = \App\Models\AppointmentStatuses::where(['account_id' => Auth::user()->account_id, 'is_arrived' => 1])->first();
        $convertedStatus = \App\Models\AppointmentStatuses::where(['account_id' => Auth::user()->account_id, 'is_converted' => 1])->first();
        $arrivedStatusId = (int) ($arrivedStatus ? $arrivedStatus->id : 2);
        $convertedStatusId = $convertedStatus ? (int) $convertedStatus->id : null;

        if ($convertedStatusId) {
            $statusCondition = 'base_appointment_status_id IN (?, ?)';
            $statusBindings = [$arrivedStatusId, $convertedStatusId];
        } else {
            $statusCondition = 'base_appointment_status_id = ?';
            $statusBindings = [$arrivedStatusId];
        }

        // Narrow the package_advances aggregation to only patients that have
        // a qualifying consultation in the selected centres. Without this
        // the `bal` subquery groups over every row in package_advances and
        // dominates the runtime.
        $patientIdSubquery = "
            SELECT DISTINCT patient_id
            FROM appointments
            WHERE appointment_type_id = 1
                AND {$statusCondition}
                AND location_id IN ({$centerPlaceholders})
        ";

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
            INNER JOIN ({$patientIdSubquery}) apt ON u.id = apt.patient_id
            INNER JOIN (
                SELECT
                    patient_id,
                    COALESCE(SUM(CASE WHEN cash_flow = 'in' AND is_cancel = 0 AND is_tax = 0 AND is_adjustment = 0 AND is_refund = 0 THEN cash_amount ELSE 0 END), 0) as cash_in,
                    COALESCE(SUM(CASE WHEN cash_flow = 'out' AND is_cancel = 0 AND is_adjustment = 0 AND is_refund = 0 THEN cash_amount ELSE 0 END), 0) as cash_out,
                    MIN(CASE WHEN cash_flow = 'in' AND cash_amount > 0 AND is_tax = 0 THEN created_at END) as conversion_date,
                    MIN(location_id) as location_id
                FROM package_advances
                WHERE patient_id IN ({$patientIdSubquery})
                GROUP BY patient_id
                HAVING (cash_in - cash_out) > 100
            ) bal ON u.id = bal.patient_id
            WHERE u.user_type_id = 3 AND u.active = 1
                AND bal.conversion_date IS NOT NULL
                AND bal.conversion_date <= ?
                __SEARCH_CLAUSE__
                __DATE_CLAUSE__
                AND NOT EXISTS (
                    SELECT 1 FROM appointments t
                    WHERE t.patient_id = u.id
                    AND t.appointment_type_id = 2
                    AND t.location_id IN ({$centerPlaceholders})
                )
            ORDER BY bal.conversion_date DESC
            LIMIT 5000
        ";

        // Bindings repeat the apt-subquery args twice (apt INNER JOIN + bal WHERE IN),
        // then date, then centres for the NOT EXISTS treatment lookup.
        $aptBindings = array_merge($statusBindings, $centerIds);
        $bindings = array_merge($aptBindings, $aptBindings, [$sevenDaysAgo]);

        $searchClause = '';
        if ($search !== '') {
            $searchClause = "AND (u.name LIKE ? OR u.phone LIKE ?)";
            $like = '%'.$search.'%';
            $bindings[] = $like;
            $bindings[] = $like;
        }
        $dateClause = '';
        if ($startDate) {
            $dateClause .= ' AND bal.conversion_date >= ?';
            $bindings[] = $startDate.' 00:00:00';
        }
        if ($endDate) {
            $dateClause .= ' AND bal.conversion_date <= ?';
            $bindings[] = $endDate.' 23:59:59';
        }

        $sqlNoTreatment = strtr($sqlNoTreatment, [
            '__SEARCH_CLAUSE__' => $searchClause,
            '__DATE_CLAUSE__' => $dateClause,
        ]);

        $bindings = array_merge($bindings, $centerIds);
        $patientsNoTreatment = DB::select($sqlNoTreatment, $bindings);
        
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
    public static function LoadPatientFollowUpReportMonthly(array $data, array $where): array
    {
        $rawCentres = $data['location_id'] ?? null;
        $center_id = !empty($rawCentres) ? (array) $rawCentres : ACL::getUserCentres();
        $centerIds = array_values(array_filter(array_map('intval', $center_id), fn (int $id): bool => $id > 0));
        if (empty($centerIds)) {
            $centerIds = array_map('intval', ACL::getUserCentres());
        }
        $centerPlaceholders = implode(',', array_fill(0, count($centerIds), '?'));
        $thirtyOneDaysAgo = Carbon::now()->subDays(31)->format('Y-m-d');
        $today = Carbon::now()->format('Y-m-d');

        $search = isset($data['search']) ? trim((string) $data['search']) : '';
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;

        // Same optimisation as PatientFollowUpReport — restrict the
        // package_advances aggregation to only patients with qualifying
        // treatments in the selected centres. Without this `bal` groups
        // the entire table.
        $patientIdSubquery = "
            SELECT DISTINCT patient_id
            FROM appointments
            WHERE appointment_type_id = 2
                AND base_appointment_status_id = 2
                AND location_id IN ({$centerPlaceholders})
        ";

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
                    AND location_id IN ({$centerPlaceholders})
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
                WHERE patient_id IN ({$patientIdSubquery})
                GROUP BY patient_id
                HAVING (cash_in - cash_out) > 100
            ) bal ON u.id = bal.patient_id
            WHERE u.user_type_id = 3 AND u.active = 1
                __SEARCH_CLAUSE__
                __DATE_CLAUSE__
                AND NOT EXISTS (
                    SELECT 1 FROM appointments f
                    WHERE f.patient_id = u.id
                    AND f.appointment_type_id = 2
                    AND f.scheduled_date >= ?
                    AND f.location_id IN ({$centerPlaceholders})
                )
            ORDER BY apt.last_arrived DESC
            LIMIT 5000
        ";

        $bindings = array_merge($centerIds, [$thirtyOneDaysAgo], $centerIds);

        $searchClause = '';
        if ($search !== '') {
            $searchClause = "AND (u.name LIKE ? OR u.phone LIKE ?)";
            $like = '%'.$search.'%';
            $bindings[] = $like;
            $bindings[] = $like;
        }
        $dateClause = '';
        if ($startDate) {
            $dateClause .= ' AND apt.last_arrived >= ?';
            $bindings[] = $startDate;
        }
        if ($endDate) {
            $dateClause .= ' AND apt.last_arrived <= ?';
            $bindings[] = $endDate;
        }
        $sql = strtr($sql, [
            '__SEARCH_CLAUSE__' => $searchClause,
            '__DATE_CLAUSE__' => $dateClause,
        ]);

        $bindings = array_merge($bindings, [$today], $centerIds);
        $patients = DB::select($sql, $bindings);
        
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


    public static function stockCheck(int|string $id): array
    {
        return \App\Services\Product\ProductService::stockCheck($id);
    }

    public static function inventoryCheck(mixed $request): int|float
    {
        return \App\Services\Product\ProductService::inventoryCheck($request);
    }

    public static function stockC(int|string $id): int|float
    {
        return \App\Services\Product\ProductService::stockC($id);
    }
    public static function saveActivityLogs(string $action, string $activityType, array $data, int|string $appointment_id): bool
    {
        return \App\Helpers\ActivityLogger::saveActivityLogs($action, $activityType, $data, $appointment_id);
    }
}
