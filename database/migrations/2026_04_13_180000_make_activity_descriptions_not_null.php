<?php

declare(strict_types=1);

use App\Helpers\ActivityLogRenderer;
use App\Models\Activity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills any remaining NULL descriptions on `activities` and then
 * tightens the column to NOT NULL.
 *
 * Producers (App\Helpers\ActivityLogger and the half-dozen services that
 * write activities) already populate description at write time. This
 * migration finishes the historical cleanup and lets the read path drop
 * its render-on-the-fly fallback.
 *
 * Run on production-sized data: 397k rows × ~30 string concats =
 * a few minutes per backfill pass. Reversible — `down()` restores
 * nullability without dropping any data.
 *
 * If running in production with very large activities tables, prefer
 * invoking `php artisan activities:backfill-descriptions` first to
 * verify output, then run this migration which will only ALTER (the
 * backfill step inside up() will be a no-op).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->backfillNulls();

        $remaining = DB::table('activities')->whereNull('description')->count();
        if ($remaining > 0) {
            throw new RuntimeException(
                "Cannot enforce NOT NULL on activities.description: {$remaining} rows still NULL after backfill. ".
                'Investigate manually and rerun: php artisan activities:backfill-descriptions'
            );
        }

        Schema::table('activities', function ($table): void {
            $table->string('description', 2000)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function ($table): void {
            $table->string('description', 2000)->nullable()->change();
        });
    }

    private function backfillNulls(): void
    {
        Activity::whereNull('description')
            ->with(['user', 'serviceR', 'patientR', 'centre'])
            ->orderBy('id')
            ->chunkById(1000, function ($rows): void {
                $updates = [];
                foreach ($rows as $row) {
                    $description = ActivityLogRenderer::render($row);
                    // varchar(2000) hard cap — truncate defensively.
                    $updates[(int) $row->id] = mb_substr($description, 0, 2000);
                }

                foreach (array_chunk($updates, 250, true) as $slice) {
                    $ids = array_keys($slice);
                    $caseSql = '';
                    $bindings = [];
                    foreach ($slice as $id => $description) {
                        $caseSql .= ' WHEN ? THEN ?';
                        $bindings[] = $id;
                        $bindings[] = $description;
                    }
                    $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
                    $bindings = array_merge($bindings, $ids);

                    DB::transaction(function () use ($caseSql, $idPlaceholders, $bindings): void {
                        DB::update(
                            "UPDATE activities SET description = CASE id{$caseSql} END WHERE id IN ({$idPlaceholders})",
                            $bindings,
                        );
                    });
                }
            });
    }
};
