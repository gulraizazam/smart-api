<?php

namespace App\Models;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Brand extends BaseModal
{
    use HasFactory;

    protected $fillable = ['name', 'account_id', 'status'];

    protected $table = 'brands';

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
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
     * @param  (int)  $iDisplayStart Start Index
     * @param  (int)  $iDisplayLength Total Records Length
     * @param  (int)  $account_id Current Organization's ID
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
     * @param  \Illuminate\Http\Request  $request
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function lead_sources_filters($request, $account_id, $search = false)
    {
        $where = [];
        $filters = getFilters($request->all());
        if (hasFilter($filters, 'name')) {
                $where[] = [
                    'name',
                    'like',
                    '%'.$filters['name'].'%',
                ];
        }

        return $where;
    }

    /**
     * Create Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function createRecord($request, $account_id)
    {
        $data = $request->all();
        // Set Account ID
        $data['account_id'] = $account_id;
        $record = self::create($data);

        return $record;
    }

    /**
     * Update Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $request, $account_id)
    {
        $old_data = (self::find($id))->toArray();

        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;

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
    public static function DeleteRecord($id)
    {
        $brand = self::getData($id);
        if (! $brand) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        // Check if child records exists or not, If exist then disallow to delete it.
        if (self::isChildExists($id, Auth::User()->account_id)) {
            $brand->update(['status'=>0]);
            return collect(['status' => false, 'message' => 'Brand Deactived Successfully!']);
        }
        $record = $brand->delete();

        return collect(['status' => true, 'message' => 'Record has been deleted successfully.']);
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
    {
        if (Product::where(['brand_id' => $id, 'account_id' => $account_id])->count()) {
            return true;
        }
        return false;
    }

    /**
     * Get All Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getAllRecordsDictionary($account_id)
    {
        return self::where(['account_id' => $account_id,'status'=>1])->get()->getDictionary();
    }
    public static function activeRecord($id, $status = 1)
    {
        $brand = self::getData($id);

        if (!$brand) {

            return false;
        }

        $record = $brand->update(['status' => $status]);

        return $record;
    }

}
