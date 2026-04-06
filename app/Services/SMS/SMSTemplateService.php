<?php

declare(strict_types=1);

namespace App\Services\SMS;

use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Models\SMSTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SMSTemplateService
{
    public function getDatatableData(Request $request, int $accountId, int $userId): array
    {
        $filename = 'sms_templates';
        $filters = getFilters($request->all());
        $apply_filter = checkFilters($filters, $filename);

        $records = [];
        $records['data'] = [];

        [$orderBy, $order] = getSortBy($request);
        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $SMSTemplates = SMSTemplates::getBulkData($ids);
            if ($SMSTemplates) {
                foreach ($SMSTemplates as $SMSTemplate) {
                    $SMSTemplate->delete();
                }
            }
            $records['status'] = true;
            $records['message'] = 'Records has been deleted successfully!';
        }

        $iTotalRecords = SMSTemplates::getTotalRecords($request, $accountId, $apply_filter);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $SMSTemplates = SMSTemplates::getRecords($request, $iDisplayStart, $iDisplayLength, $accountId, $apply_filter);

        $records['data'] = $SMSTemplates;
        $records['permissions'] = [
            'edit' => Gate::allows('sms_templates_edit'),
            'active' => Gate::allows('sms_templates_active'),
            'inactive' => Gate::allows('sms_templates_inactive'),
        ];
        $filters = Filters::all($userId, 'sms_templates');
        $records['active_filters'] = $filters;
        $records['filter_values'] = [
            'status' => config('constants.status'),
        ];
        $records['meta'] = [
            'field' => $orderBy,
            'page' => $page,
            'pages' => $pages,
            'perpage' => $iDisplayLength,
            'total' => $iTotalRecords,
            'sort' => $order,
        ];

        return $records;
    }

    public function validateAndCreate(array $data, int $accountId): array
    {
        $validator = Validator::make($data, [
            'name' => 'required',
            'content' => 'required',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->errors()->first(), 'errors' => $validator->errors()];
        }

        if (SMSTemplates::createRecord((object) $data, $accountId)) {
            return ['success' => true, 'message' => 'Record has been created successfully.'];
        }

        return ['success' => false, 'error' => 'Something went wrong, please try again later.'];
    }

    public function getEditData(int $id): array
    {
        $sms_template = SMSTemplates::getData($id);
        if (! $sms_template) {
            return ['success' => false, 'error' => 'No Record Found!'];
        }

        $sms_template->variables = GeneralFunctions::smsTemplateVariables($sms_template->slug);

        return ['success' => true, 'sms_template' => $sms_template];
    }

    public function validateAndUpdate(array $data, int $id, int $accountId): array
    {
        $validator = Validator::make($data, [
            'name' => 'required',
            'content' => 'required',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->errors()->first(), 'errors' => $validator->errors()];
        }

        if (SMSTemplates::updateRecord($id, (object) $data, $accountId)) {
            return ['success' => true, 'message' => 'Record has been updated successfully.'];
        }

        return ['success' => false, 'error' => 'Something went wrong, please try again later.'];
    }

    public function deleteTemplate(int $id): array
    {
        $sms_template = SMSTemplates::getData($id);
        if (! $sms_template) {
            return ['success' => false, 'error' => 'Resource not found.'];
        }
        $sms_template->delete();

        return ['success' => true, 'message' => 'Record has been deleted successfully.'];
    }

    public function changeStatus(int $id, int $status): array
    {
        $sms_template = SMSTemplates::getData($id);
        if (! $sms_template) {
            return ['success' => false, 'error' => 'Resource not found.'];
        }

        if ($status == 0) {
            $sms_template->update(['active' => 0]);

            return ['success' => true, 'message' => 'Record has been inactivated successfully.'];
        } else {
            $sms_template->update(['active' => 1]);

            return ['success' => true, 'message' => 'Record has been activated successfully.'];
        }
    }
}
