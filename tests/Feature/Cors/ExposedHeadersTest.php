<?php

declare(strict_types=1);

namespace Tests\Feature\Cors;

use Tests\TestCase;

/**
 * crm3 runs cross-origin from the API in production (crm3.cutera.pk →
 * api.cutera.pk). The SPA's fetch-based file downloads (api.ts
 * `downloadBlob`) read the server-provided filename out of the
 * `Content-Disposition` header — but that header is NOT CORS-safelisted,
 * so it's invisible to the browser unless explicitly exposed. Without
 * this every report/PDF/Excel export saved as an unopenable `download.bin`.
 */
class ExposedHeadersTest extends TestCase
{
    public function test_content_disposition_is_exposed_to_cross_origin_callers(): void
    {
        config(['cors.allowed_origins' => ['https://crm3.cutera.pk']]);

        // Any /api/* path triggers the CORS middleware; the request need not
        // succeed — the expose header is added to the actual response
        // regardless of the route's own status.
        $response = $this->withHeaders(['Origin' => 'https://crm3.cutera.pk'])
            ->getJson('/api/user');

        $exposed = $response->headers->get('Access-Control-Expose-Headers') ?? '';

        $this->assertStringContainsStringIgnoringCase(
            'Content-Disposition',
            $exposed,
            'Content-Disposition must be in Access-Control-Expose-Headers, or '
            .'cross-origin SPA downloads lose their filename and save as download.bin.',
        );
    }
}
