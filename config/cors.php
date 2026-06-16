<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
    | H4 fix: replace the wildcard with an explicit allowlist driven by .env.
    | CORS_ALLOWED_ORIGINS is a comma-separated list of full origins, e.g.
    |   CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
    | Default is an empty array — no cross-origin requests are accepted unless
    | the env var is explicitly populated. Never set this back to ['*'].
    */
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // crm3 runs cross-origin from the API in production (crm3.cutera.pk →
    // api.cutera.pk). `Content-Disposition` is not a CORS-safelisted response
    // header, so the SPA's fetch-based file downloads (api.ts `downloadBlob`)
    // can't read the server-provided filename unless it's explicitly exposed —
    // without this every report/PDF/Excel export saved as `download.bin`.
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    // The SPA at crm2.cutera.pk sends `credentials: 'include'` on every
    // fetch (api.ts), so the response must echo
    // `Access-Control-Allow-Credentials: true` or modern browsers reject
    // the response. Required for both Passport bearer flows and any
    // future Sanctum cookie flow on a different origin.
    'supports_credentials' => true,

];
