<?php

declare(strict_types=1);

namespace App\Services\CustomForm;

use App\Helpers\Filters;
use App\Models\CustomFormFields;
use App\Models\CustomForms;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Input;

class CustomFormService
{
    public function getDatatableData(object $request): array
    {
        $filename = 'custom_forms';

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, $filename);

        $records = [];
        $records['data'] = [];

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $CustomForms = CustomForms::getBulkData($ids);
            if ($CustomForms) {
                foreach ($CustomForms as $custom_form) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! CustomForms::isChildExists($custom_form->id, Auth::user()->account_id)) {
                        $custom_form->delete();
                    }
                }
            }
            $records['status'] = true; // pass custom message(useful for getting status of group actions)
            $records['message'] = 'Records has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
        }

        // Get Total Records
        $iTotalRecords = CustomForms::getTotalRecords($request, Auth::user()->account_id, $apply_filter);

        [$orderBy, $order] = getSortBy($request);

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $CustomForms = CustomForms::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::user()->account_id, $apply_filter);

        $records = $this->getFilters($records);

        if ($CustomForms) {
            $records['data'] = $CustomForms;

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
            'edit' => Gate::allows('custom_forms_edit'),
            'delete' => Gate::allows('custom_forms_destroy'),
            'active' => Gate::allows('custom_forms_active'),
            'inactive' => Gate::allows('custom_forms_inactive'),
            'preview' => Gate::allows('custom_forms_preview'),
            'submit' => Gate::allows('custom_forms_submit'),
        ];

        return $records;
    }

    private function getFilters(array $records): array
    {
        $form_types = [
            '' => 'All',
            '1' => 'Measurement Form',
            '0' => 'General Form',
            '2' => 'Medical Form',
        ];

        $records['active_filters'] = Filters::all(Auth::user()->id, 'custom_forms');

        $records['filter_values'] = [
            'form_types' => $form_types,
            'status' => config('constants.status'),
        ];

        return $records;
    }

    public function createGeneralForm(): int
    {
        $data['custom_form_type'] = '0';
        $form = CustomForms::createForm(Auth::user()->account_id, $data);

        return $form;
    }

    public function createMeasurementForm(): int
    {
        $data['custom_form_type'] = '1';
        $form = CustomForms::createForm(Auth::user()->account_id, $data);

        return $form;
    }

    public function createMedicalForm(): int
    {
        $data['custom_form_type'] = '2';
        $form = CustomForms::createForm(Auth::user()->account_id, $data);

        return $form;
    }

    public function saveSortOrder(): array
    {
        $custom_forms = DB::table('custom_forms')->where(['account_id' => Auth::user()->account_id])->orderBy('sort_number', 'ASC')->get();
        $itemID = Input::get('itemID');
        $itemIndex = Input::get('itemIndex');
        if ($itemID) {
            foreach ($custom_forms as $custom_form) {
                $sort = DB::table('custom_forms')->where('id', '=', $itemID)->where('account_id', Auth::user()->account_id)->update(['sort_number' => $itemIndex]);

                return ['status' => 'Data Sort Successfully'];
            }
        }

        return ['status' => 'Data Not Sort'];
    }

    public function sortFields(object $request, int $id): bool
    {
        return (bool) CustomFormFields::sortFields($request, $id, Auth::user()->account_id, Auth::id());
    }

    public function getSortOrderData(): object
    {
        return DB::table('custom_forms')->where(['account_id' => Auth::user()->account_id])->orderby('sort_number', 'ASC')->get();
    }

    public function storeRecord(object $request): bool
    {
        return (bool) CustomForms::createRecord($request, Auth::user()->account_id, Auth::id());
    }

    public function getEditData(int $id): array
    {
        $custom_form = CustomForms::get_all_fields_data($id);

        if (! $custom_form) {
            return ['error' => true];
        }

        return ['custom_form' => $custom_form];
    }

    public function updateRecord(int $id, object $request): bool
    {
        return (bool) CustomForms::updateRecord($id, $request, Auth::user()->account_id);
    }

    public function formUpdate(int $id, object $request): array
    {
        $custom_form = CustomForms::updateRecord($id, $request, Auth::user()->account_id, Auth::id());
        if ($custom_form) {
            return ['success' => true, 'data' => $custom_form];
        }

        return ['success' => false];
    }

    public function createField(object $request, int $id): array
    {
        $data = CustomFormFields::createRecord($request, Auth::user()->account_id, Auth::id(), $id);
        if ($data) {
            return ['success' => true, 'request' => $request->all(), 'data' => $data];
        }

        return ['success' => false, 'error' => $request->all(), 'id' => $id, 'data' => $data];
    }

    public function updateField(object $request, int $formId, int $fieldId): array
    {
        $data = CustomFormFields::updateRecord($request, Auth::user()->account_id, Auth::id(), $formId, $fieldId);

        if ($data) {
            return ['success' => true, 'request' => $request->all(), 'data' => $data];
        }

        return ['success' => false, 'error' => $request->all(), 'form_id' => $formId, 'field_id' => $fieldId, 'data' => $data];
    }

    public function deleteField(int $formId, int $fieldId): array
    {
        $custom_form_field = CustomFormFields::getData($fieldId);

        if (! $custom_form_field) {
            return ['success' => false, 'message' => 'Resource not found.', 'code' => 404];
        }

        CustomFormFields::deleteRecord($formId, $fieldId);

        return ['success' => true, 'message' => 'Record has been deleted successfully.', 'code' => 200];
    }

    public function destroyRecord(int $id): array
    {
        $custom_form = CustomForms::getData($id);

        if (! $custom_form) {
            return ['success' => false, 'message' => 'Resource not found.'];
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (CustomForms::isChildExists($id, Auth::user()->account_id)) {
            return ['success' => false, 'message' => 'Child records exist, unable to delete resource.'];
        }

        CustomForms::deleteRecord($id);

        return ['success' => true, 'message' => 'Record has been deleted successfully.'];
    }

    public function updateStatus(int $id, int $status): array
    {
        $custom_form = CustomForms::getData($id);

        if (! $custom_form) {
            return ['success' => false, 'message' => 'Resource not found.'];
        }

        if ($status == 1) {
            $response = CustomForms::activateRecord($id);
        } else {
            $response = CustomForms::inactivateRecord($id);
        }

        if ($response['status']) {
            return ['success' => true, 'message' => $response['message']];
        }

        return ['success' => false, 'message' => $response['message']];
    }
}
