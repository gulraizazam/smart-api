<?php

declare(strict_types=1);

namespace App\Services\Region;

use App\Helpers\Filters;
use App\Models\Regions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class RegionService
{
    public function getIndexData(int $userId): array
    {
        $filters = Filters::all($userId, 'regions');

        return ['filters' => $filters];
    }

    public function getDatatableData(Request $request, int $accountId, int $userId): array
    {
        $apply_filter = false;
        $filters = getFilters($request->all());
        if (hasFilter($filters, 'filter')) {
            if (isset($filters['filter']) && $filters['filter'] == 'filter_cancel') {
                Filters::flush($userId, 'regions');
            } elseif ($filters['filter'] == 'filter') {
                $apply_filter = true;
            }
        }

        $records = [];
        $records['data'] = [];
        $existChild = false;
        [$orderBy, $order] = getSortBy($request);
        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $Regions = Regions::getBulkData($ids);
            if ($Regions) {
                foreach ($Regions as $city) {
                    if (! Regions::isChildExists($city->id, $accountId)) {
                        $city->delete();
                        $existChild = true;
                    }
                }
            }
            if (! $existChild) {
                $records['status'] = false;
                $records['message'] = 'Child records exist, unable to delete resource!';
            } else {
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }
        }

        $iTotalRecords = Regions::getTotalRecords($request, $accountId, $apply_filter);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $Regions = Regions::getRecords($request, $iDisplayStart, $iDisplayLength, $accountId, $apply_filter);
        $records['data'] = $Regions;
        $records['permissions'] = [
            'edit' => Gate::allows('regions_edit'),
            'delete' => Gate::allows('regions_destroy'),
            'active' => Gate::allows('regions_active'),
            'inactive' => Gate::allows('regions_inactive'),
        ];

        $filters = Filters::all($userId, 'regions');
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

    public function saveSortOrder(array $itemIDs): bool
    {
        if (count($itemIDs)) {
            foreach ($itemIDs as $key => $itemID) {
                Regions::where('id', '=', $itemID)->where('account_id', auth()->user()->account_id)->update(['sort_number' => $key]);
            }

            return true;
        }

        return false;
    }

    public function getSortedRegions(int $accountId): mixed
    {
        return Regions::where(['account_id' => $accountId])->where('slug', '=', 'custom')->orderby('sort_number', 'ASC')->get();
    }

    public function validateAndCreate(array $data, int $accountId): array
    {
        $validator = Validator::make($data, [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->errors()->first(), 'errors' => $validator->errors()];
        }

        if (Regions::createRecord((object) $data, $accountId)) {
            return ['success' => true, 'message' => 'Record has been created successfully.'];
        }

        return ['success' => false, 'error' => 'Something went wrong, please try again later.'];
    }

    public function getEditData(int $id): array
    {
        $region = Regions::getData($id);
        if (! $region) {
            return ['success' => false, 'error' => 'No Record Found!'];
        }

        return ['success' => true, 'region' => $region];
    }

    public function validateAndUpdate(array $data, int $id, int $accountId): array
    {
        $validator = Validator::make($data, [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->errors()->first(), 'errors' => $validator->errors()];
        }

        if (Regions::updateRecord($id, (object) $data, $accountId)) {
            return ['success' => true, 'message' => 'Record has been updated successfully.'];
        }

        return ['success' => false, 'error' => 'Something went wrong, please try again later.'];
    }

    public function deleteRegion(int $id): array
    {
        $response = Regions::DeleteRecord($id);

        return [
            'status' => $response->get('status'),
            'message' => $response->get('message'),
        ];
    }

    public function changeStatus(int $id, int $status): array
    {
        if ($status == 0) {
            $response = Regions::inactiveRecord($id);
        } else {
            $response = Regions::activeRecord($id);
        }

        return [
            'status' => $response->get('status'),
            'message' => $response->get('message'),
        ];
    }
}
