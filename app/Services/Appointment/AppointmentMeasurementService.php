<?php

declare(strict_types=1);

namespace App\Services\Appointment;

use App;
use App\Helpers\NodesTree;
use App\Models\Appointments;
use App\Models\CustomFormFeedbacks;
use App\Models\CustomForms;
use App\Models\Measurement;
use App\Models\Patients;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AppointmentMeasurementService
{
    public function getIndexData(int $id): array
    {
        $appointment = Appointments::findorfail($id);

        $patients = User::where([
            ['account_id', '=', Auth::user()->account_id],
            ['active', '=', '1'],
            ['user_type_id', '=', '3'],
        ])->pluck('name', 'id');

        $users = User::where([
            ['account_id', '=', Auth::user()->account_id],
            ['active', '=', '1'],
            ['user_type_id', '!=', '3'],
        ])->pluck('name', 'id');

        return [
            'appointment' => $appointment,
            'patients' => $patients,
            'users' => $users,
        ];
    }

    public function getCreateData(int $id): array
    {
        $where = [];

        if (Auth::user()->account_id) {
            $where[] = [
                'account_id',
                '=',
                Auth::user()->account_id,
            ];
        }
        if (Auth::user()->account_id) {
            $where[] = [
                'custom_form_type',
                '=',
                '1',
            ];
        }

        if (count($where)) {
            $CustomForms = CustomForms::where($where)->orderBy('sort_number', 'asc')->get();
        } else {
            $CustomForms = CustomForms::orderBy('sort_number', 'asc')->get();
        }

        return [
            'CustomForms' => $CustomForms,
            'id' => $id,
        ];
    }

    public function getFillFormData(int $formId, int $appointmentId): array
    {
        $appointmentinformation = Appointments::find($appointmentId);
        $users = Patients::where([
            ['active', '=', '1'],
            ['id', '=', $appointmentinformation->patient_id],
        ])->get();
        foreach ($users as $user) {
            $patient_id = $user->id;
        }

        $custom_form = CustomForms::get_all_fields_data($formId);

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::user()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $leadServices = $appointmentinformation->service_id;

        return [
            'custom_form' => $custom_form,
            'users' => $users,
            'patient_id' => $patient_id,
            'appointmentinformation' => $appointmentinformation,
            'Services' => $Services,
            'leadServices' => $leadServices,
        ];
    }

    public function submitForm(object $request, int $id, int $appointmentId): array
    {
        $data['custom_form_type'] = 1;
        $custom_form_feedback = CustomFormFeedbacks::createRecord($request, $id, Auth::user()->account_id, Auth::id(), $data);
        if (! $custom_form_feedback) {
            return ['success' => false, 'message' => 'Invalid request', 'code' => 402];
        } else {
            $measurement = Measurement::CreateRecord($request, $custom_form_feedback->id, Auth::user()->id);
            if (! $measurement) {
                return ['success' => false, 'message' => 'Invalid request', 'code' => 402];
            }
        }

        return ['success' => true, 'message' => 'your Form is filled successfully', 'code' => 200];
    }

    public function getDatatableData(object $request, int $id): array
    {
        $records = [];
        $records['data'] = [];

        $filters = getFilters($request->all());

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $appointmentmeasurements = Measurement::getBulkData_formeasurement($ids);
            if ($appointmentmeasurements) {
                foreach ($appointmentmeasurements as $appointmentmeasurement) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! Measurement::isChildExists($appointmentmeasurement->id, Auth::user()->account_id)) {
                        $appointmentmeasurement->delete();
                    }
                }
            }
            $records['status'] = true; // pass custom message(useful for getting status of group actions)
            $records['message'] = 'Records has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
        }

        // Get Total Records
        $iTotalRecords = Measurement::getTotalRecords($request, Auth::user()->account_id, $id);

        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $appointmentmeasurements = Measurement::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::user()->account_id, $id);

        if ($appointmentmeasurements) {
            foreach ($appointmentmeasurements as $appointmentmeasurement) {
                $user = User::find($appointmentmeasurement->user_id);
                $patient = User::find($appointmentmeasurement->patient_id);
                $records['data'][] = [
                    'id' => $appointmentmeasurement->id,
                    'name' => $appointmentmeasurement->form_name,
                    'patient_id' => $patient->name,
                    'created_by' => $user->name,
                    'type' => $appointmentmeasurement->type,
                    'created_at' => Carbon::parse($appointmentmeasurement->created_at)->format('F j,Y h:i A'),
                ];
            }

            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];

        }

        $records['permissions'] = [
            'edit' => Gate::allows('appointments_measurement_edit'),
        ];

        return $records;
    }

    public function getEditData(int $id): array
    {
        $measurementinformation = Measurement::find($id);

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($measurementinformation->custom_form_feedback_id);

        $patient_id = $custom_form_feedback->reference_id;

        if (! $custom_form_feedback) {
            return ['error' => true];
        }

        $users = Patients::getActiveOnly()->toArray();

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::user()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $leadServices = $measurementinformation->service_id;

        return [
            'custom_form' => $custom_form_feedback,
            'users' => $users,
            'patient_id' => $patient_id,
            'measurementinformation' => $measurementinformation,
            'Services' => $Services,
            'leadServices' => $leadServices,
        ];
    }

    public function updateMeasurementField(object $request): array
    {
        if (Measurement::updateRecord($request, Auth::user()->account_id, Auth::id())) {
            return ['success' => true, 'message' => 'your Feedback is updated successfully', 'code' => 200];
        } else {
            return ['success' => false, 'message' => 'Invalid request', 'code' => 402];
        }
    }

    public function getFilledPreviewData(int $id): array
    {
        $measurementinformation = Measurement::with('appointment.location')->findorFail($id);

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($measurementinformation->custom_form_feedback_id);

        if (! $custom_form_feedback) {
            return ['error' => true];
        }
        $patient_id = $custom_form_feedback->reference_id;

        $users = Patients::getActiveOnly()->toArray();

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::user()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $leadServices = $measurementinformation->service_id;

        return [
            'custom_form' => $custom_form_feedback,
            'patient_id' => $patient_id,
            'measurementinformation' => $measurementinformation,
            'users' => $users,
            'Services' => $Services,
            'leadServices' => $leadServices,
            'thisId' => $id,
        ];
    }

    public function getFilledPrintData(int $id): array
    {
        return $this->getFilledPreviewData($id);
    }

    public function getExportPdfData(int $id): array
    {
        return $this->getFilledPreviewData($id);
    }
}
