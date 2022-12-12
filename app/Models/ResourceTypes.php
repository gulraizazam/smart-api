<?php

namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Resources\Json\Resource;
use App\Models\Resources;
use App\Models\ResourceHasRota;
use App\Models\AuditTrails;
use Auth;

class ResourceTypes extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name','slug','active', 'updated_by', 'created_by', 'created_at', 'updated_at'];

    protected static $_fillable = ['name','slug','active'];

    protected $table = 'resource_types';

    protected static $_table = 'resource_types';

    /**
     * sent the resource type name to resource table against resource_type_id.
     */
    public function resources()
    {
        return $this->hasOne('App\Models\Resources', 'resource_type_id');
    }

    /**
     * Get active and sorted data only.
     */
    static public function getActiveSorted($skip_ids = false, $include_ids = false)
    {
        if ($skip_ids && !is_array($skip_ids)) {
            $skip_ids = array($skip_ids);
        }
        if ($include_ids && !is_array($include_ids)) {
            $include_ids = array($include_ids);
        }

        if ($skip_ids && $include_ids) {
            return self::where(['active' => 1])->whereIn('id', $include_ids)->whereNotIn('id', $skip_ids)->OrderBy('name', 'asc')->get()->pluck('name', 'id');
        } else if ($skip_ids) {
            return self::where(['active' => 1])->whereNotIn('id', $skip_ids)->OrderBy('name', 'asc')->get()->pluck('name', 'id');
        } else if ($include_ids) {
            return self::where(['active' => 1])->whereIn('id', $include_ids)->OrderBy('name', 'asc')->get()->pluck('name', 'id');
        } else {
            return self::where(['active' => 1])->OrderBy('name', 'asc')->get()->pluck('name', 'id');
        }
    }

    /**
     * Get Total Records
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function getTotalRecords(Request $request)
    {
        $where = array();

        if ($request->get('name')) {
            $where[] = array(
                'name',
                'like',
                '%' . $request->get('name') . '%'
            );
        }

        if (count($where)) {
            return self::where($where)->count();
        } else {
            return self::count();
        }
    }

    /**
     * Get Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $iDisplayStart Start Index
     * @param (int) $iDisplayLength Total Records Length
     *
     * @return (mixed)
     */
    static public function getRecords(Request $request, $iDisplayStart, $iDisplayLength)
    {
        $where = array();

        if ($request->get('name')) {
            $where[] = array(
                'name',
                'like',
                '%' . $request->get('name') . '%'
            );
        }

        if (count($where)) {
            return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->get();
        } else {
            return self::limit($iDisplayLength)->offset($iDisplayStart)->get();
        }
    }

    /**
     * Get All Records
     *
     * @param: (void)
     *
     * @return (mixed)
     */
    static public function getAllRecordsDictionary()
    {
        return self::get()->getDictionary();
    }

    /**
     * Create Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function createRecord($request)
    {

        $data = $request->all();
        $data['slug'] = $request->name;
        $data['created_by'] = Auth::User()->id;
        $data['updated_by'] = Auth::User()->id;

        $record = self::create($data);

        //log request for Create for Audit Trail

        AuditTrails::addEventLogger(self::$_table, 'Create', $data, self::$_fillable, $record);

        return $record;

    }

    /**
     * Inactive Record
     *
     * @param $id
     *
     * @return (mixed)
     */
    static public function inactiveRecord($id)
    {
        $resource_type = ResourceTypes::findOrFail($id);

        $record = $resource_type->update(['active' => 0]);

        flash('Record has been inactivated successfully.')->success()->important();

        AuditTrails::inactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return $record;

    }

    /**
     * active Record
     *
     * @param $id
     *
     * @return (mixed)
     */
    static public function activeRecord($id)
    {
        $resource_type = ResourceTypes::findOrFail($id);

        $record = $resource_type->update(['active' => 1]);

        flash('Record has been inactivated successfully.')->success()->important();

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return $record;

    }

    /**
     * delete Record
     *
     * @param $id
     *
     * @return (mixed)
     */
    static public function deleteRecord($id)
    {
        $resource_types = ResourceTypes::findOrFail($id);

        if (ResourceTypes::isExists($id, Auth::User()->account_id)) {

            flash('Child record exist, unable to delete')->error()->important();
            return redirect()->route('admin.resource_types.index');
        }

        $record = $resource_types->delete();

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        flash('Record has been deleted successfully.')->success()->important();

        return $record;
    }

    /**
     * Update Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function updateRecord($id, $request)
    {
        $old_data = (ResourceTypes::find($id))->toArray();

        $data = $request->all();
        $data ['updated_by'] = Auth::User()->id;

        $record = self::where([
            'id' => $id,
        ])->first();

        if (!$record) {
            return null;
        }

        $record->update($data);

        AuditTrails::EditEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $id);

        return $record;
    }

    /**
     * Check if resource type use any where.
     *
     * @param (int) $id, $account id
     *
     * @return (boolean)
     */
    static public function isExists($id, $account_id)
    {
        if (Resources::where(
                ['resource_type_id' => $id, 'account_id' => $account_id])->count() ||
            ResourceHasRota::where(['resource_type_id' => $id])->count()) {
            return true;
        }
        return false;
    }
    /**
     * get resource type only for doctor and room.
     * @return (resource types)
     */
    static public function getResourceType(){
        return self::where('slug','=','Machine')->orwhere('slug','=','Doctor')->get()->pluck('name','id');


    }
    /**
     * get resource type only rota management.
     * @return (resource types)
     */
    static public function getResourceforrota(){
       return self::where('slug','=','Machine')->orwhere('slug','=','Doctor')->get();
    }
    /**
     * get resource type only rota management.
     * @return (resource types)
     */
    static public function getallresource(){
        return self::where('slug','!=','Doctor')->get()->pluck('name','id');
    }

}
