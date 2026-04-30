<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill `package_services.sold_by` from the parent
 * `packages.appointment_id → appointments.doctor_id`. The legacy admin's
 * plan-form.js required `sold_by` at service-add time and pre-selected
 * the consultation's doctor; the SPA flow that replaced it never
 * collected the field, so every row created post-cutover landed with
 * `sold_by = NULL`. The plan-detail "Sold by" column rendered "—" for
 * those rows even though the operator who sold the plan is captured on
 * the underlying consultation.
 *
 * The SPA create dialog now persists `sold_by` going forward (mirroring
 * legacy parity); this migration reconciles the historical data so the
 * column reads correctly across the board.
 *
 * Filters:
 *   - only rows where `package_services.sold_by IS NULL`
 *   - only rows whose parent appointment has a doctor (~1 of ~150k rows
 *     has no doctor on the consultation; left as NULL — a NULL is more
 *     honest than guessing).
 *
 * Down: irreversible by design — no breadcrumb of which rows were
 * backfilled vs. authored. The "down" path is a no-op; manual cleanup
 * via the legacy admin's sold-by edit dialog if a specific row needs
 * unwinding.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE package_services ps
            JOIN packages p     ON p.id = ps.package_id
            JOIN appointments a ON a.id = p.appointment_id
            SET ps.sold_by = a.doctor_id
            WHERE ps.sold_by IS NULL
              AND a.doctor_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        // No-op — see class docblock.
    }
};
