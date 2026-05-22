<?php

declare(strict_types=1);

namespace App\Services\PaymentMode;

use App\Models\PaymentModes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class PaymentModeService
{
    public function getDatatableData(Request $request, int $accountId): array
    {
        $filename = 'payment_modes';
        $filters = getFilters($request->all());
        $apply_filter = checkFilters($filters, $filename);

        $records = [];
        $records['data'] = [];
        [$orderBy, $order] = getSortBy($request);
        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $PaymentModes = PaymentModes::getBulkData($ids);
            if ($PaymentModes) {
                foreach ($PaymentModes as $city) {
                    if (! PaymentModes::isChildExists($city->id, $accountId)) {
                        $city->delete();
                    }
                }
            }
            $records['status'] = true;
            $records['message'] = 'Records has been deleted successfully!';
        }

        $iTotalRecords = PaymentModes::getTotalRecords($request, $accountId, $apply_filter);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $PaymentModes = PaymentModes::getRecords($request, $iDisplayStart, $iDisplayLength, $accountId, $apply_filter);
        foreach ($PaymentModes as $paymentMode) {
            $paymentMode->type = ucwords($paymentMode->type);
            $paymentMode->payment_type = config('constants.payment_type.'.$paymentMode->payment_type);
        }
        $records['data'] = $PaymentModes;
        $records['permissions'] = [
            'edit'     => Gate::allows('payment_modes.edit'),
            'delete'   => Gate::allows('payment_modes.destroy'),
            'active'   => Gate::allows('payment_modes.activate'),
            'inactive' => Gate::allows('payment_modes.deactivate'),
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

    public function saveSortOrder(array $itemIDs): bool
    {
        if (count($itemIDs)) {
            foreach ($itemIDs as $key => $itemID) {
                PaymentModes::where('id', '=', $itemID)->update(['sort_number' => $key]);
            }

            return true;
        }

        return false;
    }

    public function getSortedPaymentModes(int $accountId): mixed
    {
        return PaymentModes::where(['account_id' => $accountId])->orderby('sort_number', 'ASC')->get();
    }

    public function validateAndCreate(array $data, int $accountId): array
    {
        $validator = Validator::make($data, [
            'name' => 'required',
            'type' => 'required',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->errors()->first(), 'errors' => $validator->errors()];
        }

        if (PaymentModes::createRecord($data, $accountId)) {
            return ['success' => true, 'message' => 'Record has been created successfully.'];
        }

        return ['success' => false, 'error' => 'Something went wrong, please try again later.'];
    }

    public function getEditData(int $id): array
    {
        $payment_mode = PaymentModes::getData($id);
        if (! $payment_mode) {
            return ['success' => false, 'error' => 'No Record Found!'];
        }

        return ['success' => true, 'payment_mode' => $payment_mode];
    }

    public function validateAndUpdate(array $data, int $id, int $accountId): array
    {
        $validator = Validator::make($data, [
            'name' => 'required',
            'type' => 'required',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->errors()->first(), 'errors' => $validator->errors()];
        }

        if (PaymentModes::updateRecord($id, $data, $accountId)) {
            return ['success' => true, 'message' => 'Record has been updated successfully.'];
        }

        return ['success' => false, 'error' => 'Something went wrong, please try again later.'];
    }

    public function deletePaymentMode(int $id): array
    {
        $response = PaymentModes::deleteRecord($id);

        return [
            'status' => $response->get('status'),
            'message' => $response->get('message'),
        ];
    }

    public function changeStatus(int $id, int $status): array
    {
        if ($status == 0) {
            $response = PaymentModes::inactiveRecord($id);
        } else {
            $response = PaymentModes::activeRecord($id);
        }

        return [
            'status' => $response->get('status'),
            'message' => $response->get('message'),
        ];
    }
}
