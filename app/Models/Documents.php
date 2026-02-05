<?php

namespace App\Models;

use App\Helpers\Filters;
use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

class Documents extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'document_type', 'url', 'active', 'user_id', 'created_at', 'updated_at', 'deleted_at'];

    protected static $_fillable = ['name', 'document_type', 'url', 'active', 'user_id'];

    protected $table = 'documents';

    protected static $_table = 'documents';

    protected $appends = ['full_url'];

    /**
     * Get the full URL for the document
     */
    public function getFullUrlAttribute()
    {
        if ($this->url) {
            // Use url() instead of asset() to avoid /public prefix
            return url('storage/app/public/' . $this->url);
        }
        return null;
    }

    /*
     * Create record of dcoument
     *
     * @param $file , id
     *
     * @return record
     */
    public static function CreateRecord($request, $path, $id)
    {

        $data['name'] = $request->name ?? '';
        $data['document_type'] = $request->document_type ?? null;
        $data['url'] = $path;
        $data['user_id'] = $id;

        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

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
        $old_data = (Documents::find($id))->toArray();

        $data = $request->all();
        // Set Account ID
        $record = self::where([
            'id' => $id,
        ])->first();

        if (! $record) {
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
     * @return (mixed)
     */
    public static function DeleteRecord($id)
    {
        $document = Documents::find($id);
        if (! $document) {
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
     * @param  \Illuminate\Http\Request  $request
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords($request, $account_id, $id, $apply_filter, $filename)
    {

        $where = self::filters_documents($request, $account_id, $id, $apply_filter, $filename);

        if (count($where)) {
            return self::where($where)->count();
        } else {
            return self::count();
        }
    }

    /**
     * Get Records
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  (int)  $iDisplayStart Start Index
     * @param  (int)  $iDisplayLength Total Records Length
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getRecords($id, $request, $iDisplayStart, $iDisplayLength, $account_id, $apply_filter, $filename)
    {
        $where = self::filters_documents($request, $account_id, $id, $apply_filter, $filename);

        [$orderBy, $order] = getSortBy($request);

        if (count($where)) {
            return self::with('patient')->where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy, $order)->get();
        } else {
            return self::with('patient')->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy, $order)->get();
        }
    }

    public static function filters_documents($request, $account_id, $id, $apply_filter, $filename)
    {
        $where = [];
        $filters = getFilters($request->all());

        if ($id != false) {
            $where[] = [
                'user_id',
                '=',
                $id,
            ];
            Filters::put(Auth::user()->id, $filename, 'id', $id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'id')) {
                    $where[] = [
                        'user_id',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'name')) {
            $where[] = [
                'name',
                'like',
                '%'.$filters['name'].'%',
            ];
            Filters::put(Auth::user()->id, $filename, 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'name');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'name')) {
                    $where[] = [
                        'name',
                        'like',
                        '%'.Filters::get(Auth::user()->id, $filename, 'name').'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_from')) {
            $where[] = [
                'created_at',
                '>=',
                $filters['created_from'].' 00:00:00',
            ];
            Filters::put(Auth::user()->id, $filename, 'created_from', $filters['created_from'].' 00:00:00');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'created_from');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'created_from')) {
                    $where[] = [
                        'created_at',
                        '>=',
                        Filters::get(Auth::user()->id, $filename, 'created_from'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_to')) {
            $where[] = [
                'created_at',
                '<=',
                $filters['created_to'].' 23:59:59',
            ];
            Filters::put(Auth::user()->id, $filename, 'created_to', $filters['created_to'].' 23:59:59');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'created_to');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'created_to')) {
                    $where[] = [
                        'created_at',
                        '<=',
                        Filters::get(Auth::user()->id, $filename, 'created_to'),
                    ];
                }
            }
        }

        return $where;
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
