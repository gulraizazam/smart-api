<?php

declare(strict_types=1);

namespace App\Services\CentreTarget;

use App\Helpers\Filters;
use App\Models\Centertarget;
use App\Models\Locations;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CentreTargetService
{
    public function getDatatableData(object $request): array
    {
        $filename = 'centertarget';
        $filters = getFilters($request->all());
        $apply_filter = checkFilters($filters, $filename);

        $records = [];
        $records['data'] = [];

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $centretarget = Centertarget::getBulkData($ids);
            if ($centretarget) {
                foreach ($centretarget as $target) {
                    Centertarget::deleteRecord($target->id);
                }
            }
            $records['status'] = true;
            $records['message'] = 'Records has been deleted successfully!';
        }

        // Get Total Records
        $iTotalRecords = Centertarget::getTotalRecords($request, Auth::user()->account_id, $apply_filter);

        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $centretargets = Centertarget::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::user()->account_id, $apply_filter, $filters);

        $records = $this->getFilterData($records, $filename);

        if ($centretargets) {
            $months_data = Config::get('constants.months_array');
            foreach ($centretargets as $centretarget) {
                $records['data'][] = [
                    'id' => $centretarget->id,
                    'year' => $centretarget->year,
                    'month' => $months_data[$centretarget->month],
                    'created_at' => Carbon::parse($centretarget->created_at)->format('F j,Y h:i A'),
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
            'edit' => Gate::allows('centre_targets_edit'),
            'delete' => Gate::allows('centre_targets_destroy'),
            'active' => Gate::allows('centre_targets_active'),
            'inactive' => Gate::allows('centre_targets_inactive'),
            'create' => Gate::allows('centre_targets_create'),
            'allocate' => Gate::allows('centre_targets_allocate'),
            'manage' => Gate::allows('centre_targets_manage'),
        ];

        return $records;
    }

    private function getFilterData(array $records, string $filename): array
    {
        $months_data = Config::get('constants.months_array');
        foreach ($months_data as $key => $value) {
            $months[$key] = $value;
        }

        $years_data = range(Carbon::now()->year, Carbon::now()->subYears(10)->year);
        foreach ($years_data as $key => $value) {
            $years[$value] = $value;
        }

        $records['active_filters'] = Filters::all(Auth::user()->id, $filename);

        $records['filter_values'] = [
            'months' => $months,
            'years' => $years,
        ];

        return $records;
    }

    public function getCreateData(): array
    {
        $months_data = Config::get('constants.months_array');
        foreach ($months_data as $key => $value) {
            $months[$key] = $value;
        }

        $years_data = range(Carbon::now()->year, Carbon::now()->subYears(10)->year);
        foreach ($years_data as $key => $value) {
            $years[$value] = $value;
        }

        return [
            'months' => $months,
            'years' => $years,
        ];
    }

    public function loadTargetCentre(object $request): array
    {
        $locationdata = Locations::LoadtargetLocationdata($request);

        $targetlocation = $locationdata['CenterTargetArray'];

        $center_target_status = $locationdata['center_target_status'];

        $center_target_working_days = $locationdata['center_target_working_days'];

        return [
            'center_target_status' => $center_target_status,
            'center_target_working_days' => $center_target_working_days,
            'target_location' => $targetlocation,
        ];
    }

    public function storeRecord(object $request): array
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required',
            'month' => 'required',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'message' => $validator->messages()->first()];
        }

        $record = Centertarget::where([
            'month' => $request->get('month'),
            'year' => $request->get('year'),
            'account_id' => Auth::user()->account_id,
        ])->first();

        if ($record) {
            $staff_target = Centertarget::updateRecord($record->id, $request, Auth::user()->account_id);
        } else {
            $staff_target = Centertarget::createRecord($request, Auth::user()->account_id);
        }

        if ($staff_target) {
            return ['success' => true, 'message' => 'Record has been created successfully.'];
        }

        return ['success' => false, 'message' => 'Something went wrong, please try again later.'];
    }

    public function getEditData(int $id): array
    {
        $center_target = Centertarget::find($id);

        if (! $center_target) {
            return ['success' => false, 'message' => 'Resource not found'];
        }

        $months_data = Config::get('constants.months_array');
        foreach ($months_data as $key => $value) {
            $months[$key] = $value;
        }

        $years_data = range(Carbon::now()->year, Carbon::now()->subYears(10)->year);
        foreach ($years_data as $value) {
            $years[$value] = $value;
        }

        return [
            'success' => true,
            'center_target' => $center_target,
            'months' => $months,
            'years' => $years,
        ];
    }

    public function updateRecord(object $request, int $id): array
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required',
            'month' => 'required',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'message' => $validator->messages()->first()];
        }

        $record = Centertarget::find($id);

        if ($record) {
            $staff_target = Centertarget::updateRecord($record->id, $request, Auth::user()->account_id);
        } else {
            $staff_target = Centertarget::createRecord($request, Auth::user()->account_id);
        }

        if ($staff_target) {
            return ['success' => true, 'message' => 'Record has been updated successfully.'];
        }

        return ['success' => false, 'message' => 'Something went wrong, please try again later.'];
    }

    public function getDisplayData(int $id): array
    {
        $centertarget = Centertarget::with('center_target_meta.location')->find($id);

        return [
            'center_target' => $centertarget,
        ];
    }

    public function deleteRecord(int $id): void
    {
        Centertarget::deleteRecord($id);
    }

    public function getSystemTargets(): array
    {
        $accountId = Auth::user()->account_id;
        $targets = \App\Models\SystemTarget::where('account_id', $accountId)
            ->get()
            ->map(fn($t) => [
                    'key' => $t->target_key,
                    'value' => (float) $t->target_value,
                    'label' => $t->label,
                ]);

        return $targets->toArray();
    }

    public function saveSystemTarget(string $key, float $value): void
    {
        $accountId = Auth::user()->account_id;

        \App\Models\SystemTarget::setValue(
            $key,
            $value,
            $accountId,
            Auth::id()
        );
    }
}
