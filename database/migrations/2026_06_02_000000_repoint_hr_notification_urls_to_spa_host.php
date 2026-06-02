<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repoint legacy '/admin-v2/...' SPA deep-links stored in hr_notifications.url
 * to the standalone SPA host (config app.spa_url, e.g. https://crm3.cutera.pk).
 *
 * The SPA moved off the same-origin /admin-v2 mount to its own host; rows
 * written before that carry the old relative '/admin-v2/hr/leave-applications'
 * path, which 404s on the standalone SPA. This rewrites them to the absolute
 * configured SPA URL. New rows are already written correctly by
 * HrNotificationService. Only crm3-authored rows match ('/admin-v2/%'), so
 * this does not touch anything crm2 reads from the shared DB.
 */
return new class extends Migration
{
    public function up(): void
    {
        $base = rtrim((string) config('app.spa_url'), '/');
        $prefixLen = strlen('/admin-v2');

        DB::table('hr_notifications')
            ->where('url', 'like', '/admin-v2/%')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($base, $prefixLen): void {
                foreach ($rows as $row) {
                    DB::table('hr_notifications')
                        ->where('id', $row->id)
                        ->update(['url' => $base.substr((string) $row->url, $prefixLen)]);
                }
            });
    }

    public function down(): void
    {
        // One-way data cleanup — the original host segment isn't recorded,
        // so there's no safe automatic rollback.
    }
};
