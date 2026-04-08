<?php

declare(strict_types=1);
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ChangePasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected $redirectTo = '/change_password';

    /**
     * Change password form
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function showChangePasswordForm(): \Illuminate\View\View
    {
        $user = Auth::user();

        return view('admin.profile.change-password', compact('user'));
    }

    /**
     * Change password.
     */
    public function changePassword(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'message' => $validator->messages()->all(),
            ]);
        }

        if (Hash::check($request->get('current_password'), $user->password)) {
            $user->password = bcrypt($request->get('new_password'));
            $user->save();

            return response()->json([
                'status' => true,
                'message' => 'Password has been changed successfully.',
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Current password is incorrect.',
            ]);
        }
    }

    /**
     * Get a validator for an incoming change password request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(Request $request): \Illuminate\Contracts\Validation\Validator
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

        return Validator::make($request->all(), $rules, $messages);
    }
}
