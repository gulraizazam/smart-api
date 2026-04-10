<?php

declare(strict_types=1);
namespace App\Http\Controllers\Auth;

use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\AppServiceProvider;
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

    public function __construct()
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
    protected function authenticated(): void
    {
        $authedUser = Auth::user();
        $account_id = $authedUser->account_id;
        session(['account_id' => $account_id]);
        $account = DB::table('accounts')->find($account_id);
        session(['account' => $account]);

        // H2 — clear any failed-login counter / active lockout once a
        // user has fully authenticated.
        if ($authedUser instanceof User) {
            $authedUser->recordSuccessfulLogin();
        }
    }

    public function login(Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->validateLogin($request);

        // ThrottlesLogins (cache-keyed, per IP+username). Existing behavior.
        if ($this->hasTooManyLoginAttempts($request)) {
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
            $minutes = max(1, (int) ceil(now()->diffInMinutes($candidate->locked_until, false)));
            return redirect()
                ->back()
                ->withInput($request->only($this->username(), 'remember'))
                ->with(['error' => "Account is temporarily locked due to repeated failed login attempts. Try again in {$minutes} minute(s)."]);
        }

        if ($this->guard()->validate($this->credentials($request))) {
            /** @var User $user */
            $user = $this->guard()->getLastAttempted();

            // Inactive account: count this as a failed attempt for both
            // the IP throttle and the per-account lockout, then bail.
            if (! $user->active) {
                $this->incrementLoginAttempts($request);
                $user->recordFailedLogin();

                return redirect()
                    ->back()
                    ->withInput($request->only($this->username(), 'remember'))
                    ->with(['error' => 'Your account has been deactivated, please contact administrator.']);
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
        }

        return redirect()
            ->back()
            ->withInput()
            ->with(['error' => 'Your credentials did not match with our record.']);
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
