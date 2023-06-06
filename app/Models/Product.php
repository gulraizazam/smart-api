<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Http\Request;

class Product extends BaseModal
{
    use HasFactory;

    protected $fillable = ['name', 'account_id', 'brand_id', 'sale_price', 'status'];

    protected $table = 'products';

    /**
     * Get Total Records
     *
     * @param (int) $account_id Current Organization's ID
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
     * @param (int) $iDisplayStart Start Index
     * @param (int) $iDisplayLength Total Records Length
     * @param (int) $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false)
    {
        $where = self::lead_sources_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id')->get();
        } else {
            return self::limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id')->get();
        }
    }

    /**
     * Get filters
     *
     * @param  \Illuminate\Http\Request  $request
     * @param (int) $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function lead_sources_filters($request, $account_id, $search = false)
    {
        $where = [];
        if ($search != false) {
            if (isset($search['name'])) {
                $where[] = [
                    'name',
                    'like',
                    '%'.$search['name'].'%',
                ];
            }
        }
        if ($search != false) {
            if (isset($search['brand_id'])) {
                $where[] = [
                    'brand_id',
                    '=',
                    $search['brand_id'],
                ];
            }
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
        $product = self::getData($id);
        if (! $product) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        // Check if child records exists or not, If exist then disallow to delete it.
        if (self::isChildExists($id, Auth::User()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }
        $detail_records = ProductDetail::where('product_id', $id)->get();
        if (! $detail_records->isEmpty()) {
            foreach ($detail_records as $detail_record) {
                $detail_record->delete();
            }
        }
        $record = $product->delete();

        return collect(['status' => true, 'message' => 'Record has been deleted successfully.']);
    }

    /**
     * Check if child records exist
     *
     * @param (int) $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
    {
        return false;
    }

    /* * Ajax base result of patient according to id or name
    * */
    public static function getProductsAjax($name, $account_id)
    {
        if (is_numeric($name)) {
            return self::where([
                ['status', '=', '1'],
                ['account_id', '=', $account_id],
                ['id', $name],
            ])->select('sale_price', 'name', 'id')->get();
        } else {
            return self::where([
                ['status', '=', '1'],
                ['account_id', '=', $account_id],
                ['name', 'LIKE', "%{$name}%"],
            ])->select('sale_price', 'name', 'id')->get();
        }
    }

    /**
     * Get All Records
     *
     * @param (int) $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getAllRecordsDictionary($account_id)
    {
        return self::where(['account_id' => $account_id])->get()->getDictionary();
    }

   /**
    * active Record
    *
    * @param id
    * @return (mixed)
    */
   public static function activeRecord($id, $status = 1)
   {
       $product = self::getData($id);

       if (! $product) {

           return false;
       }

       $record = $product->update(['status' => $status]);

       return $record;
   }
}
