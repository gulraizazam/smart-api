<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FileUploadTownRequest;
use App\Models\Cities;
use App\Models\Towns;
use Auth;
use Carbon\Carbon;
use File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Validator;

class TownController extends Controller
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
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! Gate::allows('towns_manage')) {
            return abort(401);
        }

        return view('admin.towns.index');
    }

    /**
     * Display a listing of towns.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request)
    {
        $filename = 'towns';

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, $filename);

        $records = [];
        $records['data'] = [];

        $filters = getFilters($request->all());

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $towns = Towns::getBulkData($ids);
            if ($towns) {
                foreach ($towns as $town) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! Towns::isChildExists($town->id, Auth::User()->account_id)) {
                        $town->delete();
                    }
                }
            }
            $records['status'] = true;
            $records['message'] = 'Records has been deleted successfully!';
        }

        [$orderBy, $order] = getSortBy($request);

        // Get Total Records
        $iTotalRecords = Towns::getTotalRecords($request, Auth::User()->account_id, $apply_filter);

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $towns = Towns::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter);

        if ($towns) {
            $records['data'] = $towns;

            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];

            $records['permissions'] = [
                'edit' => Gate::allows('towns_edit'),
                'delete' => Gate::allows('towns_destroy'),
                'active' => Gate::allows('towns_active'),
                'inactive' => Gate::allows('towns_inactive'),
            ];
        }

        $filters = Filters::all(Auth::User()->id, 'towns');

        $cities = Cities::where([
            ['account_id', '=', Auth::User()->account_id],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
        ])->get()->pluck('name', 'id');
        $cities->prepend('Select a City', '');

        $records['filter_values'] = [
            'cities' => $cities,
            'status' => config('constants.status'),
        ];

        $records['active_filters'] = $filters;

        return response()->json($records);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        if (! Gate::allows('towns_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $cities = Cities::where([
            ['account_id', '=', Auth::User()->account_id],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
        ])->get()->pluck('full_name', 'id');
        $cities->prepend('Select a City', '');

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'cities' => $cities,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if (! Gate::allows('towns_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }

        if (Towns::createRecord($request, Auth::User()->account_id)) {
            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        } else {
            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
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
            'city_id' => 'required',
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! Gate::allows('towns_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $town = Towns::getData($id);

        if (! $town) {
            return ApiHelper::apiResponse($this->unauthorized, 'Resource not found.', false);
        }

        $cities = Cities::where([
            ['account_id', '=', Auth::User()->account_id],
            ['slug', '=', 'custom'],
            ['active', '=', '1'],
            ['is_featured', '=', '1'],
        ])->get()->pluck('full_name', 'id');
        $cities->prepend('Select a City', '');

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'cities' => $cities,
            'town' => $town,
        ]);
    }

    /**
     * Update town.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! Gate::allows('towns_edit')) {
            return abort(401);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->all(),
            ]);
        }

        if (Towns::updateRecord($id, $request, Auth::User()->account_id)) {
            flash('Record has been updated successfully.')->success()->important();

            return response()->json([
                'status' => 1,
                'message' => 'Record has been updated successfully.',
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'message' => 'Something went wrong, please try again later.',
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        if (! Gate::allows('towns_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $response = Towns::DeleteRecord($id);

        return ApiHelper::apiResponse($this->success, $response['message'], $response['status']);
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function status(Request $request)
    {
        if (! Gate::allows('towns_active')) {
            return abort(401);
        }

        $response = Towns::activeRecord($request->id, $request->status);

        if ($response) {
            return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
        }

        return ApiHelper::apiResponse($this->success, 'Resource not found.', false);

    }

    /**
     * Import Town.
     */
    public function importTowns(Request $request)
    {
        if (! Gate::allows('towns_import')) {
            flash('You are not authorized to access this resource.')->error()->important();

            return redirect()->route('admin.towns.index');
        }

        return view('admin.towns.import');
    }

    /**
     * Upload excel file.
     *
     * @param  \App\Http\Requests\Admin\FileUploadLeadsRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function uploadLeads(FileUploadTownRequest $request)
    {
        if (! Gate::allows('towns_import')) {
            flash('You are not authorized to access this resource.')->error()->important();

            return redirect()->route('admin.Towns.index');
        }

        if ($request->hasfile('towns_file')) {
            // Check if directory not exists then create it
            $dir = public_path('/towndata');
            if (! File::isDirectory($dir)) {
                // path does not exist so create directory
                File::makeDirectory($dir, 777, true, true);
                File::put($dir.'/index.html', 'Direct access is forbidden');
            }

            $File = $request->file('towns_file');

            // Store File Information
            $name = str_replace('.'.$File->getClientOriginalExtension(), '', $File->getClientOriginalName());
            $ext = $File->getClientOriginalExtension();
            $full_name = $File->getClientOriginalName();
            $full_name_new = $name.'-'.rand(11111111, 99999999).'.'.$ext;

            $File->move($dir, $full_name_new);

            // Read File and dump data
            $SpreadSheet = IOFactory::load($dir.DIRECTORY_SEPARATOR.$full_name_new);
            $SheetData = $SpreadSheet->getActiveSheet(0)->toArray(null, true, true, true);

            if (count($SheetData)) {
                if (
                    isset($SheetData[1])
                    && (
                        trim(strtolower($SheetData[1]['A'])) == 'name' &&
                        trim(strtolower($SheetData[1]['B'])) == 'city' &&
                        trim(strtolower($SheetData[1]['C'])) == 'active' &&
                        trim(strtolower($SheetData[1]['D'])) == 'account'
                    )) {

                    $Cities = Cities::where(['account_id' => Auth::User()->account_id])->get()->pluck('id', 'name');

                    $TownData = [];
                    $count = 0;
                    foreach ($SheetData as $SingleRow) {
                        if ($count != 0) {
                            $city_info = Cities::where('name', '=', $SingleRow['B'])->first();
                            $TownData[] = [
                                'name' => $SingleRow['A'],
                                'city_id' => $city_info->id,
                                'active' => $SingleRow['C'],
                                'account_id' => $SingleRow['D'],
                                'created_at' => Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now())->format('Y-m-d'),
                                'updated_at' => Carbon::createFromFormat('Y-m-d H:i:s', Carbon::now())->format('Y-m-d'),
                            ];
                        }
                        $count++;
                    }

                    Towns::insert($TownData);

                    return redirect()->route('admin.towns.index');
                } else {
                    flash('Invalid data provided. Pattern should: name, city, active, account')->error()->important();
                }
            } else {
                flash('No input file specified..')->error()->important();
            }

            return redirect()->route('admin.towns.import');
        }
    }
}
