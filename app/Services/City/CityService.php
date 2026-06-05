<?php

declare(strict_types=1);

namespace App\Services\City;

use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Models\Cities;
use App\Models\Regions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CityService
{
    public function getIndexData(int $userId): array
    {
        $filters = Filters::all($userId, 'cities');
        $regions = Regions::getActiveSorted(ACL::getUserRegions());
        $regions->prepend('Select a Region', '');

        return [
            'regions' => $regions,
            'filters' => $filters,
        ];
    }

    public function getDatatableData(Request $request, int $accountId): array
    {
        $filename = 'cities';
        $filters = getFilters($request->all());
        $apply_filter = checkFilters($filters, $filename);

        $records = [];
        $records['data'] = [];

        [$orderBy, $order] = getSortBy($request);
        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $Cities = Cities::getBulkData($ids);
            if ($Cities) {
                foreach ($Cities as $city) {
                    if (! Cities::isChildExists($city->id, $accountId)) {
                        $city->delete();
                    }
                }
            }
            $records['status'] = true;
            $records['message'] = 'Records has been deleted successfully!';
        }

        $iTotalRecords = Cities::getTotalRecords($request, $accountId, $apply_filter);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $Cities = Cities::getRecords($request, $iDisplayStart, $iDisplayLength, $accountId, $apply_filter);
        $Regions = Regions::getAllRecordsDictionary($accountId);

        if ($Cities) {
            foreach ($Cities as $citie) {
                $records['data'][] = [
                    'id' => $citie->id,
                    'name' => $citie->name,
                    'is_featured' => $citie->is_featured ? 'Yes' : 'No',
                    'region_id' => (array_key_exists($citie->region_id, $Regions)) ? $Regions[$citie->region_id]->name : 'N/A',
                    'active' => $citie->active,
                ];
            }
        }

        $records['permissions'] = [
            'edit' => Gate::allows('cities_edit'),
            'delete' => Gate::allows('cities_destroy'),
            'active' => Gate::allows('cities_active'),
            'inactive' => Gate::allows('cities_inactive'),
        ];

        $regions = Regions::getActiveSorted(ACL::getUserRegions());
        $filters = Filters::all(Auth::user()->id, 'cities');
        $records['active_filters'] = $filters;
        $records['filter_values'] = [
            'regions' => $regions,
            'is_featured' => [1 => 'Yes', 0 => 'No'],
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

    public function getCreateData(): array
    {
        $regions = Regions::getActiveSorted(ACL::getUserRegions());
        $regions->prepend('Select a Region', '');

        return ['regions' => $regions];
    }

    public function saveSortOrder(array $itemIDs): bool
    {
        if (count($itemIDs)) {
            foreach ($itemIDs as $key => $itemID) {
                Cities::where('id', '=', $itemID)->where('account_id', auth()->user()->account_id)->update(['sort_number' => $key]);
            }

            return true;
        }

        return false;
    }

    public function getSortedCities(int $accountId): mixed
    {
        return Cities::where(['account_id' => $accountId])->orderby('sort_number', 'ASC')->get();
    }

    public function validateAndCreate(array $data, int $accountId, ?int $id = null): array
    {
        $validator = Validator::make($data, [
            'name' => [
                'required',
                Rule::unique('cities')->ignore($id),
            ],
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->errors()->first()];
        }

        if (Cities::createRecord((object) $data, $accountId)) {
            return ['success' => true, 'message' => 'Record has been created successfully.'];
        }

        return ['success' => false, 'error' => 'Something went wrong, please try again later.'];
    }

    public function getEditData(int $id): array
    {
        $city = Cities::getData($id);
        if (! $city) {
            return ['success' => false, 'error' => 'No Record Found!'];
        }
        $regions = Regions::getActiveSorted(ACL::getUserRegions());
        $regions->prepend('Select a Region', '');

        return ['success' => true, 'city' => $city];
    }

    public function validateAndUpdate(array $data, int $id, int $accountId): array
    {
        $validator = Validator::make($data, [
            'name' => [
                'required',
                Rule::unique('cities')->ignore($id),
            ],
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'error' => $validator->errors()->first()];
        }

        if (Cities::updateRecord($id, (object) $data, $accountId)) {
            return ['success' => true, 'message' => 'Record has been updated successfully.'];
        }

        return ['success' => false, 'error' => 'Something went wrong, please try again later.'];
    }

    public function deleteCity(int $id): array
    {
        $response = Cities::DeleteRecord($id);

        return [
            'status' => $response->get('status'),
            'message' => $response->get('message'),
        ];
    }

    public function changeStatus(int $id, int $status): array
    {
        if ($status == 0) {
            $response = Cities::inactiveRecord($id);
        } else {
            $response = Cities::activeRecord($id);
        }

        return [
            'status' => $response->get('status'),
            'message' => $response->get('message'),
        ];
    }
}
