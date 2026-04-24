<?php

declare(strict_types=1);
namespace App\Http\Controllers\Auth;

use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\Auth\LoginAuditLogger;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = AppServiceProvider::HOME;

    public function __construct(private LoginAuditLogger $audit)
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * The user has been authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $authedUser): void
    {
        $account_id = $authedUser->account_id;
        session(['account_id' => $account_id]);
        $account = DB::table('accounts')->find($account_id);
        session(['account' => $account]);

        // H2 — clear any failed-login counter / active lockout once a
        // user has fully authenticated.
        if ($authedUser instanceof User) {
            $authedUser->recordSuccessfulLogin();
            $this->audit->record($request, 'web', LoginAuditLogger::OUTCOME_SUCCESS, $authedUser->{$this->username()}, $authedUser);
        }
    }

    public function login(Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->validateLogin($request);

        // Round 4 Auth-E1 — enumeration-safe error messaging. Every
        // non-success path below returns the same generic "sign-in failed"
        // wording so an attacker probing emails cannot tell apart:
        //   • wrong password on a valid account
        //   • valid credentials on a deactivated account
        //   • account that does not exist
        // The lockout path is kept visually distinct in the SPA via the
        // `Retry-After` header (UX), but the message body itself uses the
        // same "Too many attempts" wording as the IP throttle so attackers
        // can't confirm whether a target account actually exists from the
        // message content alone.
        $genericFail = 'Sign-in failed. Please check your credentials and try again.';

        // ThrottlesLogins (cache-keyed, per IP+username). Existing behavior.
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->audit->record($request, 'web', LoginAuditLogger::OUTCOME_RATE_LIMITED, $request->input($this->username()));
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        // H2 — DB-backed account lockout. Survives cache flushes and is keyed
        // to the user account, not the source IP. Looked up by the configured
        // username column (typically 'email').
        $username = $request->input($this->username());
        $candidate = $username
            ? User::where($this->username(), $username)->first()
            : null;

        if ($candidate && $candidate->isLocked()) {
            $this->audit->record($request, 'web', LoginAuditLogger::OUTCOME_ACCOUNT_LOCKED, $username, $candidate);
            $minutes = max(1, (int) ceil(now()->diffInMinutes($candidate->locked_until, false)));
            return redirect()
                ->back()
                ->withInput($request->only($this->username(), 'remember'))
                ->with(['error' => "Too many attempts. Try again in {$minutes} minute(s)."]);
        }

        if ($this->guard()->validate($this->credentials($request))) {
            /** @var User $user */
            $user = $this->guard()->getLastAttempted();

            // Inactive account: count as a failed attempt but return the
            // SAME generic message as wrong-creds — do not confirm that
            // the address corresponds to a real (if deactivated) account.
            if (! $user->active) {
                $this->incrementLoginAttempts($request);
                $user->recordFailedLogin();
                $this->audit->record($request, 'web', LoginAuditLogger::OUTCOME_ACCOUNT_DEACTIVATED, $username, $user);

                return redirect()
                    ->back()
                    ->withInput($request->only($this->username(), 'remember'))
                    ->with(['error' => $genericFail]);
            }

            if ($this->attemptLogin($request)) {
                return $this->sendLoginResponse($request);
            }
        }

        // Failed credentials. Increment both the IP throttle and the per-
        // account lockout (if we know which account was targeted).
        $this->incrementLoginAttempts($request);
        if ($candidate) {
            $candidate->recordFailedLogin();
            $this->audit->record($request, 'web', LoginAuditLogger::OUTCOME_WRONG_PASSWORD, $username, $candidate);
        } else {
            $this->audit->record($request, 'web', LoginAuditLogger::OUTCOME_ACCOUNT_NOT_FOUND, $username);
        }

        return redirect()
            ->back()
            ->withInput()
            ->with(['error' => $genericFail]);
    }

    /**
     * Check user session.
     *
     * @return Response
     */
    public function checkSession(): \Illuminate\Http\JsonResponse
    {
        return response()->json(['guest' => Auth::guest()]);
    }

    /**
     * Log the user out of the application.
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function logout(Request $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        Filters::remove_filters();

        $this->guard()->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($response = $this->loggedOut($request)) {
            return $response;
        }

        return $request->wantsJson()
            ? new JsonResponse([], 204)
            : redirect('/');
    }
}
