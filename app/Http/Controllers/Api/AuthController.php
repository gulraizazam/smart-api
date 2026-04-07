<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public $success;

    public $error;

    

    /**
     * Login for Apis
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request): mixed
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
                return $this->errorResponse($validate->errors()->first(), 422, $validate->errors()->all());
            }

            if (Auth::attempt($request->only(['email', 'password']))) {

                $user = auth()->user();
                $user->api_token = auth()->user()->createToken('login')->plainTextToken;

                return $this->successResponse('Success', $user);
            }

            return $this->errorResponse(__('auth.failed'), 401);

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
