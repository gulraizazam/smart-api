<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Appointments;

use App\Models\Leads;
use App\Models\Patients;
use App\Models\Services;
use App\Models\Doctors;
use App\Models\Locations;
use App\Models\Resources;
use App\Models\MachineType;
use App\Models\MachineTypeHasServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\Helpers\ACL;
use App\Helpers\Widgets\AppointmentEditWidget;
use App\Helpers\Widgets\LocationsWidget;
use App\Services\Phone\PhoneFormattingService;

class AppointmentLookupController extends AppointmentBaseController
{
    /**
     * Delete all selected Appointment at once.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function loadLeadData(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = [
            'status' => 0,
            'patient_id' => 0,
            'phone' => null,
            'cnic' => null,
            'gender' => null,
            'dob' => null,
            'address' => null,
            'town_id' => null,
            'referred_by' => null,
            'name' => null,
            'email' => null,
            'service_id' => null,
            'lead_source_id' => null,
        ];
        // Lookup runs the phone classifier — returns matched patient/lead
        // data so the form can pre-fill the dialog. Either module's
        // create perm grants enriched data; `appointments_manage` kept
        // as the legacy fallback.
        if (
            \Illuminate\Support\Facades\Gate::allows('consultations.create')
            || \Illuminate\Support\Facades\Gate::allows('treatments.create')
            || \Illuminate\Support\Facades\Gate::allows('appointments_manage')
        ) {
            $phone = PhoneFormattingService::cleanNumber($request->phone);
            $patient = Patients::getByPhone($phone, Auth::user()->account_id, $request->patient_id ? (int) $request->patient_id : false);
            if (! $patient) {
                $data['status'] = 1;
                $data['service_id'] = $request->service_id;
                $data['phone'] = $request->phone;
                $data['dob'] = $request->dob;
                $data['address'] = $request->address;
                $data['cnic'] = $request->cnic;
                $data['referred_by'] = $request->referred_by;
                $data['gender'] = $request->gender;
            } else {
                $lead = Leads::where(['patient_id' => $patient->id, 'service_id' => $request->service_id])->first();
                if ($lead) {
                    $data['service_id'] = $lead->service_id;
                    $data['lead_source_id'] = $lead->lead_source_id;
                    $data['lead_id'] = $lead->id;
                    $data['town_id'] = $lead->town_id;
                } else {
                    $data['service_id'] = $request->service_id;
                    $data['lead_id'] = '';
                }
                $data['patient_id'] = $patient->id;
                $data['phone'] = $patient->phone;
                $data['dob'] = $patient->dob;
                $data['address'] = $patient->address;
                $data['cnic'] = $patient->cnic;
                $data['referred_by'] = $patient->referred_by;
                $data['name'] = $patient->name;
                $data['email'] = $patient->email;
                $data['gender'] = $patient->gender !== null ? (string) $patient->gender : null;
            }
        }

        return $this->successResponse('data found', $data);
    }

    public function loadLocationsByCity(Request $request): \Illuminate\Http\JsonResponse
    {

        try {
            if ($request->city_id) {
                if ($request->machine_type_allocation) {
                    if ($request->appointment_manage == Config::get('constants.appointment_type_service_string')) {
                        $reverse_process = true;
                    } else {
                        $reverse_process = false;
                    }
                    $locationsids = [];
                    $locations = Locations::getActiveRecordsByCity($request->city_id, ACL::getUserCentres(), Auth::user()->account_id);
                    /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
                    foreach ($locations as $location) {
                        $location_serivce = AppointmentEditWidget::loadlocationservice_edit($location->id, Auth::user()->account_id, $reverse_process);
                        if (in_array($request->service_id, $location_serivce, true)) {
                            $locationsids[] = $location->id;
                        }
                    }
                    $locations = Locations::whereIn('id', $locationsids)->get();
                    if ($locations) {
                        $locations = $locations->pluck('name', 'id');
                    }

                } else {
                    $locations = Locations::getActiveRecordsByCity($request->city_id, ACL::getUserCentres(), Auth::user()->account_id);
                    if ($locations) {
                        $locations = $locations->pluck('name', 'id');
                    }
                }

                return $this->successResponse('Record found', [
                    'dropdown' => $locations,
                ]);
            }
            $assigned_locations = ACL::getUserCentres();
            $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::user()->account_id);

            return $this->successResponse('Record found', [
                'dropdown' => $locations->pluck('name', 'id'),
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentLookupController');
        }
    }

    public function LoadChildServices(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if ($request->serviceId) {
                $child_services = Services::where(['parent_id' => $request->serviceId, 'active' => 1])->get();
                if ($child_services) {
                    $child_services = $child_services->pluck('name', 'id');
                }
            }

            return $this->successResponse('Record found', [
                'dropdown' => $child_services,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentLookupController');
        }
    }

    public function loadDoctorsByLocation(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if ($request->location_id) {
                if ($request->machine_type_allocation) {
                    $doctors = $doctors_no_final = LocationsWidget::loadAppointmentDoctorByLocation($request->location_id, Auth::user()->account_id);
                    if ($request->appointment_manage == Config::get('constants.appointment_type_service_string')) {
                        $reverse_process = true;
                    } else {
                        $reverse_process = false;
                    }
                    $doctorids = [];
                    /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
                    foreach ($doctors as $key => $doctor) {
                        $doctor_serivce = AppointmentEditWidget::loaddoctorservice_edit($key, $request->location_id, Auth::user()->account_id, $reverse_process);
                        if (in_array($request->service_id, $doctor_serivce, true)) {
                            $doctorids[] = $key;
                        }
                    }
                    $doctors = $doctors_no_final = Doctors::whereIn('id', $doctorids)->pluck('name', 'id');
                } else {
                    $doctors = LocationsWidget::loadAppointmentDoctorByLocation($request->location_id, Auth::user()->account_id);
                }

                return $this->successResponse('Record found', [
                    'dropdown' => $doctors,
                ]);
            }

            return $this->errorResponse('Record found', 404, [
                'dropdown' => null,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentLookupController');
        }
    }

    public function loadConsultantDoctorsByLocation(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if ($request->location_id) {
                // Use the proper consultant doctor loader that filters by doctor_has_locations with is_allocated = 1
                $doctors = LocationsWidget::loadConsultantDoctorByLocation($request->location_id, Auth::user()->account_id);

                return $this->successResponse('Record found', [
                    'dropdown' => $doctors->toArray(),
                ]);
            }

            return $this->errorResponse('Record found', 404, [
                'dropdown' => null,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentLookupController');
        }
    }

    /**
     * Doctors eligible to perform TREATMENTS at a location — Consultants,
     * Lifestyle Consultants and all Aesthetic Doctors (no
     * can_perform_consultation gate, which only applies to consultations).
     */
    public function loadTreatmentDoctorsByLocation(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if ($request->location_id) {
                $doctors = LocationsWidget::loadTreatmentDoctorByLocation($request->location_id, Auth::user()->account_id);

                return $this->successResponse('Record found', [
                    'dropdown' => $doctors->toArray(),
                ]);
            }

            return $this->errorResponse('Record found', 404, [
                'dropdown' => null,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentLookupController');
        }
    }

    public function loadServiceByLocation(Request $request): \Illuminate\Http\JsonResponse
    {
        if ($request->location_id) {
            $doctors = LocationsWidget::loadAppointmentDoctorByLocation($request->location_id, Auth::user()->account_id);
            //$doctors = Doctors::getActiveOnly($request->location_id);
            $doctors->prepend('Select a Doctor', '');

            return response()->json([
                'status' => 1,
                'dropdown' => view('admin.appointments.dropdowns.doctors', compact('doctors'))->render(),
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'dropdown' => null,
            ]);
        }
    }

    public function loadEndServiceByBaseService(Request $request): \Illuminate\Http\JsonResponse
    {

        if ($request->service_id) {
            $child_services = \App\Models\Appointments::getNodeServices($request->service_id, Auth::user()->account_id, true, true);

            // If resource_id is provided, filter services by machine type
            if ($request->resource_id) {
                $resource = Resources::whereId($request->resource_id)->first();
                if ($resource) {
                    $machine_services = MachineTypeHasServices::where('machine_type_id', $resource->machine_type_id)
                    ->where('service_id',$request->service_id)
                    ->first();
                    if($machine_services){
                        return $this->successResponse('Record found', [
                            'services' => $child_services,
                        ]);
                    }else{

                        $machine_services = MachineTypeHasServices::where('machine_type_id', $resource->machine_type_id)
                        ->whereIn('service_id',array_keys($child_services))
                        ->pluck('service_id');

                        $available_services = array_filter($child_services, fn($service, $id) => in_array($id, $machine_services->toArray(), true), ARRAY_FILTER_USE_BOTH);
                        return $this->successResponse('Record found', [
                            'services' => $available_services,
                        ]);
                    }
                }
            }

            // No resource selected or resource not found, return all child services
            return $this->successResponse('Record found', [
                'services' => $child_services,
            ]);
        }

        return $this->errorResponse('Record not found', 200);
    }

    /**
     * Load all active child services (where parent_id != 0)
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loadAllChildServices(Request $request): \Illuminate\Http\JsonResponse
    {
        $account_id = Auth::user()->account_id;

        // Get all active child services (parent_id != 0)
        $childServices = Services::where('account_id', $account_id)
            ->where('active', 1)
            ->where('parent_id', '!=', 0)
            ->orderBy('name', 'asc')
            ->get();

        // If resource_id is provided, filter services by machine type
        if ($request->resource_id) {
            $resource = Resources::whereId($request->resource_id)->first();
            if ($resource) {
                // Get all service IDs that are compatible with this machine type
                $compatibleServiceIds = MachineTypeHasServices::where('machine_type_id', $resource->machine_type_id)
                    ->pluck('service_id')
                    ->toArray();

                // Filter child services to only those compatible with the machine
                // Either the service itself OR its parent should be in compatible services
                $childServices = $childServices->filter(fn($service) => in_array($service->id, $compatibleServiceIds, true) || in_array($service->parent_id, $compatibleServiceIds, true));
            }
        }

        // Format for dropdown
        $services = [];
        foreach ($childServices as $service) {
            $services[$service->id] = $service->name;
        }

        return $this->successResponse('Record found', [
            'services' => $services,
        ]);
    }

    public function getRoomResourcesWithDate(Request $request): \Illuminate\Http\JsonResponse
    {
        if ($resources = Resources::getMachinesResourcesRotaWithoutDays($request->location_id, $request->machine_id)) {
            return response()->json(['status' => 1, 'data' => $resources], 200);
        } else {
            return response()->json(['status' => 0, 'data' => null], 200);
        }
    }

    public function getRoomResources(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json(['status' => 1, 'data' => Resources::getRoomsWithRotas()->toArray()], 200);
    }

    public function center_machines(Request $request, $location_id): \Illuminate\Http\JsonResponse
    {
        if ($request->machine_type_allocation) {
            $machines = Resources::where([['resource_type_id', '=', config('constants.resource_room_type_id')], ['active', '=', '1'], ['location_id', '=', $location_id], ['account_id', '=', Auth::user()->account_id]])->get();
            if ($request->appointment_manage == Config::get('constants.appointment_type_service_string')) {
                $reverse_process = true;
            } else {
                $reverse_process = false;
            }
            $machineids = [];
            /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
            foreach ($machines as $machine) {
                $machinetypeid = MachineType::where('id', '=', $machine->machine_type_id)->first();
                $machine_serivce = AppointmentEditWidget::loadmachinetypeservice_edit($machinetypeid->id, Auth::user()->account_id, 'true');
                if (in_array($request->service_id, $machine_serivce, true)) {
                    $machineids[] = $machine->id;
                }
            }
            $machines = Resources::whereIn('id', $machineids)->pluck('name', 'id');
            /*End*/
        } else {
            $machines = Resources::where([['resource_type_id', '=', config('constants.resource_room_type_id')], ['active', '=', '1'], ['location_id', '=', $location_id], ['account_id', '=', Auth::user()->account_id]])->pluck('name', 'id');
        }
        if ($machines) {
            return $this->successResponse('recourd found', [
                'dropdown' => $machines,
            ]);
        }

        return $this->errorResponse('recourd found', 404, [
            'dropdown' => null,
        ]);
    }

    public function checkPhoneExist(Request $request): \Illuminate\Http\JsonResponse
    {
        $record = Patients::where('phone', 'like', '%'.PhoneFormattingService::cleanNumber($request->input('phone').'%'))->first();
        if ($record) {
            return response()->json(1);
        } else {
            return response()->json(0);
        }
    }
}
