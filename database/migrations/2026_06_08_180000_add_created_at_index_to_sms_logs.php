<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index sms_logs.created_at so the date-keyed retention sweep (logs:prune)
 * — and any "recent SMS" lookup — uses an index instead of a full scan.
 *
 * Additive + coexistence-safe: a new secondary index changes no column,
 * permission, or response shape that crm2 reads. The existing composite
 * (lead_id, created_at) does NOT cover `WHERE created_at < ?` because
 * created_at is not its leading column. Idempotent (guarded by index name).
 */
return new class extends Migration
{
    private const TABLE = 'sms_logs';

    private const INDEX = 'sms_logs_created_at_index';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE) || $this->indexExists()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->index('created_at', self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! $this->indexExists()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }

    private function indexExists(): bool
    {
        return collect(DB::select(
            'SHOW INDEX FROM `'.self::TABLE.'` WHERE Key_name = \''.self::INDEX.'\'',
        ))->isNotEmpty();
    }
};
