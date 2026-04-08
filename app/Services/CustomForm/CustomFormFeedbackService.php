<?php

declare(strict_types=1);

namespace App\Services\CustomForm;

use App\Helpers\Filters;
use App\Models\CustomFormFeedbackDetails;
use App\Models\CustomFormFeedbacks;
use App\Models\CustomForms;
use App\Models\Patients;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CustomFormFeedbackService
{
    private const FILTER_KEY = 'custom_form_feedbacks';

    // =========================================================================
    // Datatable
    // =========================================================================

    public function getDatatableData(array $requestData): array
    {
        $filters = getFilters($requestData);
        $applyFilter = checkFilters($filters, self::FILTER_KEY);

        $records = [];
        $records['data'] = [];

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $CustomFormFeedbacks = CustomFormFeedbacks::getBulkData($ids);
            if ($CustomFormFeedbacks) {
                foreach ($CustomFormFeedbacks as $custom_form_feedback) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! CustomFormFeedbacks::isChildExists($custom_form_feedback->id, Auth::user()->account_id)) {
                        $custom_form_feedback->delete();
                    }
                }
            }
            $records['status'] = true; // pass custom message(useful for getting status of group actions)
            $records['message'] = 'Records has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
        }

        $request = new Request($requestData);

        // Get Total Records
        $iTotalRecords = CustomFormFeedbacks::getTotalRecords($request, Auth::user()->account_id, $applyFilter, false, self::FILTER_KEY);

        [$orderBy, $order] = getSortBy($request);

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $CustomFormFeedbacks = CustomFormFeedbacks::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::user()->account_id, $applyFilter, false, self::FILTER_KEY);

        $records = $this->getFilters($records);

        if ($CustomFormFeedbacks) {
            foreach ($CustomFormFeedbacks as $custom_form_feedback) {
                $records['data'][] = [
                    'id' => $custom_form_feedback->internal_id,
                    'patient_id' => $custom_form_feedback->user ? \App\Helpers\GeneralFunctions::patientSearchStringAdd($custom_form_feedback->user->id) : null,
                    'form_name' => $custom_form_feedback->form_name,
                    'patient_name' => $custom_form_feedback->user?->name,
                    'created_at' => Carbon::parse($custom_form_feedback->created_at)->format('F j,Y h:i A'),
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
            'edit' => Gate::allows('custom_form_feedbacks_edit'),
            'delete' => Gate::allows('custom_form_feedbacks_destroy'),
            'active' => Gate::allows('custom_form_feedbacks_active'),
            'inactive' => Gate::allows('custom_form_feedbacks_inactive'),
            'preview' => Gate::allows('custom_form_feedbacks_preview'),
        ];

        return $records;
    }

    private function getFilters(array $records): array
    {
        $records['active_filters'] = Filters::all(Auth::user()->id, self::FILTER_KEY);

        return $records;
    }

    // =========================================================================
    // Form retrieval
    // =========================================================================

    public function getAllForms(): array|null
    {
        $forms = CustomForms::getAllForms(Auth::user()->account_id)->toArray();

        return $forms ?: null;
    }

    public function getFormFieldsData(int|string $formId): mixed
    {
        return CustomForms::get_all_fields_data($formId);
    }

    public function getPreviewFormData(int|string $formId): array
    {
        $users = Patients::getActiveOnly()->toArray();
        $custom_form = CustomForms::get_all_fields_data($formId);

        return [
            'users' => $users,
            'custom_form' => $custom_form,
        ];
    }

    // =========================================================================
    // Field update
    // =========================================================================

    public function updateField(array $requestData, int|string $feedbackId, int|string $feedbackFieldId): array
    {
        $request = new Request($requestData);
        $data = CustomFormFeedbackDetails::updateRecord($request, Auth::user()->account_id, Auth::id(), $feedbackId, $feedbackFieldId);

        if ($data) {
            return ['success' => true, 'data' => $data];
        }

        return ['success' => false];
    }

    // =========================================================================
    // Submit form
    // =========================================================================

    public function submitForm(array $requestData, int|string $id): array
    {
        $request = new Request($requestData);
        $custom_form_feedback = CustomFormFeedbacks::createRecord($request, $id, Auth::user()->account_id, Auth::id());

        if (! $custom_form_feedback) {
            return ['success' => false];
        }

        return ['success' => true];
    }

    // =========================================================================
    // Edit / Preview
    // =========================================================================

    public function getEditData(int|string $id): array|null
    {
        $custom_form_feedback = CustomFormFeedbacks::getAllFields($id);

        if (! $custom_form_feedback) {
            return null;
        }

        $patient_name = User::where('id', '=', $custom_form_feedback->reference_id)->first();

        return [
            'custom_form' => $custom_form_feedback,
            'patient_name' => $patient_name,
        ];
    }

    public function getFilledPreviewData(int|string $id): array|null
    {
        $custom_form_feedback = CustomFormFeedbacks::getAllFields($id);

        if (! $custom_form_feedback) {
            return null;
        }

        return [
            'custom_form' => $custom_form_feedback,
            'thisId' => $id,
        ];
    }

    public function getFilledPrintData(int|string $id): array|null
    {
        $custom_form_feedback = CustomFormFeedbacks::getAllFields($id);

        if (! $custom_form_feedback) {
            return null;
        }

        return [
            'custom_form' => $custom_form_feedback,
            'thisId' => $id,
        ];
    }

    // =========================================================================
    // Export PDF
    // =========================================================================

    public function getExportPdfData(int|string $id): array|null
    {
        $custom_form_feedback = CustomFormFeedbacks::getAllFields($id);

        if (! $custom_form_feedback) {
            return null;
        }

        return [
            'custom_form' => $custom_form_feedback,
            'thisId' => $id,
        ];
    }

    // =========================================================================
    // Update
    // =========================================================================

    public function updateFeedback(int|string $id, array $requestData): bool
    {
        $request = new Request($requestData);

        return (bool) CustomFormFeedbacks::updateRecord($id, $request, Auth::user()->account_id, Auth::id());
    }

    // =========================================================================
    // Destroy
    // =========================================================================

    public function destroyFeedback(int|string $id): array
    {
        $custom_form_feedback = CustomFormFeedbacks::getData($id);

        if (! $custom_form_feedback) {
            return ['success' => false, 'message' => 'Resource not found.'];
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (CustomFormFeedbacks::isChildExists($id, Auth::user()->account_id)) {
            return ['success' => false, 'message' => 'Child records exist, unable to delete resource.'];
        }

        CustomFormFeedbacks::deleteRecord($id);

        return ['success' => true, 'message' => 'Record has been deleted successfully.'];
    }

    // =========================================================================
    // Active / Inactive
    // =========================================================================

    public function inactivateFeedback(int|string $id): array
    {
        $custom_form_feedback = CustomFormFeedbacks::getData($id);

        if (! $custom_form_feedback) {
            return ['success' => false, 'message' => 'Resource not found.'];
        }

        CustomFormFeedbacks::inactivateRecord($id);

        return ['success' => true, 'message' => 'Record has been inactivated successfully.'];
    }

    public function activateFeedback(int|string $id): array
    {
        $custom_form_feedback = CustomFormFeedbacks::getData($id);

        if (! $custom_form_feedback) {
            return ['success' => false, 'message' => 'Resource not found.'];
        }

        CustomFormFeedbacks::activateRecord($id);

        return ['success' => true, 'message' => 'Record has been inactivated successfully.'];
    }
}
