<?php

namespace App\Models;

use App\Helpers\Filters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\AuditTrails;
use Auth;

class Documents extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'url', 'active', 'user_id', 'created_at','updated_at','deleted_at'];

    protected static $_fillable = ['name', 'url', 'active', 'user_id'];

    protected $table = 'documents';

    protected static $_table = 'documents';

    /*
     * Create record of dcoument
     *
     * @param $file , id
     *
     * @return record
     */
    static public function CreateRecord($file,$request,$id){

        $data['name'] = $request->name;
        $data['url'] = $file->getClientOriginalName();
        $data['user_id'] = $id;

        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        return $record;
    }

    /**
     * Update Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function updateRecord($id, $request, $account_id)
    {
        $old_data = (Documents::find($id))->toArray();

        $data = $request->all();
        // Set Account ID
        $record = self::where([
            'id' => $id,
        ])->first();

        if (!$record) {
            return null;
        }

        $record->update($data);

        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

        return $record;
    }

    /**
     * Delete Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function DeleteRecord($id)
    {
        $document = Documents::find($id);
        if (!$document) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.patients.document', ['id' => $document->user_id]);
        }
        $record = $document->delete();
        //log request for delete for audit trail
        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        flash('Record has been deleted successfully.')->success()->important();

        return $record;

    }
    /**
     * Get Total Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getTotalRecords($request, $account_id = false,$id , $apply_filter = false, $filename )
    {

        $where = self::filters_documents( $request,$account_id,$id, $apply_filter , $filename );

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
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getRecords($id,$request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false, $filename )
    {
        $where = self::filters_documents( $request, $account_id, $id, $apply_filter, $filename );
        $orderBy = 'created_at';
        $order = 'desc';

        if ($request->get('order')[0]['dir']) {
            $orderColumn = $request->get('order')[0]['column'];
            $orderBy = $request->get('columns')[$orderColumn]['data'];
            $order = $request->get('order')[0]['dir'];
        }
        if (count($where)) {
            return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy,$order)->get();
        } else {
            return self::limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy,$order)->get();
        }
    }

    static public function filters_documents( $request, $account_id, $id, $apply_filter, $filename )
    {
        $where = array();

        if ($id != false )
        {
            $where[] = array(
                'user_id',
                '=',
                $id
            );
            Filters::put(Auth::user()->id , $filename, 'id', $id);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id, $filename, 'id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'id')){
                    $where[] = array(
                        'user_id',
                        '=',
                        Filters::get(Auth::user()->id,$filename, 'id')
                    );
                }
            }
        }

        if ($request->get('name') && $request->get('name') != '') {
            $where[] = array(
                'name',
                'like',
                '%' . $request->get('name') . '%'
            );
            Filters::put(Auth::user()->id, $filename, 'name', $request->get('name'));
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id, $filename , 'name');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'name' )){
                    $where[] = array(
                        'name',
                        'like',
                        '%' . Filters::get(Auth::user()->id, $filename, 'name') . '%'
                    );
                }
            }
        }

        if ($request->get('created_from') && $request->get('created_from') != '') {
            $where[] = array(
                'created_at',
                '>=',
                $request->get('created_from') . ' 00:00:00'
            );
            Filters::put(Auth::user()->id, $filename, 'created_from', $request->get('created_from') . '00:00:00');
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id, $filename, 'created_from');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'created_from')){
                    $where[] = array(
                        'created_at',
                        '>=',
                        Filters::get(Auth::user()->id, $filename, 'created_from')
                    );
                }
            }
        }

        if ($request->get('created_to') && $request->get('created_to') != '') {
            $where[] = array(
                'created_at',
                '<=',
                $request->get('created_to') . ' 23:59:59'
            );
            Filters::put(Auth::user()->id , $filename, 'created_to', $request->get('created_to') . '23:59:59');
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id, $filename , 'created_to');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'created_to')){
                    $where[] = array(
                        'created_at',
                        '<=',
                        Filters::get(Auth::user()->id, $filename, 'created_to')
                    );
                }
            }
        }

        return $where ;
    }
}
