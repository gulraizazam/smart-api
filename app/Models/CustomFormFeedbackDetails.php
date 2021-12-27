<?php

namespace App\Models;

use App\Helpers\CustomFormHelper;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CustomFormFeedbackDetails extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['account_id', 'field_label', "field_value", "field_type", 'content', 'section_id', "custom_form_id", "custom_form_field_id", "custom_form_feedback_id", 'created_by', 'updated_by', 'created_at', 'updated_at', "deleted_at"];

    protected $table = 'custom_form_feedback_details';

    /**
     * logable array and table name
     * @var array
     */
    protected static $_fillable = ['field_label', "field_value","custom_form_id", "custom_form_field_id", "custom_form_feedback_id"];

    protected static $_table = "custom_form_feedbacks";

    const sort_field = 'id';


    /**
     * Get All Records
     *
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getAllRecordsDictionary($account_id)
    {
        return self::where(['account_id' => $account_id])->get()->getDictionary();
    }


    /**
     * Create Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @param $form_id
     * @param $field
     * @param $feedback_id
     * @param $account_id
     * @param $user_id
     * @return  (mixed)
     */
    static public function createRecord($request, $form_id, $field, $feedback_id, $account_id, $user_id)
    {

        $data_fields = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;
        $data['custom_form_id'] = $form_id;
        $data['custom_form_field_id'] = $field->id;
        $data['custom_form_feedback_id'] = $feedback_id;
        $data['field_type'] = $field->field_type;
        $data['content'] = $field->content;
        $field_content = CustomFormHelper::getContentArray($field->content);
        $data["field_label"] = $field_content["title"];
        //if($data_fields[$field->id] != config("constants.custom_form.field_types.title"))
        if(!isset($data_fields[$field->id]))
        {
            $data["field_value"] = '';
        }
        else
        {
            $data["field_value"] = $data_fields[$field->id];;
        }

        $data["section_id"] = $field->section_id;

        $data["created_by"] = $user_id;
        $record = self::create($data);
        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record, $feedback_id);

        return $record;
    }

    /**
     * Update Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function updateRecord(Request $request, $account_id, $user_id, $feedback_id, $feedback_field_id)
    {
        $old_data = (self::find($feedback_field_id))->toArray();
        $field_value = "null";
        if ($request->has("field_value")) {
            $field_value = $request->get("field_value");
        }

        $record = self::where([
            'id' => $feedback_field_id,
            'account_id' => $account_id
        ])->first();

        if (!$record) {
            return null;
        }

        $data["field_value"] = $field_value;
        $data["updated_by"] = $user_id;
        // Set Account ID
        $data['account_id'] = $account_id;
        $record->update($data);

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable,$old_data,$feedback_field_id);
        return $record;
    }

    /**
     * Check if child records exist
     *
     * @param (int) $id
     * @param
     *
     * @return (boolean)
     */
    static public function isChildExists($id, $account_id)
    {

        return false;
    }
}
