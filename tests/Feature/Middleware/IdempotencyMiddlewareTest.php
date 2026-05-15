<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureIdempotency;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Contract tests for the Idempotency-Key middleware. These exercise
 * the middleware directly via a one-off test route so the contract is
 * pinned independently of any business endpoint:
 *
 *   1. No header → pass-through (handler runs as if middleware absent)
 *   2. First call WITH header → handler runs, response cached
 *   3. Second call same key + same body → cached response returned,
 *      `Idempotency-Key-Replay: true` header set
 *   4. Second call same key + DIFFERENT body → 409 (key reused with
 *      mutated payload — refuse the replay)
 *   5. In-flight reservation: simulated by pre-seeding the reservation
 *      cache key → second concurrent caller gets 409
 *   6. 5xx response not cached → next call re-runs the handler
 *   7. Bad key format (too short / illegal chars) → 400
 *   8. Per-user scoping — user A's key doesn't replay for user B
 *   9. Per-route scoping — same key on different routes works
 *      independently
 *
 * If any of these turn red, replay protection has regressed — payment
 * endpoints become vulnerable to double-submits and replay attacks.
 */
class IdempotencyMiddlewareTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    /**
     * Counter increments every time the test handler runs. Lets us
     * assert "handler ran N times" vs "served from cache."
     */
    private static int $handlerCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        Cache::flush();
        self::$handlerCalls = 0;

        // Register two ephemeral routes — one for the per-route scope
        // test — both gated by auth + the idempotent middleware.
        Route::middleware(['auth.api.dual', 'idempotent'])->post(
            '__idempotency_test/echo',
            function (\Illuminate\Http\Request $request) {
                self::$handlerCalls++;
                return response()->json([
                    'success' => true,
                    'handler_calls' => self::$handlerCalls,
                    'echoed' => $request->all(),
                ]);
            },
        );
        Route::middleware(['auth.api.dual', 'idempotent'])->post(
            '__idempotency_test/other',
            fn () => response()->json(['ok' => true]),
        );
        Route::middleware(['auth.api.dual', 'idempotent'])->post(
            '__idempotency_test/failing',
            fn () => response()->json(['error' => 'fail'], 500),
        );
    }

    /* -------- 1. No header → pass-through -------- */

    public function test_request_without_header_passes_through(): void
    {
        $this->actingAsAdmin();

        $a = $this->postJson('/__idempotency_test/echo', ['v' => 1]);
        $b = $this->postJson('/__idempotency_test/echo', ['v' => 1]);

        $a->assertStatus(200);
        $b->assertStatus(200);
        $this->assertSame(2, self::$handlerCalls,
            'Without the header, the middleware must be a no-op and the handler must run on every request.');
    }

    /* -------- 2 & 3. Replay returns cached response -------- */

    public function test_replay_returns_cached_response_with_replay_header(): void
    {
        $this->actingAsAdmin();

        $headers = ['Idempotency-Key' => 'test-key-12345'];

        $first = $this->postJson('/__idempotency_test/echo', ['v' => 7], $headers);
        $second = $this->postJson('/__idempotency_test/echo', ['v' => 7], $headers);

        $first->assertStatus(200);
        $second->assertStatus(200);

        $this->assertSame(1, self::$handlerCalls,
            'The second call must NOT re-run the handler — it must be served from idempotency cache.');
        $this->assertSame($first->json(), $second->json(),
            'Replay response must match the original byte-for-byte.');
        $second->assertHeader('Idempotency-Key-Replay', 'true');
    }

    /* -------- 4. Same key + different body → 409 -------- */

    public function test_same_key_with_different_body_returns_409(): void
    {
        $this->actingAsAdmin();

        $headers = ['Idempotency-Key' => 'test-key-12345'];

        $this->postJson('/__idempotency_test/echo', ['v' => 1], $headers)->assertStatus(200);
        $reused = $this->postJson('/__idempotency_test/echo', ['v' => 2], $headers);

        $reused->assertStatus(409);
        $this->assertStringContainsString(
            'different request payload',
            strtolower((string) $reused->json('message')),
        );
    }

    /* -------- 5. In-flight reservation returns 409 -------- */

    public function test_in_flight_reservation_returns_409(): void
    {
        $admin = $this->actingAsAdmin();
        $headers = ['Idempotency-Key' => 'test-inflight-key'];

        // Seed the in-flight reservation manually to simulate "another
        // concurrent caller already in the handler."
        $routeFingerprint = sha1('POST|__idempotency_test/echo');
        $cacheKey = sprintf(
            'idempotency:%d:%s:%s',
            $admin->id,
            $routeFingerprint,
            'test-inflight-key',
        );
        Cache::put($cacheKey.':in-flight', $admin->id, 60);

        $response = $this->postJson('/__idempotency_test/echo', ['v' => 1], $headers);

        $response->assertStatus(409);
        $this->assertStringContainsString(
            'still being processed',
            strtolower((string) $response->json('message')),
        );
        $this->assertSame(0, self::$handlerCalls,
            'Handler must NOT run while another request with the same key is in flight.');
    }

    /* -------- 6. 5xx response is NOT cached -------- */

    public function test_error_response_is_not_cached(): void
    {
        $this->actingAsAdmin();
        $headers = ['Idempotency-Key' => 'test-key-error'];

        $first = $this->postJson('/__idempotency_test/failing', ['v' => 1], $headers);
        $second = $this->postJson('/__idempotency_test/failing', ['v' => 1], $headers);

        $first->assertStatus(500);
        $second->assertStatus(500);
        // Both should have hit the handler — error responses are not
        // cached, so the second request runs fresh.
        $second->assertHeaderMissing('Idempotency-Key-Replay');
    }

    /* -------- 7. Bad key format → 400 -------- */

    public function test_malformed_key_is_rejected_with_400(): void
    {
        $this->actingAsAdmin();

        // Too short.
        $this->postJson('/__idempotency_test/echo', ['v' => 1], ['Idempotency-Key' => 'abc'])
            ->assertStatus(400);

        // Illegal chars.
        $this->postJson('/__idempotency_test/echo', ['v' => 1], ['Idempotency-Key' => 'bad key with spaces'])
            ->assertStatus(400);

        // Too long (>128).
        $this->postJson('/__idempotency_test/echo', ['v' => 1], ['Idempotency-Key' => str_repeat('a', 130)])
            ->assertStatus(400);
    }

    /* -------- 8. Per-user scoping -------- */

    public function test_per_user_scope_prevents_cross_user_replay(): void
    {
        $headers = ['Idempotency-Key' => 'shared-key-across-users'];

        $userA = $this->actingAsAdmin();
        $first = $this->postJson('/__idempotency_test/echo', ['v' => 1], $headers);
        $first->assertStatus(200);
        $callsAfterA = self::$handlerCalls;

        $userB = $this->actingAsAdmin();
        $this->assertNotSame($userA->id, $userB->id, 'Sanity: distinct users.');

        $second = $this->postJson('/__idempotency_test/echo', ['v' => 1], $headers);
        $second->assertStatus(200);

        $this->assertGreaterThan($callsAfterA, self::$handlerCalls,
            'A key issued by user A must NOT replay for user B — the handler should have run for user B.');
        $second->assertHeaderMissing('Idempotency-Key-Replay');
    }

    /* -------- 9. Per-route scoping -------- */

    public function test_per_route_scope_allows_same_key_on_different_routes(): void
    {
        $this->actingAsAdmin();
        $headers = ['Idempotency-Key' => 'cross-route-key'];

        $echo = $this->postJson('/__idempotency_test/echo', ['v' => 1], $headers);
        $other = $this->postJson('/__idempotency_test/other', ['v' => 1], $headers);

        $echo->assertStatus(200);
        $other->assertStatus(200);
        $other->assertHeaderMissing('Idempotency-Key-Replay');
    }
}
