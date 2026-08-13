<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\Storage\R2DocumentService;
use Illuminate\Database\Eloquent\Model;

/**
 * Wipes R2 objects when their owning DB row is *force*-deleted.
 *
 * Soft-deletes are a deliberate no-op — the file stays so the row can be
 * restored with its content intact. A later prune job (or manual admin
 * action) that calls `forceDelete()` is what actually reclaims R2 space.
 *
 * Models opt in with two small methods:
 *
 *   public function r2DocumentKey(): ?string;
 *   public function r2DocumentService(): \App\Services\Storage\R2DocumentService;
 *
 * Register via `Model::observe(R2CleanupObserver::class)` in
 * AppServiceProvider so the cleanup fires wherever force-delete runs
 * (UI, artisan CLI, queued pruners).
 */
final class R2CleanupObserver
{
    public function forceDeleted(Model $model): void
    {
        if (! method_exists($model, 'r2DocumentKey')
            || ! method_exists($model, 'r2DocumentService')) {
            return;
        }

        $key = $model->r2DocumentKey();
        if (! is_string($key) || $key === '') {
            return;
        }

        try {
            $service = $model->r2DocumentService();
            if ($service instanceof R2DocumentService) {
                $service->delete($key);
            }
        } catch (\Throwable) {
            // Best-effort: never 500 the caller on an orphaned-but-harmless
            // object. The DB row is the source of truth.
        }
    }
}
