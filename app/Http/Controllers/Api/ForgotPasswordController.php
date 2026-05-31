<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * SPA-facing forgot-password / reset-password endpoints.
 *
 * The legacy Blade pair (Auth\ForgotPasswordController + ResetPasswordController
 * in routes/web.php) stays alive until the cutover for the legacy admin's
 * own login page. After cutover, those routes go and only this pair remains.
 *
 * Two contracts that differ from the legacy Blade flow on purpose:
 *
 *   1. **Always 200 on send**, regardless of whether the email matches a
 *      known user. The Blade flow leaks "didn't find any record" which lets
 *      an attacker enumerate active accounts. The SPA endpoint never confirms
 *      account existence — the user only learns whether the email landed.
 *
 *   2. **Reset URL targets the SPA**, not `route('password.reset')`. The
 *      override is wired in AppServiceProvider::boot via
 *      ResetPassword::createUrlUsing — so the email links to the SPA's
 *      /reset-password/{token} screen built here, which then POSTs to
 *      /api/password/reset.
 */
class ForgotPasswordController extends Controller
{
    /**
     * Send a reset link to the user. Always returns 200 with a generic
     * "if an account exists, an email was sent" message — never confirms
     * or denies the email's existence (enumeration defense).
     *
     * Throttling is enforced at the route level (5/60min) AND by the
     * password broker (config/auth.php passwords.users.throttle = 60s
     * between repeat sends per email).
     */
    public function send(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                $validator->errors()->first(),
                422,
                $validator->errors()->all(),
            );
        }

        // Fire-and-forget: we deliberately ignore the broker's response
        // status. RESET_LINK_SENT, INVALID_USER, and RESET_THROTTLED all
        // surface to the caller as the same generic 200 — distinguishing
        // them is exactly the enumeration vector we're closing.
        Password::sendResetLink($request->only('email'));

        return $this->successResponse(
            'If an account exists for that email, a reset link has been sent.',
            null,
        );
    }

    /**
     * Apply a new password using the token from the SPA reset screen.
     *
     * The broker validates `(email, token)` against the hashed token in
     * `password_resets` (see config/auth.php); on success it invokes the closure to do the
     * actual update. The token is consumed (single-use) by the broker.
     */
    public function reset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                PasswordRule::min(8)->mixedCase()->numbers()->symbols(),
                'confirmed',
            ],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(
                $validator->errors()->first(),
                422,
                $validator->errors()->all(),
            );
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    // Rotate remember_token so any "remember me" cookies
                    // outstanding on the old password lose their grip.
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->successResponse('Password has been reset successfully.', null);
        }

        // INVALID_TOKEN, INVALID_USER, etc. all surface as a single
        // 422 with the broker's translated message ('passwords.token',
        // 'passwords.user', etc. — see resources/lang/*/passwords.php).
        return $this->errorResponse(__($status), 422);
    }
}
