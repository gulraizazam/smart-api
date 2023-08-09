<?php

namespace App\Http\Controllers\Admin;

use DateTime;
use Validator;
use Carbon\Carbon;
use App\Helpers\ACL;
use App\Models\User;
use App\Helpers\Filters;
use App\Models\Patients;
use App\Models\Locations;
use App\Models\RoleHasUsers;
use Illuminate\Http\Request;
use App\HelperModule\ApiHelper;
use App\Models\UserHasLocations;
use App\Helpers\GeneralFunctions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Config;
use App\Helpers\Widgets\LocationsWidget;
use Illuminate\Contracts\Encryption\DecryptException;

class UsersController extends Controller
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
     * Display a listing of User.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! Gate::allows('users_manage')) {
            return abort(401);
        }

        return view('admin.users.index');
    }

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request)
    {
        $filename = 'users';

        $filters = getFilters($request->all());
        $apply_filter = checkFilters($filters, $filename);

        $records = [];
        $records['data'] = [];

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $Users = User::whereIn('id', $ids);
            if ($Users) {
                $Users->delete();
            }
            $records['status'] = true;
            $records['message'] = 'Records has been deleted successfully!';
        }
        if (hasFilter($filters, 'created_at')) {
            $date_range = explode(' - ', $filters['created_at']);
            $start_date_time = date('Y-m-d H:i:s', strtotime($date_range[0]));
            $end_date_string = new DateTime($date_range[1]);
            $end_date_string->setTime(23, 59, 0);
            $end_date_time = $end_date_string->format('Y-m-d H:i:s');
        } else {
            $start_date = null;
            $end_date = null;
        }

        $where = [];
        [$orderBy, $order] = getSortBy($request);
        if (Auth::user()->account_id && Auth::user()->account_id != '') {
            $where[] = [
                'users.account_id',
                '=',
                Auth::user()->account_id,
            ];
            Filters::put(Auth::user()->id, $filename, 'account_id', Auth::user()->account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'account_id')) {
                    $where[] = [
                        'users.account_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'account_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'name')) {
            $where[] = [
                'users.name',
                'like',
                '%'.$filters['name'].'%',
            ];
            Filters::put(Auth::user()->id, $filename, 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'name');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'name')) {
                    $where[] = [
                        'users.name',
                        'like',
                        '%'.Filters::get(Auth::user()->id, $filename, 'name').'%',
                    ];
                }
            }
        }
        if (hasFilter($filters, 'email')) {
            $where[] = [
                'users.email',
                'like',
                '%'.$filters['email'].'%',
            ];
            Filters::put(Auth::user()->id, $filename, 'email', $filters['email']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'email');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'email')) {
                    $where[] = [
                        'users.email',
                        'like',
                        '%'.Filters::get(Auth::user()->id, $filename, 'email').'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'phone')) {
            $where[] = [
                'users.phone',
                'like',
                '%'.GeneralFunctions::cleanNumber($filters['phone']).'%',
            ];
            Filters::put(Auth::user()->id, $filename, 'phone', $filters['phone']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'phone');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'phone')) {
                    $where[] = [
                        'users.phone',
                        'like',
                        '%'.GeneralFunctions::cleanNumber(
                            Filters::get(Auth::user()->id, $filename, 'phone')
                        ).'%',
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
            Filters::put(Auth::user()->id, $filename, 'gender', $filters['gender']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'gender');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'gender')) {
                    $where[] = [
                        'users.gender',
                        'like',
                        Filters::get(Auth::user()->id, $filename, 'gender'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'commission')) {
            $where[] = [
                'commission',
                '=',
                $filters['commission'],
            ];
            Filters::put(Auth::user()->id, $filename, 'commission', $filters['commission']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'commission');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'commission')) {
                    $where[] = [
                        'commission',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'commission'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'location_id')) {
            $where[] = [
                'user_has_locations.location_id',
                '=',
                $filters['location_id'],
            ];
            Filters::put(Auth::user()->id, $filename, 'location_id', $filters['location_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'location_id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'location_id')) {
                    $where[] = [
                        'user_has_locations.location_id',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'location_id'),
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
            Filters::put(Auth::user()->id, $filename, 'role_id', $filters['role_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'role_id');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'role_id')) {
                    $where[] = [
                        'role_has_users.role_id',
                        '=',
                        Filters::get(Auth::user()->id, $filename, 'role_id'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_at')) {
            $where[] = [
                'users.created_at',
                '>=',
                $start_date_time,
            ];
            $where[] = [
                'users.created_at',
                '<=',
                $end_date_time,
            ];
            Filters::put(Auth::user()->id, $filename, 'created_at', $filters['created_at']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'created_at');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'created_at')) {
                    $where[] = [
                        'users.created_at',
                        '>=',
                        Filters::get(Auth::user()->id, $filename, 'created_at'),
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
            Filters::put(Auth::user()->id, $filename, 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'status');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'status') == 0 || Filters::get(Auth::user()->id, $filename, 'status') == 1) {
                    if (Filters::get(Auth::user()->id, $filename, 'status') != null) {
                        $where[] = [
                            'users.active',
                            '=',
                            Filters::get(Auth::user()->id, $filename, 'status'),
                        ];
                    }
                }
            }
        }
        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_users')) {
                $iTotalRecords = count(User::leftJoin('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                    ->leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                    ->groupBy('user_has_locations.user_id', 'role_has_users.user_id')
                    ->whereNotIn('users.user_type_id', [Config::get('constants.practitioner_id'), Config::get('constants.patient_id')])
                    ->where([
                        [$where],
                        ['account_id', '=', Auth::User()->account_id],
                    ])->get(['users.id']));
            } else {
                $iTotalRecords = count(User::leftJoin('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                    ->leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                    ->groupBy('user_has_locations.user_id', 'role_has_users.user_id')
                    ->whereNotIn('users.user_type_id', [Config::get('constants.practitioner_id'), Config::get('constants.patient_id')])
                    ->where('users.active', 1)
                    ->where([
                        [$where],
                        ['account_id', '=', Auth::User()->account_id],
                    ])->get(['users.id']));
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_users')) {
                $iTotalRecords = count(User::leftJoin('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                    ->leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                    ->groupBy('user_has_locations.user_id', 'role_has_users.user_id')
                    ->whereNotIn('users.user_type_id', [Config::get('constants.practitioner_id'), Config::get('constants.patient_id')])
                    ->where([
                        [$where],
                        ['account_id', '=', Auth::User()->account_id],
                    ])->get(['users.id']));
            } else {
                $iTotalRecords = count(User::leftJoin('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                    ->leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                    ->groupBy('user_has_locations.user_id', 'role_has_users.user_id')
                    ->where('users.active', 1)
                    ->whereNotIn('users.user_type_id', [Config::get('constants.practitioner_id'), Config::get('constants.patient_id')])
                    ->where([
                        [$where],
                        ['account_id', '=', Auth::User()->account_id],
                    ])->get(['users.id']));
            }
        }
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);
        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_users')) {
                $Users = User::leftJoin('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                    ->leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                    ->groupBy('user_has_locations.user_id', 'role_has_users.user_id')
                    ->whereNotIn('users.user_type_id', [Config::get('constants.practitioner_id'), Config::get('constants.patient_id')])
                    ->where('email', '!=', 'superadmin@redsignal.net')
                    ->where([
                        [$where],
                        ['account_id', '=', Auth::User()->account_id],
                    ])->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
            } else {
                $Users = User::leftJoin('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                    ->leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                    ->groupBy('user_has_locations.user_id', 'role_has_users.user_id')
                    ->whereNotIn('users.user_type_id', [Config::get('constants.practitioner_id'), Config::get('constants.patient_id')])
                    ->where('users.active', 1)
                    ->where('email', '!=', 'superadmin@redsignal.net')
                    ->where([
                        [$where],
                        ['account_id', '=', Auth::User()->account_id],
                    ])->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_users')) {
                $Users = User::leftJoin('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                    ->leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                    ->groupBy('user_has_locations.user_id', 'role_has_users.user_id')
                    ->whereNotIn('users.user_type_id', [Config::get('constants.practitioner_id'), Config::get('constants.patient_id')])
                    ->where('email', '!=', 'superadmin@redsignal.net')
                    ->where([
                        'account_id' => Auth::User()->account_id,
                    ])->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
            } else {
                $Users = User::leftJoin('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                    ->leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                    ->groupBy('user_has_locations.user_id', 'role_has_users.user_id')
                    ->whereNotIn('users.user_type_id', [Config::get('constants.practitioner_id'), Config::get('constants.patient_id')])
                    ->where('users.active', 1)
                    ->where('email', '!=', 'superadmin@redsignal.net')
                    ->where([
                        'account_id' => Auth::User()->account_id,
                    ])->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
            }
        }
        $records = $this->getExtraData($records);
        if ($Users->count()) {
            $index = 0;
            $loc = Locations::select('*')->get()->getDictionary();
            foreach ($Users as $user) {
                $locations = [];
                $userhaslocation = $user->user_has_locations ? $user->user_has_locations->pluck('location_id') : [];
                $user_has_locations = LocationsWidget::generatelocationArrayEdit($userhaslocation, Auth::User()->account_id, $user);
                if ($user_has_locations) {
                    foreach ($user_has_locations as $location) {
                        $locationchecked = Locations::find($location);
                        if ($locationchecked != null) {
                            $locations[] = $loc[$location]->name ?? '';
                        }
                    }
                }
                $records['data'][$index] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => GeneralFunctions::contactStatus($user->phone),
                    'commission' => $user->commission.'%',
                    'gender' => view('admin.users.genderselection', compact('user'))->render(),
                    'locations' => $locations,
                    'roles' => $user->user_roles()->pluck('name'),
                    'created_at' => Carbon::parse($user->created_at)->format('F j,Y h:i A'),
                    'active' => $user->active,
                ];
                $index++;
            }
            $records['permissions'] = [
                'edit' => Gate::allows('users_edit'),
                'change_password' => Gate::allows('users_change_password'),
                'active' => Gate::allows('users_active'),
                'inactive' => Gate::allows('users_inactive'),
                'delete' => Gate::allows('users_destroy'),
                'contact' => Gate::allows('contact'),
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
    }

    private function getExtraData($records = [])
    {
        $locations = Locations::where([['active', '=', '1'], ['account_id', '=', Auth::User()->account_id]])->get()->pluck('full_address', 'id');
        $roles = Role::get()->pluck('name', 'id');
        $filters = Filters::all(Auth::User()->id, 'users');
        $records['filter_values'] = [
            'roles' => $roles,
            'locations' => $locations,
            'status' => config('constants.status'),
        ];
        $records['active_filters'] = $filters;

        return $records;
    }

    /**
     * Show the form for creating new User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        if (! Gate::allows('users_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $user = new \stdClass();
        $user->gender = null;
        $user->phone = null;
        $roles = Role::where('name', '!=', 'Super-Admin')->get();
        $roles_commissions = Role::where('name', '!=', 'Super-Admin')->get();
        $locations = LocationsWidget::generateDropDownArray(Auth::User()->account_id);

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'roles' => $roles,
            'roles_commissions' => $roles_commissions,
            'locations' => $locations,
            'user' => $user,
        ]);
    }

    /**
     * Store a newly created User in storage.
     */
    public function store(Request $request)
    {
        if (! Gate::allows('users_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $validator = $this->verifyCreateFields($request);
        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        $data = $request->all();
        $data['phone'] = GeneralFunctions::cleanNumber($request->phone);
        $data['account_id'] = Auth::User()->account_id;
        $data['main_account'] = '0';
        $data['user_type_id'] = Config::get('constants.application_user_id');
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
            // Check if locations exist and are set then assign centres to User
            if ($request->get('centers') && is_array($request->get('centers'))) {
                $centres = LocationsWidget::generatelocationArray($request->centers, Auth::User()->account_id, $user->id);
                $user_has_locations = [];
                foreach ($centres as $centre) {
                    $user_has_locations = [
                        'user_id' => $centre['user_id'],
                        'region_id' => $centre['region_id'],
                        'location_id' => $centre['location_id'],
                    ];
                    // Insert assigned centres to User
                    UserHasLocations::createRecord($user_has_locations, $user->id);
                }
            }
        }
        session()->flash('success', 'Record has been created successfully.');

        return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
    }

    /**
     * Validate create form fields.
     *
     * @return Validator $validator;
     */
    protected function verifyCreateFields(Request $request)
    {
        $rules = [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,NULL,id,deleted_at,NULL',
            'password' => 'required|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/',
            'roles' => 'required',
            'commission' => 'required',
        ];
        $messages = [
            'name.required' => 'Name field is required',
            'email.required' => 'Email field is required',
            'email.unique' => 'Email must be unique',
            'password.required' => 'Password field is required',
            'password.min' => 'password must be at least 8 characters',
            'password.regex' => 'Password must be a combination of numbers, upper, lower, and special characters',
            'roles.required' => 'Role must be unique',
            'commission.required' => 'Commission must be unique',
        ];

        return $validator = Validator::make($request->all(), $rules, $messages);
    }

    /**
     * Show the form for editing User.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function changePassword($id)
    {
        if (! Gate::allows('users_change_password')) {
            return abort(401);
        }
        $user = User::getData($id);
        if ($user == null) {
            return view('error');
        } else {
            return view('admin.users.change_password', compact('user'));
        }
    }

    /**
     * Update User Password in storage.
     *
     * @param  \App\Http\Requests\Admin\UpdateUsersRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function savePassword(Request $request)
    {
        if (! Gate::allows('users_change_password')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $data = [];
        $validator = $this->verifyPasswordFields($request);
        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->all(),
            ]);
        }
        try {
            $id = decrypt($request->get('id'));
        } catch (DecryptException $e) {

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again.', false);
        }
        $data['password'] = bcrypt($request->get('password'));
        $result = User::updateRecord($data, $id);
        if ($result) {

            return ApiHelper::apiResponse($this->success, 'Password has been changed successfully.');
        }

        return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again.', false);
    }

    /**
     * Validate create form fields.
     *
     * @return Validator $validator;
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
     * Show the form for editing User.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! Gate::allows('users_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $roles = Role::where('name', '!=', 'Super-Admin')->get()->pluck('name', 'id');
        $roles_commissions = Role::where('name', '!=', 'Super-Admin')->get();
        $user = User::getData($id);
        $user_has_locations = $user->user_has_locations->pluck('location_id');
        $user_has_locations = LocationsWidget::generatelocationArrayEdit($user_has_locations, Auth::User()->account_id, $user);
        if ($user_has_locations) {
            //$user_has_locations = $user_has_locations->toArray();
        } else {
            $user_has_locations = [];
        }
        $locations = LocationsWidget::generateDropDownArray(Auth::User()->account_id);
        $user_roles = $user->user_roles()->pluck('id');
        if ($user_roles) {
            $user_roles = $user_roles->toArray();
        } else {
            $user_roles = [];
        }

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'roles' => $roles,
            'user' => $user,
            'locations' => $locations,
            'roles_commissions' => $roles_commissions,
            'user_has_locations' => $user_has_locations,
            'user_roles' => $user_roles,
        ]);
    }

    /**
     * Update User in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! Gate::allows('users_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $validator = $this->verifyUpdateFields($request);
        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
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
                    if ($roleid) {
                        $role_has_users = [
                            'role_id' => $roleid->id ?? 0,
                            'user_id' => $user->id ?? 0,
                        ];
                        // Insert assigned centres to User
                        RoleHasUsers::updateRecord($role_has_users, $user);
                    }
                }
            }
            // Check if locations exist and are set then assign centres to User
            if ($request->get('centers') && is_array($request->get('centers'))) {
                // Destroy if user has locations
                $user->user_has_locations()->forceDelete();
                $centres = LocationsWidget::generatelocationArray($request->centers, Auth::User()->account_id, $user->id);
                $user_has_locations = [];
                foreach ($centres as $centre) {
                    $user_has_locations = [
                        'user_id' => $centre['user_id'],
                        'region_id' => $centre['region_id'],
                        'location_id' => $centre['location_id'],
                    ];
                    // Insert assigned centres to User
                    UserHasLocations::updateRecord($user_has_locations, $user);
                }
            }
        }
        session()->flash('success', 'Record has been updated successfully.');

        return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
    }

    /**
     * Validate create form fields.
     *
     * @return Validator $validator;
     */
    protected function verifyUpdateFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
            'roles' => 'required',
            'phone' => 'required',
            'gender' => 'required',
        ]);
    }

    /**
     * Remove User from storage.
     *
     * @param  int  $id
     */
    public function destroy($id)
    {
        if (! Gate::allows('users_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        User::deleteRecord($id);

        return ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.');
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function status(Request $request)
    {
        if (! Gate::allows('users_active')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $response = User::activeRecord($request->id, $request->status);
        if ($response) {
            return ApiHelper::apiResponse($this->success, 'Status has been changed successfully.');
        }

        return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
    }

    /*
     * Function get the variable to search in database to get the patient
     *
     * */
    public function getpatient(Request $request)
    {
        $patient = Patients::getPatientAjax($request->q, Auth::User()->account_id);

        return response()->json($patient);
    }

    /*
     * Function get the variable to search in database to get the patient
     *
     * */
    public function getpatientid(Request $request)
    {
        $patients = Patients::getPatientidAjax($request->search, Auth::User()->account_id);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'patients' => $patients,
        ]);
    }

    public function phoneSearch(Request $request)
    {
        $patients = Patients::getPatientPhoneAjax($request->search, Auth::User()->account_id);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'patients' => $patients,
        ]);
    }

    /*
    * Function get the variable to search in database to get the patient
    *
    * */
    public function getpatientnumber(Request $request)
    {
        $patient = Patients::find($request->patient_id);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'patient' => $patient,
        ]);
    }

    public function getUserCities()
    {
        $cities = ACL::getUserCities();
        if (count($cities) == 1) {
            return ApiHelper::apiResponse($this->success, 'City found', true, [
                'city' => $cities[0],
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'City not found', false);
    }

    public function getUserCenters()
    {
        $centers = ACL::getUserCentres();
        if (count($centers) == 1) {
            return ApiHelper::apiResponse($this->success, 'Center found', true, [
                'center' => $centers[0],
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'Center not found', false);
    }
}
