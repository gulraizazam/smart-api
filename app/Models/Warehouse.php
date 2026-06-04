<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\Filters;
use App\Models\Concerns\GuardsTenantBoundary;
use App\Services\Reports\Concerns\ParsesDateRange;
use App\Support\SafeFilename;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class Warehouse extends Model
{
    use GuardsTenantBoundary;
    use HasFactory;
    use ParsesDateRange;

    protected $fillable = ['name', 'manager_name', 'manager_phone', 'account_id',  'address', 'google_map', 'city_id', 'active', 'created_at', 'updated_at', 'image_src'];

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id  Current Organization's ID
     * @return (mixed)
     */
    public static function getData($id)
    {
        return self::where([
            ['id', '=', $id],
            ['account_id', '=', Auth::user()->account_id],
        ])->first();
    }

    public static function getBulkData($id)
    {
        if (! is_array($id)) {
            $id = [$id];
        }

        return self::where([
            ['account_id', '=', Auth::user()->account_id],
        ])->whereIn('id', $id)
            ->get();
    }

    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::lead_sources_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            return self::where($where)->count();
        } else {
            return self::count();
        }
    }

    /**
     * Get Records
     *
     * @param  (int)  $iDisplayStart  Start Index
     * @param  (int)  $iDisplayLength  Total Records Length
     * @param  (int)  $account_id  Current Organization's ID
     * @return (mixed)
     */
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false)
    {
        $where = self::lead_sources_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id', 'desc')->get();
        } else {
            return self::limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id', 'desc')->get();
        }
    }

    /**
     * Get filters
     *
     * @param  Request  $request
     * @param  (int)  $account_id  Current Organization's ID
     * @return (mixed)
     */
    public static function lead_sources_filters($request, $account_id, $apply_filter)
    {
        $where = [];

        $filters = getFilters($request->all());
        [$start_date_time, $end_date_time] = self::parseDateRangeForFilter(
            hasFilter($filters, 'created_at') ? $filters['created_at'] : null
        );

        if (hasFilter($filters, 'name')) {
            $where[] = ['name', 'like', '%'.$filters['name'].'%'];
            Filters::put(Auth::user()->id, 'warehouse', 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'warehouse', 'name');
            } else {
                if (Filters::get(Auth::user()->id, 'warehouse', 'name')) {
                    $where[] = [
                        'name',
                        'like',
                        '%'.Filters::get(Auth::user()->id, 'warehouse', 'name').'%',
                    ];
                }
            }
        }
        if (hasFilter($filters, 'manager_name')) {
            $where[] = [
                'manager_name',
                'like',
                '%'.$filters['manager_name'].'%',
            ];
            Filters::put(Auth::user()->id, 'warehouse', 'manager_name', $filters['manager_name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'warehouse', 'manager_name');
            } else {
                if (Filters::get(Auth::user()->id, 'warehouse', 'manager_name')) {
                    $where[] = [
                        'manager_name',
                        'like',
                        '%'.Filters::get(Auth::user()->id, 'warehouse', 'manager_name').'%',
                    ];
                }
            }
        }
        if (hasFilter($filters, 'manager_phone')) {
            $where[] = [
                'manager_phone',
                'like',
                '%'.$filters['manager_phone'].'%',
            ];
            Filters::put(Auth::user()->id, 'warehouse', 'manager_phone', $filters['manager_phone']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'warehouse', 'manager_phone');
            } else {
                if (Filters::get(Auth::user()->id, 'warehouse', 'manager_phone')) {
                    $where[] = [
                        'manager_phone',
                        'like',
                        '%'.Filters::get(Auth::user()->id, 'warehouse', 'manager_phone').'%',
                    ];
                }
            }
        }
        if (hasFilter($filters, 'city')) {
            $where[][] = ['city_id' => $filters['city']];
            Filters::put(Auth::user()->id, 'warehouse', 'city_id', $filters['city']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'warehouse', 'city');
            } else {
                if (Filters::get(Auth::user()->id, 'warehouse', 'city')) {
                    $where[][] = ['city_id' => Filters::get(Auth::user()->id, 'warehouse', 'city')];
                }
            }
        }
        if (hasFilter($filters, 'status')) {
            $where[][] = ['active' => $filters['status']];
            Filters::put(Auth::user()->id, 'warehouse', 'active', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'warehouse', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'warehouse', 'status')) {
                    $where[][] = ['active' => Filters::get(Auth::user()->id, 'warehouse', 'status')];
                }
            }
        }
        if (hasFilter($filters, 'created_at')) {
            $where[] = ['created_at', '>=', $start_date_time];
            $where[] = ['created_at', '<=', $end_date_time];
            Filters::put(Auth::user()->id, 'warehouse', 'created_at', $filters['created_at']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'warehouse', 'created_at');
            } else {
                if (Filters::get(Auth::user()->id, 'warehouse', 'created_at')) {
                    $where[] = ['created_at', '>=', $start_date_time];
                    $where[] = ['created_at', '<=', $end_date_time];
                }
            }
        }

        return $where;
    }

    /**
     * Create Record
     *
     * @param  Request  $request
     * @return (mixed)
     */
    public static function createRecord($request, $account_id)
    {
        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;
        $data['active'] = 1;
        // Set Region ID
        $data['region_id'] = Cities::findOrFail($data['city_id'])->region_id;
        // Set Image
        if ($request->file('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                // Filename hardening — sanitise the browser-supplied
                // basename before joining it to a public storage path.
                $fileName = time().'-'.SafeFilename::sanitize(
                    $file->getClientOriginalName(),
                    ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                );
                $file->storeAs('public/warehouse_logo', $fileName);
                $data['image_src'] = $fileName;
            }
        }

        $record = self::create($data);

        $role = Role::findByName('Super-Admin');
        $user = RoleHasUsers::where('role_id', $role->id)->first();

        $user_has_warehouse = [
            'user_id' => $user->user_id,
            'warehouse_id' => $record->id,
        ];
        UserHasWarehouse::createRecord($user_has_warehouse, $user->id);

        return $record;
    }

    /**
     * Update Record
     *
     * @param  Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $request, $account_id)
    {
        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;

        // Set Image
        if ($request->file('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                // Filename hardening — sanitise the browser-supplied
                // basename before joining it to a public storage path.
                $fileName = time().'-'.SafeFilename::sanitize(
                    $file->getClientOriginalName(),
                    ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                );
                $file->storeAs('public/warehouse_logo', $fileName);
                $data['image_src'] = $fileName;
            }
        }
        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
            return null;
        }

        $record->update($data);

        return $record;
    }

    /**
     * Delete Record
     *
     * @param id
     * @return (mixed)
     */
    public static function deleteRecord($id)
    {
        $warehouse = Warehouse::getData($id);

        if (! $warehouse) {
            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }
        if (! Warehouse::isChildExists($warehouse->id, Auth::user()->account_id)) {
            $warehouse->delete();

            $role = Role::findByName('Super-Admin');
            $user = RoleHasUsers::where('role_id', $role->id)->first();

            UserHasWarehouse::where(['user_id' => $user->user_id, 'warehouse_id' => $warehouse->id])->delete();
        } else {
            $warehouse->update(['active' => 0]);

            return [
                'status' => false,
                'message' => 'Warehouse Deactivated Successfully!',
            ];
        }

        return [
            'status' => true,
            'message' => 'Record has been deleted successfully.',
        ];
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (bool)
     */
    public static function isChildExists($id, $account_id)
    {
        $isChildExist = Inventory::where(['warehouse_id' => $id])->count();
        if ($isChildExist > 0) {
            return true;
        }

        return false;
    }

    /**
     * Get All Records
     *
     * @param  (int)  $account_id  Current Organization's ID
     * @return (mixed)
     */
    public static function getAllRecordsDictionary($account_id, $locationids = false)
    {
        if ($locationids && ! is_array($locationids)) {
            $locationids = [$locationids];
        }
        if ($locationids) {
            return self::where(['account_id' => $account_id])->where(['active' => 1])->whereIn('id', $locationids)->get()->getDictionary();
        }
    }

    public static function activeRecord($id, $status)
    {
        $warehouse = Warehouse::getData($id);

        if (! $warehouse) {
            return false;
        }
        $record = $warehouse->update(['active' => $status]);

        return $record;
    }
}
