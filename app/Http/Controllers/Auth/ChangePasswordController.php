<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Hash;
use Validator;

class ChangePasswordController extends Controller
{

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Where to redirect users after password is changed.
     *
     * @var string $redirectTo
     */
    protected $redirectTo = '/change_password';

    /**
     * Change password form
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showChangePasswordForm()
    {
        $user = Auth::getUser();

        return view('admin.profile.change-password', compact('user'));
    }

    /**
     * Change password.
     *
     * @param Request $request
     */
    public function changePassword(Request $request)
    {
        $user = Auth::getUser();

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
            ));
        }

        if (Hash::check($request->get('current_password'), $user->password)) {
            $user->password = bcrypt($request->get('new_password'));
            $user->save();

            return response()->json(array(
                'status' => true,
                'message' => 'Password has been changed successfully.',
            ));
        } else {
            return response()->json(array(
                'status' => false,
                'message' => 'Current password is incorrect.',
            ));
        }
    }

    /**
     * Get a validator for an incoming change password request.
     *
     * @param array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(Request $request)
    {
        $rules = [
            'current_password' => 'required',
            'new_password' => 'required|confirmed|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/',
        ];

        $messages = [
            'current_password.required' => 'Current Password field is required',
            'new_password.required' => 'New Password field is required',
            'new_password.min' => 'New password must be at least 8 characters',
            'new_password.regex' => 'New Password must be a combination of numbers, upper, lower, and special characters',
        ];
        return $validator = Validator::make($request->all(), $rules, $messages);
    }
}
