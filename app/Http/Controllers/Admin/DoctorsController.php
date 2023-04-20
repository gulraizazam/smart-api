<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\NodesTree;
use App\Helpers\Widgets\LocationsWidget;
use App\Helpers\Widgets\ServiceWidget;
use App\Http\Controllers\Controller;
use App\Models\DoctorHasLocations;
use App\Models\Doctors;
use App\Models\Locations;
use App\Models\Resources;
use App\Models\ResourceTypes;
use App\Models\RoleHasUsers;
use App\Models\Services;
use App\Models\UserTypes;
use App\Models\User;
use Facade\FlareClient\Api;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class DoctorsController extends Controller
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
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|never
     */
    public function index()
    {
        if (!Gate::allows('doctors_manage')) {
            return abort(401);
        }

        return view('admin.doctors.index');
    }

    /**
     * Display a User As Doctor  in datatables.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            if (!Gate::allows('doctors_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $filename = 'doctors';

            $filters = getFilters($request->all());

            $apply_filter = checkFilters($filters, $filename);

            $records = [];
            $records['data'] = [];

            list($orderBy, $order) = getSortBy($request);
            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $users = User::getBulkData($ids);
                if ($users) {
                    foreach ($users as $user) {
                        // Check if child records exists or not, If exist then disallow to delete it.
                        if (!User::isExists($user->id, Auth::User()->account_id)) {
                            $user->delete();
                        }
                    }
                }
                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }
            $filterConditions = $this->getFilters($request, $apply_filter);
            if (!$filterConditions->get('status')) {
                return ApiHelper::apiException($filterConditions->get('data'));
            }

            $where = $filterConditions->get('data');
            if(\Illuminate\Support\Facades\Gate::allows("view_inactive_doctors")){
                $iTotalRecords = User::leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                ->groupBy('role_has_users.user_id')
                ->when(count($where), fn($q) => $q->where($where))->get();
            }else{
                $iTotalRecords = User::leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                ->groupBy('role_has_users.user_id')
                ->when(count($where), fn($q) => $q->where($where)->where('active',1))->get();
            }
            $iTotalRecords = $iTotalRecords->count();
                
            list($iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);
            if ($orderBy != '') {
                if ($orderBy == 'created_at') {
                    $orderBy = 'users.created_at';
                }
                Filters::put(Auth::User()->id, 'doctors', 'order_by', $orderBy);
                Filters::put(Auth::User()->id, 'doctors', 'order', $order);
            } else {
                if (Filters::get(Auth::User()->id, 'doctors', 'order_by') && Filters::get(Auth::User()->id, 'doctors', 'order')) {
                    $orderBy = Filters::get(Auth::User()->id, 'doctors', 'order_by');
                    $order = Filters::get(Auth::User()->id, 'doctors', 'order');

                    if ($orderBy == 'created_at') {
                        $orderBy = 'users.created_at';
                    }
                } else {
                    $orderBy = 'created_at';
                    $order = 'desc';
                    if ($orderBy == 'created_at') {
                        $orderBy = 'users.created_at';
                    }

                    Filters::put(Auth::User()->id, 'doctors', 'order_by', $orderBy);
                    Filters::put(Auth::User()->id, 'doctors', 'order', $order);
                }
            }
            if(\Illuminate\Support\Facades\Gate::allows("view_inactive_doctors")){
                $Users = User::leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                ->groupBy('role_has_users.user_id')
                ->when(count($where), fn($q) => $q->where($where))->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
            }else{
                $Users = User::leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                ->groupBy('role_has_users.user_id')
                ->when(count($where), fn($q) => $q->where($where)->where('active',1))->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
            }
            foreach ($Users as $user) {
                $user->roles = $user->user_roles()->pluck('name');
                $user->phone = GeneralFunctions::contactStatus($user->phone);
                $user->gender = config('constants.gender_array.' . $user->gender);
                $user->created = $user->created_at->format('F j,Y h:i A');
            }

            $records['data'] = $Users;
            $records["permissions"] = [
                'edit' => Gate::allows('doctors_edit'),
                'change_password' => Gate::allows('doctors_change_password'),
                'active' => Gate::allows('doctors_active'),
                'inactive' => Gate::allows('doctors_inactive'),
                'delete' => Gate::allows('doctors_destroy'),
                'allocate' => Gate::allows('doctors_allocate'),
                'contact' => Gate::allows('contact'),
            ];

            $filters = Filters::all(Auth::User()->id, 'doctors');
            $records['active_filters'] = $filters;
            $roles = Role::get()->pluck('name', 'id');
            $roles->prepend('All', '');
            $records['filter_values'] = [
                'roles' => $roles,
                'gender_array' => config('constants.gender_array'),
                'status' => config('constants.status'),

            ];
            $records["meta"] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];
            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    protected function getFilters(Request $request, $apply_filter)
    {
        try {
            $filters = getFilters($request->all());
            $where = [];
            list($orderBy, $order) = getSortBy($request);


            if (hasFilter($filters, 'name')) {
                $where[] = [
                    'users.name',
                    'like',
                    '%' . $filters['name'] . '%',
                ];
                Filters::put(Auth::User()->id, 'doctors', 'name', $filters['name']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, 'doctors', 'name');
                } else {
                    if (Filters::get(Auth::User()->id, 'doctors', 'name')) {
                        $where[] = [
                            'users.name',
                            'like',
                            '%' . Filters::get(Auth::User()->id, 'doctors', 'name') . '%',
                        ];
                    }
                }
            }
            if (hasFilter($filters, 'email')) {
                $where[] = [
                    'users.email',
                    'like',
                    '%' . $filters['email'] . '%',
                ];
                Filters::put(Auth::User()->id, 'doctors', 'email', $filters['email']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, 'doctors', 'email');
                } else {
                    if (Filters::get(Auth::User()->id, 'doctors', 'email')) {
                        $where[] = [
                            'users.email',
                            'like',
                            '%' . Filters::get(Auth::User()->id, 'doctors', 'email') . '%',
                        ];
                    }
                }
            }

            if (hasFilter($filters, 'phone')) {
                $where[] = [
                    'users.phone',
                    'like',
                    '%' . GeneralFunctions::cleanNumber($filters['phone']) . '%',
                ];
                Filters::put(Auth::User()->id, 'doctors', 'phone', $filters['phone']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, 'doctors', 'phone');
                } else {
                    if (Filters::get(Auth::User()->id, 'doctors', 'phone')) {
                        $where[] = [
                            'users.phone',
                            'like',
                            '%' . GeneralFunctions::cleanNumber(Filters::get(Auth::User()->id, 'doctors', 'phone')) . '%',
                        ];
                    }
                }
            }

            if (hasFilter($filters, 'gender')) {
                $where[] = [
                    'users.gender',
                    '=',
                    $filters['gender'],
                ];
                Filters::put(Auth::User()->id, 'doctors', 'gender', $filters['gender']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, 'doctors', 'gender');
                } else {
                    if (Filters::get(Auth::User()->id, 'doctors', 'gender')) {
                        $where[] = [
                            'users.gender',
                            '=',
                            Filters::get(Auth::User()->id, 'doctors', 'gender'),
                        ];
                    }
                }
            }
            if (hasFilter($filters, 'role_id')) {
                $where[] = [
                    'role_has_users.role_id',
                    '=',
                    $filters['role_id'],
                ];
                Filters::put(Auth::User()->id, 'doctors', 'role_id', $filters['role_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, 'doctors', 'role_id');
                } else {
                    if (Filters::get(Auth::User()->id, 'doctors', 'role_id')) {
                        $where[] = [
                            'role_has_users.role_id',
                            '=',
                            Filters::get(Auth::User()->id, 'doctors', 'role_id'),
                        ];
                    }
                }
            }
            $where[] = [
                'users.user_type_id',
                '=',
                config('constants.asthatic_operator_id'),
            ];
            $where[] = [
                'users.account_id',
                '=',
                Auth::User()->account_id,
            ];
            $where[] = [
                'users.resource_type_id',
                '=',
                2,
            ];
            if (hasFilter($filters, 'created_from')) {
                $where[] = [
                    'users.created_at',
                    '>=',
                    $filters['created_from'] . ' 00:00:00',
                ];
                Filters::put(Auth::User()->id, 'doctors', 'created_from', $filters['created_from']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, 'doctors', 'created_from');
                } else {

                    if (Filters::get(Auth::User()->id, 'doctors', 'created_from')) {
                        $where[] = [
                            'users.created_at',
                            '>=',
                            Filters::get(Auth::User()->id, 'doctors', 'created_from') . ' 00:00:00',
                        ];
                    }
                }
            }
            if (hasFilter($filters, 'created_to')) {
                $where[] = [
                    'users.created_at',
                    '<=',
                    $filters['created_to'] . ' 23:59:59'
                ];
                Filters::put(Auth::User()->id, 'doctors', 'created_to', $filters['created_to']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, 'doctors', 'created_to');
                } else {
                    if (Filters::get(Auth::User()->id, 'doctors', 'created_to')) {
                        $where[] = [
                            'users.created_at',
                            '<=',
                            Filters::get(Auth::User()->id, 'doctors', 'created_to') . ' 23:59:59',
                        ];
                    }
                }
            }

            if (hasFilter($filters, 'status')) {
                $where[] = [
                    'users.active',
                    '=',
                    $filters['status'],
                ];
                Filters::put(Auth::user()->id, 'doctors', 'status', $filters['status']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::user()->id, 'doctors', 'status');
                } else {
                    if (Filters::get(Auth::user()->id, 'doctors', 'status') == 0 && Filters::get(Auth::user()->id, 'doctors', 'status') == 1) {
                        $where[] = [
                            'users.active',
                            '=',
                            Filters::get(Auth::user()->id, 'doctors', 'status'),
                        ];
                    }
                }
            }
            return collect(['status' => true, 'message' => null, 'data' => $where]);
        } catch (\Exception $e) {
            return collect(['status' => false, 'message' => null, 'data' => $e]);
        }
    }

    /**
     * Show the form for creating new User As Doctors.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        if (!Gate::allows('doctors_create')) {
            return abort(401);
        }
        $doctor = new \stdClass();

        $doctor->gender = null;
        $doctor->phone = null;

        $userstype = UserTypes::getUserType_for_Doctor();
        $userstype->prepend('Select a User Type', '');

        $locations = Locations::where([
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1'],
        ])->get()->pluck('full_address', 'id');

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id, true, true);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;

        $DoctorServices = [];

        $roles = Role::get()->pluck('name', 'id');

        return ApiHelper::apiResponse($this->success, 'Data found', true, [
            'locations' => $locations,
            'userstype' => $userstype,
            'user' => $doctor,
            'Services' => $Services,
            'DoctorServices' => $DoctorServices,
            'roles'  => $roles
        ]);

    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {

            if (!Gate::allows('doctors_create')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }

            $validator = $this->verifyFields($request);

            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            $data = $request->all();
            $resourcetype_id = ResourceTypes::where('name', '=', 'doctor')->first();

            $data['resource_type_id'] = $resourcetype_id->id;
            $data['user_type_id'] = Config::get('constants.practitioner_id');
            $data['account_id'] = Auth::User()->account_id;
            $data['phone'] = GeneralFunctions::cleanNumber($request->phone);

            if ($user = User::createRecord($data)) {
                $roles = $request->input('roles') ? $request->input('roles') : [];
                $user->assignRole($roles);

                // Check if role exist and are set then assign role to users
                if ($request->get('roles') && is_array($request->get('roles'))) {
                    $roles = $request->get('roles');
                    $role_has_users = [];
                    foreach ($roles as $role) {
                        $roleid = DB::table('roles')->select('id')->where('id', '=', $role)->first();
                        $role_has_users = [
                            'role_id' => $roleid->id,
                            'user_id' => $user->id,
                        ];
                        // Insert assigned role to users
                        RoleHasUsers::createRecord($role_has_users, $user);
                    }
                }

                $resource = new Resources();
                $resource->name = $request->name;
                $resource->account_id = Auth::User()->account_id;
                $resource->resource_type_id = $resourcetype_id->id;
                $resource->external_id = $user->id;
                $resource->save();
                return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
            }
            return ApiHelper::apiResponse($this->error, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }


    /**
     * Validate form fields.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function verifyFields(Request $request)
    {
        $rules = [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,NULL,id,deleted_at,NULL',
            'phone' => 'required',
            'gender' => 'required',
            'password' => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/',
        ];

        $messages = [
            'name.required' => 'Name field is required',
            'email.required' => 'Email field is required',
            'phone.required' => 'Phone field is required',
            'password.required' => 'Password field is required',
            'password.min' => 'password must be at least 8 characters',
            'password.regex' => 'Password must be a combination of numbers, upper, lower, and special characters',
        ];

        return Validator::make($request->all(), $rules, $messages);
    }

    /**
     * Show the form for changing password.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function changePassword($id)
    {
        try {
            if (!Gate::allows('doctors_change_password')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $user = User::getData($id);
            if (!$user) {
                return ApiHelper::apiResponse($this->success, 'No Record Found!', false);
            }
            return ApiHelper::apiResponse($this->success, 'No Record Found!', true, $user);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update User Password in storage or Database.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function savePassword(Request $request)
    {
        try {
            if (!Gate::allows('doctors_change_password')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $data = [];
            $validator = $this->verifyPasswordFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            $id = $request->get('id');

            $data['password'] = bcrypt($request->get('password'));

            $result = User::updateRecord($data, $id);
            if ($result) {
                return ApiHelper::apiResponse($this->success, 'Password has been changed successfully.');
            }
            return ApiHelper::apiResponse($this->success, 'Are you mad? what were you trying to do? :@.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Show the form for editing User.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        if (!Gate::allows('doctors_edit')) {
            return abort(401);
        }
        $doctor = User::getData($id);

        if ($doctor == null) {
            return view('error');
        } else {
            $userstype = UserTypes::getUserType_for_Doctor();
            $userstype->prepend('Select a User Type', '');

            $user_has_locations = $doctor->user_has_locations->pluck('location_id');

            $DoctorServices = $doctor->doctor_has_services()->pluck('service_id')->toArray();

            if (!$user_has_locations) {
                $user_has_locations = [];
            }

            if (!$DoctorServices) {
                $DoctorServices = [];
            }

            /* Create Nodes with Parents */
            $parentGroups = new NodesTree();
            $parentGroups->current_id = -1;
            $parentGroups->build(0, Auth::User()->account_id, true, true);
            $parentGroups->toList($parentGroups, -1);

            $Services = $parentGroups->nodeList;

            $locations = Locations::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
            ])->get()->pluck('full_address', 'id');

            $roles = Role::get()->pluck('name', 'id');
            $user_roles = $doctor->user_roles()->pluck('id');

            return ApiHelper::apiResponse($this->success, 'Data found', true, [
                'user' => $doctor,
                'user_has_locations' => $user_has_locations,
                'locations' => $locations,
                'userstype' => $userstype,
                'DoctorServices' => $DoctorServices,
                'Services' => $Services,
                'roles' => $roles,
                'user_roles' => $user_roles
            ]);

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'roles' => $roles,
                'user' => $doctor,
                'locations' => $locations,
                'roles_commissions' => $roles_commissions,
                'user_has_locations' => $user_has_locations,
                'user_roles' => $user_roles
            ]);
        }
    }


    /**
     * Update Doctor in storage.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            if (!Gate::allows('doctors_edit')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $validator = $this->verifyUpdateFields($request, $id);

            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->errors()->first(), false, $validator->errors());
            }
            $user = User::findOrFail($id);
            if ($request->input('phone') == '***********') {
                $request->merge(['phone' => $request->input('old_phone')]);
            }
            $request->request->remove('old_phone');
            $data = $request->all();
            $data['phone'] = GeneralFunctions::cleanNumber($request->phone);

            if ($user = User::updateRecord($data, $id)) {
                $roles = $request->input('roles') ? $request->input('roles') : [];
                $user->syncRoles($roles);

                // Check if locations exist and are set then assign centres to User
                if ($request->get('roles') && is_array($request->get('roles'))) {
                    // Destroy if user has locations
                    $user->role_has_users()->forceDelete();

                    $roles = $request->get('roles');
                    $role_has_users = [];
                    foreach ($roles as $role) {
                        $roleid = DB::table('roles')->select('id')->where('id', '=', $role)->first();
                        $role_has_users = [
                            'role_id' => $roleid->id,
                            'user_id' => $user->id,
                        ];
                        // Insert assigned centres to User
                        RoleHasUsers::updateRecord($role_has_users, $user);
                    }
                }
                $resource_doctor = Resources::where('external_id', '=', $user->id)->first();
                if (!$resource_doctor) {

                    $resourcetype_id = ResourceTypes::where('name', '=', 'doctor')->first();

                    Resources::create([
                        'name' => $user->name ?? '',
                        'account_id' => Auth::User()->account_id ?? '',
                        'resource_type_id' => $resourcetype_id->id ?? '',
                        'external_id' => $user->id ?? '',
                        'active' => 1,
                    ]);
                }
                if ($resource_doctor) {
                    $resource_doctor->name = $request->name;
                    $resource_doctor->save();
                }
                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }
            return ApiHelper::apiResponse($this->error, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }


    /**
     * Remove Doctor from storage.
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|never
     */
    public function destroy($id)
    {
        try {
            if (!Gate::allows('doctors_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $response = User::deleteRecord1($id);
            return ApiHelper::apiResponse($this->success, $response->get('message'), $response->get('status'));

        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Display lcoation to add service for doctor.
     *
     * @param int $id
     */
    public function displaylocation($id)
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $doctor = User::find($id);
            $location = LocationsWidget::generateDropDownArray(Auth::User()->account_id);
            $doctor_has_location = DoctorHasLocations::with(['service', 'location.city'])->where('user_id', '=', $doctor->id)->get();

//            return view('admin.doctors.location', compact('doctor', 'location', 'doctor_has_location'));
            return ApiHelper::apiResponse($this->success, 'Service Allocated', true, [
                'doctor' => $doctor,
                'location' => $location,
                'doctor_has_location' => $doctor_has_location,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * display services against location id.
     *
     * @param request
     */
    public function getservices(Request $request)
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $serive = ServiceWidget::generateServiceArrayArray($request, Auth::User()->account_id);

            $myarray = ['services' => $serive, 'locaiton_id_1' => $request->id];
            return ApiHelper::apiResponse($this->success, 'Success', true, $myarray);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * save services against location id.
     *
     * @param  $request
     */
    public function saveservices(Request $request)
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $has_services = '';
            $myString = $request->id;
            $myArray = explode(',', $myString);
            $data = [];
            $data['user_id'] = $request->doctor_id;
            $data['location_id'] = $myArray[0];
            $data['service_id'] = $myArray[1];
            $service= Services::where(['id' => $data['service_id']])->first();
            $data['end_node'] = $service->end_node;
            
            $checked_service = DoctorHasLocations::where([
                'location_id' => $myArray[0],
                'service_id' => $myArray[1],
                'user_id' => $request->doctor_id,
            ])->count();
            if ($checked_service == '0') {
                $query = DoctorHasLocations::
                where([
                    'location_id' => $myArray[0],
                    'user_id' => $request->doctor_id,
                ]);
                $checked = $query->with('service')->get();
                if($checked_service == '0' && count($checked) == '0'){
                    $has_services = 'new';
                } else {
                    foreach($checked->toArray() as $value){
                        if($value['service']['slug'] == 'all'){
                            $has_services = 'all';
                        } elseif($service->parent_id == $value['service']['id']){
                            $has_services = 'parent';
                        } elseif($service->id == $value['service']['parent_id']){
                            $has_services = 'child';
                        } else{
                            $has_services = 'equal';
                        }
                    }
                }
                if($has_services == 'new'){
                    $record = DoctorHasLocations::create($data);
                } elseif($service->slug == 'all'){
                    $query->delete();
                    $record = DoctorHasLocations::create($data);
                } elseif($has_services == 'child'){
                    $query->whereHas('service', fn($q) => $q->where(['parent_id' => $service->id]))->delete();
                    $record = DoctorHasLocations::create($data);
                } elseif($has_services == 'equal'){
                    $record = DoctorHasLocations::create($data);
                } elseif($has_services == 'all' || $has_services == 'parent'){
                    return ApiHelper::apiResponse($this->success, 'Parent Service / All Service already exist!', false);
                } else {
                    return ApiHelper::apiResponse($this->success, 'Service not found!', false);
                }
                $record_location_name = $record->location->city->name . '-' . $record->location->name;
                $record_service_name = $record->service->name;
                $myarray = ['record' => $record, 'record_location_name' => $record_location_name, 'record_service_name' => $record_service_name];
                return ApiHelper::apiResponse($this->success, 'Success', true, $myarray);
            }
            return ApiHelper::apiResponse($this->success, 'Service already exist!', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * delete serive.
     *
     * @param request
     */
    public function deleteservices(Request $request)
    {
        try {
            if (!Gate::allows('doctors_allocate')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            DoctorHasLocations::find($request->id)->delete();
            return ApiHelper::apiResponse($this->success, 'Doctor location has been deleted!', true, ['id' => $request->id]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }


    /**
     * Validate create form fields.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function verifyPasswordFields(Request $request)
    {
        $rules = [
            'password' => 'required|confirmed|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/',
        ];

        $messages = [
            'password.required' => 'Password field is required',
            'password.min' => 'password must be at least 8 characters',
            'password.regex' => 'Password must be a combination of numbers, upper, lower, and special characters',
        ];

        return $validator = Validator::make($request->all(), $rules, $messages);
    }


    /**
     * Validate create form fields.
     *
     * @param Request $request
     * @param $id
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function verifyUpdateFields(Request $request, $id)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'unique:users,email,' . $id,
            'phone' => 'required',
            'gender' => 'required',
        ]);
    }


    /**
     * Change status of Record from storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        try {
            if ($request->status == 0) {
                if (!Gate::allows('doctors_inactive')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
                }
            } elseif ($request->status == 1) {
                if (!Gate::allows('doctors_active')) {
                    return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
                }
            }
            $response = User::activeRecord($request->id, $request->status);
            if ($response) {
                return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
            }
            return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
