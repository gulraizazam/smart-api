<?php

namespace App\Http\Controllers\Api;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public $success;

    public $error;

    public function __construct()
    {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
    }

    /**
     * Login for Apis
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            $rules = [
                'email' => 'required |email',
                'password' => 'required',
            ];
            $message = [
                'required' => 'Please enter :attribute',
                'email' => 'Please enter valid email',
            ];
            $validate = Validator::make($request->all(), $rules, $message);
            if ($validate->fails()) {
                return ApiHelper::apiResponse($this->error, $validate->errors()->first(), $validate->errors());
            }

            if (Auth::attempt($request->only(['email', 'password']))) {

                $user = auth()->user();
                $user->api_token = auth()->user()->createToken('login')->plainTextToken;

                return ApiHelper::apiResponse($this->success, 'Success', $user);
            }

            return ApiHelper::apiResponse($this->error, __('auth.failed'));

        } catch (\Exception $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage());
        }
    }
}
