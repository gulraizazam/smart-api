<?php

declare(strict_types=1);

namespace App\Services\Appointment;

use App\Helpers\NodesTree;
use App\Models\Appointments;
use App\Models\CustomFormFeedbacks;
use App\Models\CustomForms;
use App\Models\Measurement;
use App\Models\Medical;
use App\Models\Patients;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AppointmentMedicalService
{
    public function getIndexData(int $id): array
    {
        $appointment = Appointments::findorfail($id);

        $patients = User::where([
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1'],
            ['user_type_id', '=', '3'],
        ])->pluck('name', 'id');

        $users = User::where([
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1'],
            ['user_type_id', '!=', '3'],
        ])->pluck('name', 'id');

        return [
            'appointment' => $appointment,
            'patients' => $patients,
            'users' => $users,
        ];
    }

    public function getCustomFormsForCreate(int $id): array
    {
        $accountId = Auth::user()->account_id;

        $CustomForms = $accountId
            ? CustomForms::where('account_id', $accountId)
                ->where('custom_form_type', '2')
                ->orderBy('sort_number', 'asc')
                ->get()
            : CustomForms::orderBy('sort_number', 'asc')->get();

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
        $patient_id = $users->first()?->id;

        $custom_form = CustomForms::get_all_fields_data($formId);

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
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
        $data['custom_form_type'] = 2;
        $custom_form_feedback = CustomFormFeedbacks::createRecord($request, $id, Auth::User()->account_id, Auth::id(), $data);
        if (! $custom_form_feedback) {
            return ['success' => false, 'message' => 'Invalid request', 'code' => 402];
        } else {
            $medicals = Medical::CreateRecord($request, $custom_form_feedback->id, Auth::User()->id);
            if (! $medicals) {
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

        $records = [];
        $records['data'] = [];

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $appointmentmeasurements = Measurement::getBulkData_formeasurement($ids);
            if ($appointmentmeasurements) {
                foreach ($appointmentmeasurements as $appointmentmeasurement) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! Measurement::isChildExists($appointmentmeasurement->id, Auth::User()->account_id)) {
                        $appointmentmeasurement->delete();
                    }
                }
            }
            $records['status'] = true; // pass custom message(useful for getting status of group actions)
            $records['message'] = 'Records has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
        }

        // Get Total Records
        $iTotalRecords = Medical::getTotalRecords($request, Auth::User()->account_id, $id);

        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $appointmentmedicals = Medical::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $id);

        if ($appointmentmedicals) {
            foreach ($appointmentmedicals as $appointmentmedicals) {
                $user = User::find($appointmentmedicals->user_id);
                $patient = User::find($appointmentmedicals->patient_id);
                $records['data'][] = [
                    'id' => $appointmentmedicals->id,
                    'name' => $appointmentmedicals->form_name,
                    'patient_id' => $patient?->name ?? 'N/A',
                    'created_by' => $user?->name ?? 'N/A',
                    'created_at' => Carbon::parse($appointmentmedicals->created_at)->format('F j,Y h:i A'),
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
            'edit' => Gate::allows('appointments_medical_edit'),
        ];

        return $records;
    }

    public function getEditData(int $id): array
    {
        $medicalinformation = Medical::find($id);

        if (! $medicalinformation) {
            return ['error' => true];
        }

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($medicalinformation->custom_form_feedback_id);

        if (! $custom_form_feedback) {
            return ['error' => true];
        }

        $patient_id = $custom_form_feedback->reference_id;
        $users = Patients::getActiveOnly()->toArray();

        return [
            'custom_form' => $custom_form_feedback,
            'users' => $users,
            'patient_id' => $patient_id,
            'medicalinformation' => $medicalinformation,
        ];
    }

    public function updateMedicalField(object $request): array
    {
        if (Medical::updateRecord($request, Auth::User()->account_id, Auth::id())) {
            return ['success' => true, 'message' => 'your Feedback is updated successfully', 'code' => 200];
        } else {
            return ['success' => false, 'message' => 'Invalid request', 'code' => 402];
        }
    }

    public function getFilledPreviewData(int $id): array
    {
        $medicalinformation = Medical::with('appointment.location')->findorFail($id);

        $custom_form_feedback = CustomFormFeedbacks::getAllFields($medicalinformation->custom_form_feedback_id);

        if (! $custom_form_feedback) {
            return ['error' => true];
        }

        $patient_id = $custom_form_feedback->reference_id;

        $users = Patients::getActiveOnly()->toArray();

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $leadServices = $medicalinformation->service_id;

        return [
            'custom_form' => $custom_form_feedback,
            'patient_id' => $patient_id,
            'medicalinformation' => $medicalinformation,
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
