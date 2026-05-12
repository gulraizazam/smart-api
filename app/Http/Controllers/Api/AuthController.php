<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\LoginAuditLogger;
use App\Services\Auth\LoginCaptchaGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(
        private LoginAuditLogger $audit,
        private LoginCaptchaGate $captcha,
    ) {}

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

            $clientIp = (string) $request->ip();

            // Round 4 Auth-C1 — progressive CAPTCHA gate. After THRESHOLD
            // misses from this IP inside the rolling window, the SPA must
            // submit a valid Turnstile token alongside the credentials.
            // Checked BEFORE the per-account lockout so the captcha
            // challenge is honoured regardless of which account is being
            // targeted. When Turnstile keys aren't provisioned the gate
            // short-circuits to a no-op.
            if ($this->captcha->isRequired($clientIp)) {
                if (! $this->captcha->verify($request->input('cf-turnstile-response'), $clientIp)) {
                    return $this->errorResponse('Please complete the verification.', 401, [
                        'captcha_required' => true,
                        'site_key' => $this->captcha->siteKey(),
                    ]);
                }
            }

            /** @var User|null $user */
            $user = User::where('email', $request->input('email'))->first();

            // Round 4 Auth-E1 — enumeration-safe error messages. All
            // failure paths below return the same generic wording so an
            // attacker probing emails cannot distinguish a wrong password
            // from a deactivated / non-existent account. The lockout path
            // keeps the "Too many attempts" wording shared with 429 so
            // the message body alone does not confirm that the account
            // exists; the `Retry-After` header lets the SPA still render
            // a precise countdown.
            $genericFail = 'Sign-in failed. Please check your credentials and try again.';

            // Per-account lockout check before we touch the password —
            // mirrors the web LoginController flow so brute-forcing the
            // API endpoint is not a way around the DB lockout.
            //
            // Seconds (not just minutes) are surfaced via the standard
            // Retry-After header so the SPA can render an exact live
            // countdown — same mechanism the 429 throttle middleware uses,
            // which keeps the frontend handler a single code path.
            if ($user && $user->isLocked()) {
                $this->audit->record($request, 'api', LoginAuditLogger::OUTCOME_ACCOUNT_LOCKED, $request->input('email'), $user);
                $seconds = max(1, (int) ceil(now()->diffInSeconds($user->locked_until, false)));
                $minutes = max(1, (int) ceil($seconds / 60));
                return $this->errorResponse(
                    "Too many attempts. Try again in {$minutes} minute(s).",
                    423
                )->header('Retry-After', (string) $seconds);
            }

            // Validate credentials WITHOUT establishing a session — that
            // way a bad password is just a counter increment, not a
            // partial login that other code could pick up.
            if (! Auth::guard('web')->validate($request->only(['email', 'password']))) {
                $this->captcha->recordMiss($clientIp);
                if ($user) {
                    $user->recordFailedLogin();
                    $this->audit->record($request, 'api', LoginAuditLogger::OUTCOME_WRONG_PASSWORD, $request->input('email'), $user);
                } else {
                    $this->audit->record($request, 'api', LoginAuditLogger::OUTCOME_ACCOUNT_NOT_FOUND, $request->input('email'));
                }
                return $this->errorResponse($genericFail, 401);
            }

            // Re-fetch in case the validate call mutated state (it shouldn't,
            // but $user came from a separate query and we want fresh attrs).
            $user = User::where('email', $request->input('email'))->first();

            if (! $user || ! $user->active) {
                $this->captcha->recordMiss($clientIp);
                if ($user) {
                    $user->recordFailedLogin();
                    $this->audit->record($request, 'api', LoginAuditLogger::OUTCOME_ACCOUNT_DEACTIVATED, $request->input('email'), $user);
                } else {
                    $this->audit->record($request, 'api', LoginAuditLogger::OUTCOME_ACCOUNT_NOT_FOUND, $request->input('email'));
                }
                return $this->errorResponse($genericFail, 401);
            }

            // All gates cleared — establish the auth user, reset the
            // lockout counter, and issue the token. Auth::login is used
            // (rather than the original Auth::attempt) so we don't
            // re-validate the password and re-trigger the guard's
            // remember-me / event side-effects unnecessarily.
            Auth::guard('web')->login($user);
            $user->recordSuccessfulLogin();
            $this->captcha->clear($clientIp);
            $user->api_token = $user->createToken('login')->plainTextToken;

            $this->audit->record($request, 'api', LoginAuditLogger::OUTCOME_SUCCESS, $request->input('email'), $user);
            // toAuthPayload() bundles permissions + role alongside the
            // user attributes (and preserves the api_token we just set).
            // Without this the SPA caches the user with `permissions`
            // undefined and `usePermissions().has()` falls back to
            // allow-all, leaking gated menu entries (Scheduling Shifts,
            // Business Closures, Business Working Days) into roles
            // that shouldn't see them.
            return $this->successResponse('Success', $user->toAuthPayload());

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    /**
     * Logout for the cookie- and bearer-mode SPA. Passport-mode tokens are
     * revoked by /api/v2/auth/logout, so this endpoint is a no-op for them.
     *
     * The endpoint is intentionally unauthenticated and idempotent: an
     * expired-session caller still gets a clean 200 with the session and
     * CSRF token rotated, so the client can't be wedged in a "logged-out
     * locally, still alive on the server" state. The framework-emitted
     * Logout event is captured by AuthActivityListener::onLogout for audit.
     */
    public function logout(Request $request): \Illuminate\Http\JsonResponse
    {
        // Cookie mode: terminate the web session, rotate the session ID
        // and CSRF token. Safe to call when no session exists — the
        // guard's logout() short-circuits and session()->invalidate()
        // simply seeds a fresh empty session.
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Bearer mode: revoke ONLY the personal access token used for
        // this request — multi-device users keep their other sessions
        // alive. Cookie- and passport-mode requests don't authenticate
        // via Sanctum tokens, so this branch is a no-op for them.
        $tokenUser = Auth::guard('sanctum')->user();
        if ($tokenUser) {
            $token = $tokenUser->currentAccessToken();
            if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
                $token->delete();
            }
        }

        return $this->successResponse('Logged out.', null);
    }
}
