<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * /api/version is the build-provenance endpoint (added 2026-06 alongside the
 * gated deploy scripts). It must be PUBLIC (so "what's live on api?" is a
 * one-second check and deploy-api.sh can verify the upload) and return a
 * stable { commit, builtAt } shape. The commit is stamped on the server by
 * scripts/deploy-api.sh; with no stamp file present it falls back to 'unknown'
 * rather than erroring.
 */
class VersionEndpointTest extends TestCase
{
    public function test_version_endpoint_is_public_and_returns_commit_shape(): void
    {
        $response = $this->getJson('/api/version');

        // Public: a 401 here would mean it slipped behind auth.
        $response->assertOk();
        $response->assertJsonStructure(['commit', 'builtAt']);
    }
}
