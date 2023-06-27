<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    protected $error;

    protected $success;

    protected $unauthorized;

    public function __construct()
    {
        $this->error = config('constants.api_status.error');
        $this->success = config('constants.api_status.success');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Display a listing of Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! Gate::allows('settings_manage')) {
            return abort(401);
        }

        $filters = Filters::all(Auth::User()->id, 'settings');

        return view('admin.settings.index', compact('filters'));
    }

    /**
     * Display a listing of Global Settings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {

            $filename = 'settings';
            $filters = getFilters($request->all());

            $apply_filter = checkFilters($filters, $filename);

            $records = [];
            $records['data'] = [];
            [$orderBy, $order] = getSortBy($request);
            // Get Total Records
            $iTotalRecords = Settings::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $settings = Settings::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

            if ($settings) {
                foreach ($settings as $setting) {

                    switch ($setting->slug) {
                        case 'sys-discounts':
                            $exploded = explode(':', $setting->data);

                            $setting->data = 'Min: '.$exploded[0].'%, Max: '.$exploded[1].'%';
                            break;
                        case 'sys-birthdaypromotion':
                            $exploded = explode(':', $setting->data);
                            $setting->data = 'Pre Days: '.$exploded[0].', Post Days: '.$exploded[1];
                            break;
                        case 'sys-list-mode':
                            $setting->data = config('constants.listing_array')[$setting->data];
                            break;
                        case 'sys-back-date-appointment':
                            $setting->data = $setting->data == 0 ? 'Disabled' : 'Enabled';
                            break;
                        case 'sys-current-sms-operator':
                            $setting->data = $setting->data == 1 ? config('constants.operator_array.1') : config('constants.operator_array.2');
                            break;
                        case 'sys-consultancy-invoice-medical-operator':
                            $setting->data = $setting->data == 1 ? config('constants.invoice_consultancy_medical_form.1') : config('constants.invoice_consultancy_medical_form.2');
                            break;
                        case 'sys-virtual-consultancy':
                            $setting->data = $setting->data == 1 ? config('constants.consultancy_type.1') : config('constants.consultancy_type.2');
                            break;
                    }
                }
                $records['data'] = $settings;

                $records['permissions'] = [
                    'edit' => Gate::allows('settings_edit'),
                ];
                $records['meta'] = [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $iDisplayLength,
                    'total' => $iTotalRecords,
                    'sort' => $order,
                ];
            }

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! Gate::allows('settings_manage')) {
            return abort(401);
        }

        $setting = new \stdClass();
        $setting->id = null;

        return view('admin.settings.create', compact('setting'));
    }

    /**
     * Store a newly created Permission in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! Gate::allows('settings_manage')) {
            return abort(401);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->all(),
            ]);
        }

        if (Settings::createRecord($request, Auth::User()->account_id)) {
            flash('Record has been created successfully.')->success()->important();

            return response()->json([
                'status' => 1,
                'message' => 'Record has been created successfully.',
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => 'Something went wrong, please try again later.',
            ]);
        }
    }

    /**
     * Validate form fields
     *
     * @return Validator $validator;
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
            'data' => 'required',
        ]);
    }

    /**
     * Get data for edit
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        try {
            if (! Gate::allows('settings_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $setting = Settings::getData($id);
            if (! $setting) {
                return ApiHelper::apiResponse($this->success, 'No Data Found', false);
            }

            $setting->field_type = 'text';
            switch ($setting->slug) {
                case 'sys-discounts':
                    $setting->field_type = 'minmax';
                    $exploded = explode(':', $setting->data);
                    $setting->min = $exploded[0];
                    $setting->max = isset($exploded[1]) == true ? $exploded[1] : '0';
                    break;
                case 'sys-birthdaypromotion':
                    $setting->field_type = 'prepost';
                    $exploded = explode(':', $setting->data);
                    $setting->pre = $exploded[0];
                    $setting->post = $exploded[1];
                    break;
                case 'sys-list-mode':
                    $setting->field_type = 'select';
                    $setting->list = config('constants.listing_array');
                    break;
                case 'sys-back-date-appointment':
                    $setting->field_type = 'select';
                    $setting->list = (object) ['Disabled', 'Enabled'];
                    break;
                case 'sys-current-sms-operator':
                    $setting->field_type = 'select';
                    $setting->list = config('constants.operator_array');
                    break;
                case 'sys-consultancy-invoice-medical-operator':
                    $setting->field_type = 'select';
                    $setting->list = config('constants.invoice_consultancy_medical_form');
                    break;
                case 'sys-virtual-consultancy':
                    $setting->field_type = 'select';
                    $setting->list = config('constants.consultancy_type');
                    break;
            }

            return ApiHelper::apiResponse($this->success, 'Success', true, $setting);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update Global Setting
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {

            if (! Gate::allows('settings_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }

            $setting = (Settings::find($id))->toArray();
            if ($setting['slug'] == 'sys-discounts' && $request->min > $request->max) {
                return ApiHelper::apiResponse($this->success, 'Min value is greater than Max value.', false);
            }

            if (Settings::updateRecord($id, $request, Auth::User()->account_id)) {
                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Remove Permission from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! Gate::allows('settings_manage')) {
            return abort(401);
        }

        $setting = Settings::getData($id);

        if (! $setting) {
            flash('Resource not found.')->error()->important();

            return redirect()->route('admin.settings.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Settings::isChildExists($id, Auth::User()->account_id)) {
            flash('Child records exist, unable to delete resource')->error()->important();

            return redirect()->route('admin.settings.index');
        }

        $setting->delete();

        flash('Record has been deleted successfully.')->success()->important();

        return redirect()->route('admin.settings.index');
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function inactive($id)
    {
        if (! Gate::allows('settings_manage')) {
            return abort(401);
        }
        $setting = Settings::getData($id);

        if (! $setting) {
            flash('Resource not found.')->error()->important();

            return redirect()->route('admin.settings.index');
        }

        $setting->update(['active' => 0]);

        flash('Record has been inactivated successfully.')->success()->important();

        return redirect()->route('admin.settings.index');
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function active($id)
    {
        if (! Gate::allows('settings_manage')) {
            return abort(401);
        }
        $setting = Settings::getData($id);

        if (! $setting) {
            flash('Resource not found.')->error()->important();

            return redirect()->route('admin.settings.index');
        }

        $setting->update(['active' => 1]);

        flash('Record has been inactivated successfully.')->success()->important();

        return redirect()->route('admin.settings.index');
    }
}
