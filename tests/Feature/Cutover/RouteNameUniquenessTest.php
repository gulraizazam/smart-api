<?php

declare(strict_types=1);

namespace Tests\Feature\Cutover;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * `php artisan route:cache` aborts if ANY two routes share a name. Duplicate
 * names also silently shadow one route with another at runtime. Both are
 * regressions we hit during the Blade cutover (resource-vs-explicit api routes
 * for appointments show/update/destroy). This guards that the route table
 * stays cache-able (AUTH-5).
 */
final class RouteNameUniquenessTest extends TestCase
{
    public function test_no_duplicate_route_names_so_route_cache_works(): void
    {
        $dups = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->countBy()
            ->filter(fn (int $count) => $count > 1);

        $this->assertTrue(
            $dups->isEmpty(),
            'Duplicate route names break `php artisan route:cache`: '
                .$dups->keys()->implode(', '),
        );
    }
}
