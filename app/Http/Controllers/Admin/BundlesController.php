<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\BundleHasServices;
use App\Models\Bundles;
use App\Models\Services;
use App\Models\TaxTreatmentType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class BundlesController extends Controller
{
    public $success;

    public $error;

    public $unauthorized;

    public function __construct()
    {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Display a listing of Packages.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|never
     */
    public function index()
    {
        if (! Gate::allows('packages_manage')) {
            return abort(401);
        }

        return view('admin.bundles.index');
    }

    /**
     * Display a listing of Packages.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            if (! Gate::allows('packages_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, 'bundles');

            $records = [];
            $records['data'] = [];
            [$orderBy, $order] = getSortBy($request);

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $Bundles = Bundles::getBulkData($ids);
                if ($Bundles) {
                    foreach ($Bundles as $city) {
                        // Check if child records exists or not, If exist then disallow to delete it.
                        if (! Bundles::isChildExists($city->id, Auth::User()->account_id)) {
                            $city->delete();
                        }
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            // Get Total Records
            $iTotalRecords = Bundles::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $Bundles = Bundles::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

            foreach ($Bundles as $bundle) {
                $bundle->price = number_format($bundle->price, 2);
                $bundle->apply_discount = ($bundle->apply_discount) ? 'Yes' : 'No';
                $bundle->start = $bundle->start ? Carbon::parse($bundle->start)->format('D M, j Y') : null;
                $bundle->end = $bundle->end ? Carbon::parse($bundle->end)->format('D M, j Y') : null;
            }

            $records['data'] = $Bundles;
            $records['permissions'] = [
                'edit' => Gate::allows('packages_edit'),
                'delete' => Gate::allows('packages_destroy'),
                'active' => Gate::allows('packages_active'),
                'inactive' => Gate::allows('packages_inactive'),
                'details' => Gate::allows('packages_manage'),
            ];

            $filters = Filters::all(Auth::User()->id, 'bundles');
            $records['active_filters'] = $filters;
            $discounts = ['0' => 'No', '1' => 'Yes'];
            $tax_treatment_types = TaxTreatmentType::get();
            $services = Services::getServices();
            $records['filter_values'] = [
                'status' => config('constants.status'),
                'discounts' => $discounts,
                'tax_treatment_types' => $tax_treatment_types,
                'services' => $services,
            ];
            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];

            return response()->json($records);
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
        if (! Gate::allows('packages_create')) {
            return abort(401);
        }

        $services = Services::getServices();

        $tax_treatment_types = TaxTreatmentType::get();

        return view('admin.bundles.create', compact('services', 'tax_treatment_types'));
    }

    /**
     * Store a newly created Package in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            if (! Gate::allows('packages_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            if ($request->start <= $request->end) {
                if (Bundles::createRecord($request, Auth::User()->account_id)) {
                    return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
                }

                return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Date range invalid, Kindly define again', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Validate form fields
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
            'price' => 'required|numeric|min:0',
            'total_services' => 'required|numeric|min:1',
            'service_id' => 'required|array',
            //'tax_treatment_type_id' => 'required'
        ]);
    }

    /**
     * Show the form for editing Package.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        try {
            if (! Gate::allows('packages_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $bundle = Bundles::getData($id);
            if (! $bundle) {
                return ApiHelper::apiResponse($this->success, 'No record found!', false);
            }
            $services = Services::getServices();
            $relationships = BundleHasServices::where([
                'bundle_id' => $bundle->id,
            ])->select('service_id')->get();
            $bundle_services = collect(new Services());
            if ($relationships->count()) {
                $bundle_services = Services::whereIn('id', $relationships)->where(['account_id' => Auth::User()->account_id])->get()->getDictionary();
            }
            $tax_treatment_types = TaxTreatmentType::get();

            return ApiHelper::apiResponse($this->success, 'Success', true, ['bundle' => $bundle, 'services' => $services, 'bundle_services' => $bundle_services, 'relationships' => $relationships, 'tax_treatment_types' => $tax_treatment_types]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update Package in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            if (! Gate::allows('packages_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            if ($request->start <= $request->end) {
                if (Bundles::updateRecord($id, $request, Auth::User()->account_id)) {
                    return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
                }

                return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Date range invalid, Kindly define again', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Show Lead detail.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail($id)
    {
        try {
            if (! Gate::allows('packages_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $bundle = Bundles::findOrFail($id);

            if (! $bundle) {
                return ApiHelper::apiResponse($this->success, 'No record found!', false);
            }

            $relationships = BundleHasServices::where([
                'bundle_id' => $bundle->id,
            ])->select('service_id')->get();

            $bundle_services = collect(new Services());

            if ($relationships->count()) {
                $bundle_services = Services::whereIn('id', $relationships)->where(['account_id' => Auth::User()->account_id])->get()->getDictionary();
            }

            return ApiHelper::apiResponse($this->success, 'Success', true, ['bundle' => $bundle, 'bundle_services' => $bundle_services, 'relationships' => $relationships]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Remove Package from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            if (! Gate::allows('packages_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $response = Bundles::DeleteRecord($id);

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Change status of Package
     */
    public function status(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if ($request->status == 0) {
                if (! Gate::allows('regions_inactive')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
                }
                $response = Bundles::inactiveRecord($request->id);
            } else {
                if (! Gate::allows('regions_active')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
                }
                $response = Bundles::activeRecord($request->id);
            }

            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
