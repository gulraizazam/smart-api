<?php

namespace App\Models;

use App\Helpers\CustomFormHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

class CustomFormFields extends BaseModal
{
    use SoftDeletes;

    const sort_field = 'sort_number';
    protected $fillable = ['account_id', 'name', "description", "field_type", 'content', 'section_id', 'active', 'user_form_id', 'sort_number', 'created_by', 'updated_by', 'created_at', 'updated_at','form_type'];

    protected $table = 'custom_form_fields';

    protected static $_fillable = ['name', "description", "field_type", 'content', 'section_id', 'active', 'user_form_id', 'sort_number','form_type'];

    /**
     * Logable
     * @var array
     */
    public  $__fillable = ['name', "description", "field_type", 'content', 'section_id', 'active', 'user_form_id', 'sort_number','form_type'];

    protected static $_table = "custom_form_fields";

    /**
     * Logable through event
     * @var string
     */
    public $__table = "custom_form_fields";
    /**
     * Get active and sorted data only.
     */
    static public function getActiveSorted($cityId = false)
    {
        if ($cityId && !is_array($cityId)) {
            $cityId = array($cityId);
        }
        if ($cityId) {
            return self::whereIn('id', $cityId)->get()->pluck('name', 'id');
        } else {
            return self::get()->pluck('name', 'id');
        }
    }



    /**
     * Get active and sorted data only.
     */
    static public function getActiveFeaturedOnly($cityId = false, $account_id)
    {
        if ($cityId && !is_array($cityId)) {
            $cityId = array($cityId);
        }

        $query = self::where(['active' => 1, 'is_featured' => 1, 'account_id' => $account_id]);
        if ($cityId) {
            $query->whereIn('id', $cityId);
        }
        return $query->OrderBy('sort_number', 'asc');
    }


    /**
     * Create Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function createRecord($request, $account_id, $user_id, $form_id)
    {
        $data = [];
        $title = $request->get("question");
        $options = $request->get("field");
        $field_type = $request->get("field_type");
        $description = $request->get("description");
        // Set Account ID
        $data['account_id'] = $account_id;

        $data["content"] = CustomFormHelper::getContentJson($request);

        // Set Account ID
        $data['account_id'] = $account_id;
        $data['user_form_id'] = $form_id;
        $data["field_type"] = $field_type;
        $data["created_by"] = $user_id;
        $record = self::create($data);

        $record->update([self::sort_field => $record->id]);
        return $record;

    }

    /**
     * Update Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @param $account_id
     * @param $user_id
     * @param $form_id
     * @param $field_id
     * @return null (mixed)
     */
    static public function updateRecord($request, $account_id, $user_id, $form_id, $field_id)
    {

        $old_data = (self::find($field_id))->toArray();
        $record = self::where([
            'id' => $field_id,
            'user_form_id' => $form_id,
            'account_id' => $account_id
        ])->first();

        if (!$record) {
            return null;
        }

        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;

        $data["content"] = CustomFormHelper::getContentJson($request);
        $data["updated_by"] = $user_id;

        $record->update($data);
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
        if (
            Locations::where(['city_id' => $id, 'account_id' => $account_id])->count() ||
            Leads::where(['city_id' => $id, 'account_id' => $account_id])->count() ||
            Appointments::where(['city_id' => $id, 'account_id' => $account_id])->count()
        ) {
            return true;
        }

        return false;
    }

    public static function sortFields(Request $request, $id, $account_id, $user_id)
    {

        if ($request->has("cs_field")) {
            $fields = $request->get("cs_field");
            foreach ($fields as $order_no => $field_id) {
                $custom_field = self::where([
                    'id' => $field_id,
                    'user_form_id' => $id,
                    'account_id' => $account_id
                ])->first();
                $data["updated_by"] = $user_id;

                $custom_field->update([self::sort_field => $order_no]);
            }
            return true;
        } else {
            return null;
        }
    }

    /**
     * Delete field
     * @param $form_id
     * @param $field_id
     */
    public static function deleteRecord($form_id, $field_id)
    {
        $custom_form_field = CustomFormFields::getData($field_id);
        $custom_form_field->delete();
        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $field_id, $form_id);
    }

    /**
     * Model boot for database events
     */

    public static function boot() {



        parent::boot();


        static::created(function($item) {

            Event::fire('custom_form_field.created', $item);

        });


        static::updating(function($item) {

            Event::fire('custom_form_field.updating', $item);

        });



        static::deleting(function($item) {

            Event::fire('custom_form_field.deleting', $item);

        });

    }
}
