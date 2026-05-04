<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 0 instrumentation for the listing-API perf optimization.
 *
 * - Adds an `X-Response-Time-Ms` header on every response so the SPA's
 *   network panel shows real server time at a glance.
 * - When a request takes longer than the slow threshold, writes a single
 *   summary line to `storage/logs/slow-queries.log` with route, method,
 *   path, total wall time, query count, and total query time. This catches
 *   "death by many fast queries" cases that the per-query >250ms listener
 *   in AppServiceProvider misses.
 *
 * Off by default. Enable with `LOG_SLOW_QUERIES=true` in `.env`. Threshold
 * tunable via `LOG_SLOW_QUERIES_REQUEST_MS` (default 500). Removed once
 * Phase 2 cleanup ships.
 */
class LogSlowRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = (bool) env('LOG_SLOW_QUERIES', false);

        if (! $enabled) {
            return $next($request);
        }

        $start = microtime(true);
        $queries = [];

        // Bind a per-request listener so we can count queries + sum query
        // time without colliding with the global slow-query listener in
        // AppServiceProvider. The closure runs only while this request is
        // in flight.
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->time;
        });

        $response = $next($request);

        $totalMs = (microtime(true) - $start) * 1000;
        $response->headers->set('X-Response-Time-Ms', (string) round($totalMs, 1));

        // Always emit a per-request summary line when the flag is on. This
        // (a) creates the log file on the very first request so the user can
        // see things working, and (b) gives us the per-route timing series
        // we need to fill out the baseline doc, not just the >threshold ones.
        // Per-query slow lines are still gated by their own threshold in the
        // DB::listen callback.
        $route = $request->route();
        $routeName = $route ? ($route->getName() ?: $route->uri()) : 'no-route';

        $threshold = (float) env('LOG_SLOW_QUERIES_REQUEST_MS', 200);
        $payload = [
            'total_ms' => round($totalMs, 1),
            'route' => $routeName,
            'method' => $request->getMethod(),
            'path' => $request->path(),
            'query_count' => count($queries),
            'query_total_ms' => round(array_sum($queries), 1),
        ];

        $channel = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/slow-queries.log'),
            'level' => 'debug',
        ]);

        if ($totalMs >= $threshold) {
            $channel->warning('slow-request', $payload);
        } else {
            $channel->info('request', $payload);
        }

        return $response;
    }
}
