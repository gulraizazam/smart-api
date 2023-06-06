<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class UserTypes extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'type', 'created_at', 'updated_at', 'account_id', 'active'];

    protected static $_fillable = ['name', 'type', 'active'];

    protected $table = 'user_types';

    protected static $_table = 'user_types';

    public static function getUserType_for_Doctor()
    {

        return self::where([
            ['type', '=', 'consultant'],
            ['account_id', '=', Auth::User()->account_id],
        ]
        )->get()->pluck('name', 'id');
    }

    /**
     * Get Total Records
     *
     * @param (int) $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false)
    {
        $where = [];

        $filters = getFilters($request->all());

        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
        }

        if (count($filters) > 0 && hasFilter($filters, 'name')) {
            $where[] = [
                'name',
                'like',
                '%'.$filters['name'].'%',
            ];
        }

        if (count($filters) > 0 && hasFilter($filters, 'type')) {
            $where[] = [
                'type',
                '=',
                $filters['type'],
            ];
        }

        if (count($where)) {
            return self::where([
                [$where],
                ['name', '!=', 'Administrator'],
            ])->count();
        } else {
            return self::where('name', '!=', 'Administrator')->count();
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
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false)
    {
        $where = [];

        $filters = getFilters($request->all());

        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
        }

        if (count($filters) > 0 && hasFilter($filters, 'name')) {
            $where[] = [
                'name',
                'like',
                '%'.$filters['name'].'%',
            ];
        }

        if (count($filters) > 0 && hasFilter($filters, 'type')) {
            $where[] = [
                'type',
                '=',
                $filters['type'],
            ];
        }

        if (count($where)) {
            return self::where($where)->where('name', '!=', 'Administrator')->limit($iDisplayLength)->offset($iDisplayStart)->get();
        } else {
            return self::where('name', '!=', 'Administrator')->limit($iDisplayLength)->offset($iDisplayStart)->get();
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
     * Create Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function createRecord($request, $account_id, $user_id)
    {
        $data = $request->all();

        $data['created_by'] = $userid = Auth::User()->id;

        $data['updated_by'] = $userid = Auth::User()->id;

        $data['account_id'] = $account_id;

        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        return $record;
    }

    /**
     * inactive Record
     *
     * @param id
     * @return (mixed)
     */
    public static function inactiveRecord($id)
    {

        $usertype = UserTypes::getData($id);

        if (! $usertype) {
            flash('Resource not found.')->error()->important();

            return redirect()->route('admin.user_types.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (UserTypes::isChildExists($id, Auth::User()->account_id)) {
            flash('Child records exist, unable to inactivate resource')->error()->important();

            return redirect()->route('admin.user_types.index');
        }

        $record = $usertype->update(['active' => 0]);

        flash('Record has been inactivated successfully.')->success()->important();

        AuditTrails::InactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return $record;
    }

    /**
     * active Record
     *
     * @param id
     * @return (mixed)
     */
    public static function activeRecord($id)
    {

        $usertype = UserTypes::getData($id);

        if (! $usertype) {
            flash('Resource not found.')->error()->important();

            return redirect()->route('admin.user_types.index');
        }

        $record = $usertype->update(['active' => 1]);

        flash('Record has been inactivated successfully.')->success()->important();

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return $record;
    }

    /**
     * delete Record
     *
     * @param id
     * @return (mixed)
     */
    public static function deleteRecord($id)
    {

        $usertypes = UserTypes::getData($id);

        if (! $usertypes) {
            flash('Resource not found.')->error()->important();

            return redirect()->route('admin.user_types.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (UserTypes::isChildExists($id, Auth::User()->account_id)) {
            flash('Child records exist, unable to delete resource')->error()->important();

            return redirect()->route('admin.user_types.index');
        }

        $record = $usertypes->delete();

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        flash('Record has been deleted successfully.')->success()->important();

        return $record;

    }

    /**
     * Update Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $request, $account_id, $user_id)
    {
        $old_data = (UserTypes::find($id))->toArray();

        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;
        $data['updated_by'] = $user_id;

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
            return null;
        }

        $record->update($data);

        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

        return $record;
    }

    /**
     * Check if child records exist
     *
     * @param (int) $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
    {
        if (
            User::where(['user_type_id' => $id, 'account_id' => $account_id])->count()
        ) {
            return true;
        }

        return false;
    }
}
