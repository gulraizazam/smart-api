<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Login for Apis.
     *
     * Round 4 Auth-M3 — the original implementation only checked password
     * and issued a Sanctum token. That skipped the per-account DB lockout
     * (User::isLocked / recordFailedLogin / recordSuccessfulLogin).
     *
     * Hardened flow:
     *  1. Look up the user; if locked, refuse before touching the password.
     *  2. Validate credentials WITHOUT logging in via the guard.
     *  3. On a bad password, count it against the lockout policy and bail.
     *  4. Refuse inactive accounts (also counts toward the policy).
     *  5. Only after every gate clears do we issue a Sanctum token and
     *     reset the failed-login counter.
     */
    public function login(Request $request): \Illuminate\Http\JsonResponse
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

            /** @var User|null $user */
            $user = User::where('email', $request->input('email'))->first();

            // Per-account lockout check before we touch the password —
            // mirrors the web LoginController flow so brute-forcing the
            // API endpoint is not a way around the DB lockout.
            if ($user && $user->isLocked()) {
                $minutes = max(1, (int) ceil(now()->diffInMinutes($user->locked_until, false)));
                return $this->errorResponse(
                    "Account is temporarily locked. Try again in {$minutes} minute(s).",
                    423
                );
            }

            // Validate credentials WITHOUT establishing a session — that
            // way a bad password is just a counter increment, not a
            // partial login that other code could pick up.
            if (! Auth::guard('web')->validate($request->only(['email', 'password']))) {
                if ($user) {
                    $user->recordFailedLogin();
                }
                return $this->errorResponse(__('auth.failed'), 401);
            }

            // Re-fetch in case the validate call mutated state (it shouldn't,
            // but $user came from a separate query and we want fresh attrs).
            $user = User::where('email', $request->input('email'))->first();

            if (! $user || ! $user->active) {
                if ($user) {
                    $user->recordFailedLogin();
                }
                return $this->errorResponse('Your account has been deactivated, please contact administrator.', 403);
            }

            // All gates cleared — establish the auth user, reset the
            // lockout counter, and issue the token. Auth::login is used
            // (rather than the original Auth::attempt) so we don't
            // re-validate the password and re-trigger the guard's
            // remember-me / event side-effects unnecessarily.
            Auth::guard('web')->login($user);
            $user->recordSuccessfulLogin();
            $user->api_token = $user->createToken('login')->plainTextToken;

            return $this->successResponse('Success', $user);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }
}
