<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V2\LoginRequest;
use App\Http\Requests\Api\V2\RefreshRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Passport-based auth for the mobile SPA.
 *
 * Login: runs the same lockout / active-account / failed-login gates as
 * the legacy Sanctum AuthController, then delegates token issuance to
 * Passport's /oauth/token via the password grant. Response is a typed
 * envelope carrying { access_token, refresh_token, expires_in, user }.
 *
 * Refresh: rotates the access+refresh pair. Logout: revokes the access
 * token and its refresh chain.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $email = (string) $request->input('email');
        $password = (string) $request->input('password');

        /** @var User|null $user */
        $user = User::where('email', $email)->first();

        // Enumeration-safe failures: wrong-password, unknown-account and
        // deactivated all return the SAME generic 401, and the lockout uses the
        // shared "Too many attempts" wording — so the response never confirms
        // whether an email exists. Mirrors the Sanctum AuthController
        // (Round 4 Auth-E1). Security audit 2026-06.
        $genericFail = 'Sign-in failed. Please check your credentials and try again.';

        if ($user && $user->isLocked()) {
            $seconds = max(1, (int) ceil(now()->diffInSeconds($user->locked_until, false)));
            $minutes = max(1, (int) ceil($seconds / 60));

            return $this->errorResponse(
                "Too many attempts. Try again in {$minutes} minute(s).",
                423
            )->header('Retry-After', (string) $seconds);
        }

        if (! Auth::guard('web')->validate(['email' => $email, 'password' => $password])) {
            $user?->recordFailedLogin();

            return $this->errorResponse($genericFail, 401);
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! $user->active) {
            $user?->recordFailedLogin();

            return $this->errorResponse($genericFail, 401);
        }

        $tokens = $this->issuePasswordGrantTokens($email, $password);

        if ($tokens === null) {
            return $this->errorResponse('Could not issue access token.', 500);
        }

        Auth::guard('web')->login($user);
        $user->recordSuccessfulLogin();

        return $this->successResponse('Success', [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['expires_in'],
            'token_type' => $tokens['token_type'],
            // Match the sanctum login payload — see User::toAuthPayload()
            // for the rationale. Without permissions+role here the SPA's
            // passport-mode boot leaves the user cache with no perms
            // (boot doesn't refetch /api/user in passport mode) and
            // gated menu entries leak through.
            'user' => $user->toAuthPayload(),
        ]);
    }

    public function refresh(RefreshRequest $request): JsonResponse
    {
        $clientId = (string) config('services.passport.password_client_id');
        $clientSecret = (string) config('services.passport.password_client_secret');

        if ($clientId === '' || $clientSecret === '') {
            return $this->errorResponse('Passport password client is not configured.', 500);
        }

        $response = Http::asForm()->post(url('/oauth/token'), [
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $request->input('refresh_token'),
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => '',
        ]);

        if (! $response->successful()) {
            return $this->errorResponse('Invalid refresh token.', 401);
        }

        $body = (array) $response->json();

        return $this->successResponse('Success', [
            'access_token' => $body['access_token'] ?? null,
            'refresh_token' => $body['refresh_token'] ?? null,
            'expires_in' => $body['expires_in'] ?? null,
            'token_type' => $body['token_type'] ?? 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->errorResponse('Not authenticated.', 401);
        }

        $token = $user->token();

        if ($token === null) {
            return $this->errorResponse('No active token on the current session.', 400);
        }

        DB::transaction(function () use ($token): void {
            $token->revoke();
            DB::table('oauth_refresh_tokens')
                ->where('access_token_id', $token->getKey())
                ->update(['revoked' => true]);
        });

        return $this->successResponse('Logged out.');
    }

    /**
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string}|null
     */
    private function issuePasswordGrantTokens(string $email, string $password): ?array
    {
        $clientId = (string) config('services.passport.password_client_id');
        $clientSecret = (string) config('services.passport.password_client_secret');

        if ($clientId === '' || $clientSecret === '') {
            Log::error('Passport password client is not configured; cannot issue tokens.');

            return null;
        }

        $response = Http::asForm()->post(url('/oauth/token'), [
            'grant_type' => 'password',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'username' => $email,
            'password' => $password,
            'scope' => '',
        ]);

        if (! $response->successful()) {
            Log::error('Passport password grant failed.', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        }

        $body = (array) $response->json();

        return [
            'access_token' => (string) ($body['access_token'] ?? ''),
            'refresh_token' => (string) ($body['refresh_token'] ?? ''),
            'expires_in' => (int) ($body['expires_in'] ?? 0),
            'token_type' => (string) ($body['token_type'] ?? 'Bearer'),
        ];
    }
}
