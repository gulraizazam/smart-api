<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\Widgets\LocationsWidget;
use App\Http\Controllers\Controller;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\RoleHasUsers;
use App\Models\UserHasLocations;
use App\User;
use Auth;
use Carbon\Carbon;
use Config;
use DB;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Session;
use Spatie\Permission\Models\Role;
use Validator;

class UsersController extends Controller
{
    /**
     * Display a listing of User.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Gate::allows('users_manage')) {
            //return abort(401);
        }
        $locations = Locations::where([['active', '=', '1'], ['account_id', '=', Auth::User()->account_id]])->get()->pluck('full_address', 'id');
        $locations->prepend('All', '');

        $roles = Role::get()->pluck('name', 'id');
        $roles->prepend('All', '');

        $filters = Filters::all(Auth::User()->id, 'users');

        return view('admin.users.index', compact('locations', 'roles', 'filters'));
    }

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     *
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request)
    {
        $filename = 'users';
        $apply_filter = false;
        if ($request->get('action')) {
            $action = $request->get('action');
            if (isset($action[0]) && $action[0] == 'filter_cancel') {
                Filters::flush(Auth::User()->id, $filename);
            } elseif ($action == 'filter') {
                $apply_filter = true;
            }
        }

        $records = [];
        $records['data'] = [];

        if ($request->get('customActionType') && $request->get('customActionType') == 'group_action') {
            $Users = User::whereIn('id', $request->get('id'));
            if ($Users) {
                $Users->delete();
            }
            $records['customActionStatus'] = 'OK'; // pass custom message(useful for getting status of group actions)
            $records['customActionMessage'] = 'Records has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
        }

        $where = [];

        $orderBy = 'users.created_at';
        $order = 'desc';

        if ($request->get('order')[0]['dir']) {
            $orderColumn = $request->get('order')[0]['column'];
            $orderBy = $request->get('columns')[$orderColumn]['data'];
            $order = $request->get('order')[0]['dir'];
        }

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

        if ($request->get('name') && $request->get('name') != '') {
            $where[] = [
                'users.name',
                'like',
                '%'.$request->get('name').'%',
            ];
            Filters::put(Auth::user()->id, $filename, 'name', $request->get('name'));
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
        if ($request->get('email') && $request->get('email')) {
            $where[] = [
                'users.email',
                'like',
                '%'.$request->get('email').'%',
            ];
            Filters::put(Auth::user()->id, $filename, 'email', $request->get('email'));
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

        if ($request->get('phone') && $request->get('phone') != '') {
            $where[] = [
                'users.phone',
                'like',
                '%'.GeneralFunctions::cleanNumber($request->get('phone')).'%',
            ];
            Filters::put(Auth::user()->id, $filename, 'phone', $request->get('phone'));
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
        if ($request->get('gender') && $request->get('gender')) {
            $where[] = [
                'users.gender',
                '=',
                $request->get('gender'),
            ];
            Filters::put(Auth::user()->id, $filename, 'gender', $request->get('gender'));
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

        if ($request->get('commission') && $request->get('commission') != '') {
            $where[] = [
                'commission',
                '=',
                $request->get('commission'),
            ];
            Filters::put(Auth::user()->id, $filename, 'commission', $request->get('commission'));
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
        if ($request->get('location_id') && $request->get('location_id')) {
            $where[] = [
                'user_has_locations.location_id',
                '=',
                $request->get('location_id'),
            ];
            Filters::put(Auth::user()->id, $filename, 'location_id', $request->get('location_id'));
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
        if ($request->get('role_id') && $request->get('role_id')) {
            $where[] = [
                'role_has_users.role_id',
                '=',
                $request->get('role_id'),
            ];
            Filters::put(Auth::user()->id, $filename, 'role_id', $request->get('role_id'));
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
        if ($request->get('created_from') && $request->get('created_from') != '') {
            $where[] = [
                'users.created_at',
                '>=',
                $request->get('created_from').' 00:00:00',
            ];
            Filters::put(Auth::user()->id, $filename, 'created_from', $request->get('created_from').' 00:00:00');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'created_from');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'created_from')) {
                    $where[] = [
                        'users.created_at',
                        '>=',
                        Filters::get(Auth::user()->id, $filename, 'created_from'),
                    ];
                }
            }
        }

        if ($request->get('created_to') && $request->get('created_to') != '') {
            $where[] = [
                'users.created_at',
                '<=',
                $request->get('created_to').' 23:59:59',
            ];
            Filters::put(Auth::user()->id, $filename, 'created_to', $request->get('created_to').' 23:59:59');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, $filename, 'created_to');
            } else {
                if (Filters::get(Auth::user()->id, $filename, 'created_to')) {
                    $where[] = [
                        'users.created_at',
                        '<=',
                        Filters::get(Auth::user()->id, $filename, 'created_to'),
                    ];
                }
            }
        }

        if ($request->get('status') && $request->get('status') != null || $request->get('status') == 0 && $request->get('status') != null) {
            $where[] = [
                'users.active',
                '=',
                $request->get('status'),
            ];
            Filters::put(Auth::user()->id, $filename, 'status', $request->get('status'));
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
            $iTotalRecords = count(DB::table('users')->leftJoin('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                ->leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                ->groupBy('user_has_locations.user_id', 'role_has_users.user_id')
                ->select('users.id')
                ->whereNotIn('users.user_type_id', [Config::get('constants.practitioner_id'), Config::get('constants.patient_id')])
                ->where([
                    [$where],
                    ['account_id', '=', Auth::User()->account_id],
                ])->get());
        } else {
            $iTotalRecords = count(DB::table('users')->leftJoin('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                ->leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                ->groupBy('user_has_locations.user_id', 'role_has_users.user_id')
                ->select('users.id')
                ->whereNotIn('users.user_type_id', [Config::get('constants.practitioner_id'), Config::get('constants.patient_id')])
                ->where([
                    ['account_id', '=', Auth::User()->account_id],
                ])->get());
        }

        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));
        $sEcho = intval($request->get('draw'));

        $end = $iDisplayStart + $iDisplayLength;
        $end = $end > $iTotalRecords ? $iTotalRecords : $end;

        if (count($where)) {
            $Users = User::leftJoin('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                ->leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                ->groupBy('user_has_locations.user_id', 'role_has_users.user_id')
                ->whereNotIn('users.user_type_id', [Config::get('constants.practitioner_id'), Config::get('constants.patient_id')])
                ->where([
                    [$where],
                    ['account_id', '=', Auth::User()->account_id],
                ])->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
        } else {
            $Users = User::leftJoin('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                ->leftjoin('role_has_users', 'users.id', '=', 'role_has_users.user_id')
                ->groupBy('user_has_locations.user_id', 'role_has_users.user_id')
                ->whereNotIn('users.user_type_id', [Config::get('constants.practitioner_id'), Config::get('constants.patient_id')])
                ->where([
                    ['account_id', '=', Auth::User()->account_id],
                ])->limit($iDisplayLength)->offset($iDisplayStart)->orderBy($orderBy, $order)->get();
        }
        if ($Users) {
            $index = 0;
            $loc = Locations::select('*')->get()->getDictionary();
            foreach ($Users as $user) {
                $locaiton = '';
                $userhaslocation = $user->user_has_locations->pluck('location_id');
                $user_has_locations = LocationsWidget::generatelocationArrayEdit($userhaslocation, Auth::User()->account_id, $user);
                if ($user_has_locations) {
                    foreach ($user_has_locations as $location) {
                        $locationchecked = Locations::find($location);
                        if ($locationchecked->slug == 'custom') {
                            $locaiton .= '<span class="label label-sm label-info">'.$loc[$location]->city->name.'-'.$loc[$location]->name.'</span>&nbsp;';
                        } else {
                            $locaiton .= '<span class="label label-sm label-info">'.$loc[$location]->name.'</span>&nbsp;';
                        }
                    }
                }
                $roles = '';
                foreach ($user->roles()->pluck('name') as $role) {
                    $roles .= '<span class="label label-sm label-info">'.$role.'</span>&nbsp;';
                }
                $records['data'][$index] = [
                    'id' => '<label class="mt-checkbox mt-checkbox-single mt-checkbox-outline"><input name="id[]" type="checkbox" class="checkboxes" value="'.$user->id.'"/><span></span></label>',
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => GeneralFunctions::contactStatus($user->phone),
                    'commission' => $user->commission.'%',
                    'gender' => view('admin.users.genderselection', compact('user'))->render(),
                    'location' => $locaiton,
                    'roles' => $roles,
                    'created_at' => Carbon::parse($user->created_at)->format('F j,Y h:i A'),
                    'status' => view('admin.users.status', compact('user'))->render(),
                    'actions' => view('admin.users.actions', compact('user'))->render(),
                ];
                ++$index;
            }
        }

        $records['draw'] = $sEcho;
        $records['recordsTotal'] = $iTotalRecords;
        $records['recordsFiltered'] = $iTotalRecords;

        return response()->json($records);
    }

    /**
     * Show the form for creating new User.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (!Gate::allows('users_create')) {
            return abort(401);
        }
        $user = new \stdClass();

        $user->gender = null;
        $user->phone = null;

        $roles = Role::get();
        $roles_commissions = Role::all();

        $locations = LocationsWidget::generateDropDownArray(Auth::User()->account_id);

        return view('admin.users.create', compact('roles', 'roles_commissions', 'locations', 'user'));
    }

    /**
     * Store a newly created User in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!Gate::allows('users_create')) {
            return abort(401);
        }

        $validator = $this->verifyCreateFields($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->all(),
            ]);
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
        flash('Record has been created successfully.')->success()->important();

        return response()->json([
            'status' => 1,
            'message' => 'Record has been created successfully.',
        ]);
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
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function changePassword($id)
    {
        if (!Gate::allows('users_change_password')) {
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
     * @param \App\Http\Requests\Admin\UpdateUsersRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function savePassword(Request $request)
    {
        if (!Gate::allows('users_change_password')) {
            return abort(401);
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
            flash('Are you mad? what were you trying to do? :@.')->error()->important();

            return redirect()->back();
        }

        $data['password'] = bcrypt($request->get('password'));

        $result = User::updateRecord($data, $id);

        if ($result) {
            flash('Password has been changed successfully.')->success()->important();

            return response()->json([
                'status' => 1,
                'message' => 'Password has been changed successfully.',
            ]);
        }

        flash('Are you mad? what were you trying to do? :@.')->error()->important();

        return redirect()->back();
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
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!Gate::allows('users_edit')) {
            return abort(401);
        }
        $roles = Role::get()->pluck('name', 'id');
        $roles_commissions = Role::all();

        $user = User::getData($id);

        $user_has_locations = $user->user_has_locations->pluck('location_id');

        $user_has_locations = LocationsWidget::generatelocationArrayEdit($user_has_locations, Auth::User()->account_id, $user);
        if ($user_has_locations) {
            //$user_has_locations = $user_has_locations->toArray();
        } else {
            $user_has_locations = [];
        }

        $locations = LocationsWidget::generateDropDownArray(Auth::User()->account_id);

        return view('admin.users.edit', compact('user', 'roles', 'roles_commissions', 'user_has_locations', 'locations'));
    }

    /**
     * Update User in storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (!Gate::allows('users_edit')) {
            return abort(401);
        }

        $validator = $this->verifyUpdateFields($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->all(),
            ]);
        }
        if($request->input('phone') == '***********'){
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
        flash('Record has been updated successfully.')->success()->important();

        return response()->json([
            'status' => 1,
            'message' => 'Record has been updated successfully.',
        ]);
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
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (!Gate::allows('users_destroy')) {
            return abort(401);
        }

        User::deleteRecord($id);

        return redirect()->route('admin.users.index');
    }

    /**
     * Inactive Record from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function inactive($id)
    {
        if (!Gate::allows('users_inactive')) {
            return abort(401);
        }

        User::InactiveRecord($id);

        return redirect()->route('admin.users.index');
    }

    /**
     * Inactive Record from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function active($id)
    {
        if (!Gate::allows('users_active')) {
            return abort(401);
        }
        User::activeRecord($id);

        return redirect()->route('admin.users.index');
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
        $patient = Patients::getPatientidAjax($request->q, Auth::User()->account_id);

        return response()->json($patient);
    }

    /*
    * Function get the variable to search in database to get the patient
    *
    * */
    public function getpatientnumber(Request $request)
    {
        $patient = Patients::find($request->patient_id);

        return response()->json($patient);
    }
}
