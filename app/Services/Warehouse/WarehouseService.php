<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use Carbon\Carbon;
use App\Models\Cities;
use App\Models\Warehouse;
use App\Helpers\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class WarehouseService
{
    public function getDatatableData(Request $request, int $accountId): array
    {
        $records = [];
        $records['data'] = [];
        $filename = 'warehouse';

        $filters = getFilters($request->all());
        $apply_filter = checkFilters($filters, $filename);

        if (isset($filters['delete'])) {
            $ids = explode(',', $filters['delete']);
            $warehouses = Warehouse::getBulkData($ids);

            if (!$warehouses->isEmpty()) {
                $is_child = false;
                foreach ($warehouses as $warehouse) {
                    if (!Warehouse::isChildExists($warehouse->id, $accountId)) {
                        $warehouse->delete();
                        $is_child = true;
                    }
                }
                if (!$is_child) {
                    $records['status'] = false;
                    $records['message'] = 'Child records exist, unable to delete resource!';
                } else {
                    $records['status'] = true;
                    $records['message'] = 'Records has been deleted successfully!';
                }
            }
        }

        $iTotalRecords = Warehouse::getTotalRecords($request, $accountId, $apply_filter);
        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $warehouses = Warehouse::getRecords($request, $iDisplayStart, $iDisplayLength, $accountId, $apply_filter);
        $cities = Cities::getAllRecordsDictionary($accountId);

        if ($warehouses->count()) {
            foreach ($warehouses as $warehouse) {
                $records['data'][] = [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                    'manager_name' => $warehouse->manager_name ? $warehouse->manager_name : 'N/A',
                    'manager_phone' => $warehouse->manager_phone ? GeneralFunctions::prepareNumber4CallSMS($warehouse->manager_phone) : 'N/A',
                    'address' => $warehouse->address,
                    'city' => (array_key_exists($warehouse->city_id, $cities)) ? $cities[$warehouse->city_id]->name : 'N/A',
                    'status' => $warehouse->active,
                    'created_at' => Carbon::parse($warehouse->created_at)->format('F j,Y h:i A'),
                ];
            }
        }

        $records['permissions'] = [
            'manage' => Gate::allows('warehouse_manage'),
            'create' => Gate::allows('warehouse_create'),
            'edit' => Gate::allows('warehouse_edit'),
            'delete' => Gate::allows('warehouse_destroy'),
            'active' => Gate::allows('warehouse_active'),
        ];
        $records['active_filters'] = $filters;
        $records['filter_values'] = [
            'cities' => collect($cities)->pluck('name', 'id'),
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

    public function getCreateData(int $accountId): array
    {
        $cities = Cities::where([
            ['account_id', '=', $accountId],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
            ['is_featured', '=', '1'],
        ])->get()->pluck('full_name', 'id');

        return ['cities' => $cities];
    }

    public function validateAndCreate(array $data, int $accountId): array
    {
        $validator = Validator::make($data, [
            'name' => 'required',
            'city_id' => 'required',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->messages()->first()];
        }

        $warehouse = Warehouse::createRecord((object) $data, $accountId);
        if ($warehouse) {
            return ['success' => true, 'message' => 'Record has been created successfully.'];
        }

        return ['success' => false, 'error' => 'Something went wrong, please try again later.'];
    }

    public function getEditData(int $id, int $accountId): array
    {
        $warehouse = Warehouse::getData($id);
        if (!$warehouse) {
            return ['success' => false, 'error' => 'Record not found.'];
        }

        $cities = Cities::where([
            ['account_id', '=', $accountId],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
            ['is_featured', '=', '1'],
        ])->get()->pluck('full_name', 'id');
        $cities->prepend('Select a City', '');

        return [
            'success' => true,
            'warehouse' => $warehouse,
            'cities' => $cities,
        ];
    }

    public function validateAndUpdate(array $data, int $id, int $accountId): array
    {
        $validator = Validator::make($data, [
            'name' => 'required',
            'city_id' => 'required',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->messages()->first()];
        }

        $warehouse = Warehouse::updateRecord($id, (object) $data, $accountId);
        if ($warehouse) {
            return ['success' => true, 'message' => 'Record has been updated successfully.'];
        }

        return ['success' => false, 'error' => 'Something went wrong, please try again later.'];
    }

    public function deleteWarehouse(int $id): array
    {
        $result = Warehouse::deleteRecord($id);

        return [
            'status' => $result['status'],
            'message' => $result['message'],
        ];
    }

    public function changeStatus(int $id, int $status): array
    {
        $response = Warehouse::activeRecord($id, $status);
        if ($response) {
            return ['success' => true, 'message' => 'Status has been changed successfully.'];
        }

        return ['success' => false, 'error' => 'Resource not found.'];
    }
}
