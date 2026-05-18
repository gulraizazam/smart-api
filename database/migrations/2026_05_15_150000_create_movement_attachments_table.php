<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic attachments for the three staff-side movement kinds:
 * staff_advance, staff_return, staff_transfer.
 *
 * Pool→pool transfers keep using `cash_transfer_attachments` — that
 * table was shipped a few hours earlier and is already wired through
 * the SPA + backend. Splitting the storage this way avoids the
 * cross-table refactor a unified `movement_attachments` would
 * require while still giving the three staff combos a real attachment
 * surface (asymmetric before today: drop-zone on pool→pool, nothing
 * on the staff legs).
 *
 * Same R2 bucket as the rest of cashflow (`invoices`). Same content-
 * addressed-by-SHA storage so dropping the same file on a staff
 * advance and a payment reuses the blob. Soft-delete + R2-keep-
 * forever policy mirrors the other attachment tables.
 *
 * `movement_kind` + `movement_id` are nullable as a pair — orphan
 * uploads land with both NULL and `MovementService::create` populates
 * them when the user submits the form.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('movement_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('account_id');
            $table->enum('movement_kind', ['staff_advance', 'staff_return', 'staff_transfer'])
                ->nullable()
                ->comment('Discriminator for which staff table the attachment ultimately binds to. NULL = orphan upload.');
            $table->unsignedBigInteger('movement_id')->nullable()
                ->comment('FK target inside the table named by movement_kind. NULL while orphan; populated on form submit.');
            $table->string('file_name', 255);
            $table->string('file_path', 500)->comment('R2 object key — content-addressed by SHA-256.');
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size');
            $table->char('sha256', 64);
            $table->unsignedInteger('uploaded_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('uploaded_by')->references('id')->on('users');
            // No FK on (movement_kind, movement_id) — polymorphic shape
            // means the target table varies by kind. The MovementService
            // bind step validates the (kind, id) pair against the right
            // table before writing.

            $table->index(['account_id', 'movement_kind', 'movement_id'], 'movatt_account_kind_id_idx');
            $table->index(['account_id', 'sha256'], 'movatt_account_sha_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_attachments');
    }
};
