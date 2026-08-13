<?php

// R2 (Cloudflare) disks fall back to the LOCAL driver when R2_DRIVER /
// R2_INVOICES_DRIVER is `local`. The DEFAULT is `local` — this project's policy
// is that NOTHING goes to cloud storage; everything stays inside the Laravel
// app's storage/ tree. A cloud (s3) disk is only used when an env EXPLICITLY
// opts in with R2_DRIVER=s3, so a misconfigured/fresh deployment can never
// accidentally ship uploads off-box. In local mode files are read back through
// PHP via an extensionless
// signed route (AppServiceProvider maps temporaryUrl() to `local-files.serve` —
// see App\Http\Controllers\LocalSignedFileController for why static/image-URL
// serving fails on this host). So NO `url` here (a `url` would make
// CentreResource emit a static `…jpg` link the host won't serve) and NO `serve`
// key. Root sits under app/public so files are easy to inspect; the signed
// route is the only path that serves them. In s3 mode the disks are cloud-backed
// and mint their own presigned URLs (r2 public bucket, r2_invoices private).
$r2Driver = env('R2_DRIVER', 'local');
$r2Local = $r2Driver === 'local';
$r2Disk = [
    'driver' => $r2Driver,
    'key' => env('CLOUDFLARE_R2_ACCESS_KEY_ID'),
    'secret' => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY'),
    'region' => 'auto',
    'bucket' => env('CLOUDFLARE_R2_BUCKET'),
    'endpoint' => env('CLOUDFLARE_R2_ENDPOINT'),
    'use_path_style_endpoint' => false,
    'visibility' => 'public',
    'throw' => false,
    'root' => $r2Local ? storage_path('app/public/r2') : storage_path('app/private/r2'),
    'url' => $r2Local ? null : env('CLOUDFLARE_R2_URL'),
];

$r2InvoicesDriver = env('R2_INVOICES_DRIVER', 'local');
$r2InvoicesLocal = $r2InvoicesDriver === 'local';
$r2InvoicesDisk = [
    'driver' => $r2InvoicesDriver,
    'key' => env('CLOUDFLARE_R2_ACCESS_KEY_ID'),
    'secret' => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY'),
    'region' => 'auto',
    'bucket' => env('CLOUDFLARE_R2_INVOICES_BUCKET', 'invoices'),
    'endpoint' => env('CLOUDFLARE_R2_ENDPOINT'),
    'use_path_style_endpoint' => false,
    'visibility' => $r2InvoicesLocal ? 'public' : 'private',
    'throw' => false,
    'root' => $r2InvoicesLocal ? storage_path('app/public/r2_invoices') : storage_path('app/private/r2_invoices'),
];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],

        // Built above (driver/url depend on R2_DRIVER) — see top of file.
        'r2' => $r2Disk,

        /*
         * Cash-flow Payments attachments (invoices / receipts). Same R2
         * account as the HRM bucket above — only the bucket name differs.
         * Visibility is PRIVATE: every download is fronted by a
         * short-lived signed URL minted by Laravel, so a leaked URL has
         * a 15-minute leak window instead of forever. Built above.
         */
        'r2_invoices' => $r2InvoicesDisk,

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];