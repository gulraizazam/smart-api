<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-file attachments for cash-flow Transfers (pool→pool movements).
 *
 * Replaces the legacy `cash_transfers.attachment_url` text field (a
 * pasted Google Drive link) with a proper attachments table backed by
 * Cloudflare R2 (private `invoices` bucket — shared with payments).
 * The legacy URL column stays put — pre-2026-05-15 rows continue to
 * render their drive link, new transfers write here instead.
 *
 * Mirrors `expense_attachments` exactly — same content-addressed
 * storage (SHA-256 → R2 key), same orphan-upload lifecycle (rows
 * created with NULL transfer_id, bound on form submit), same soft-
 * delete + R2-keep-forever policy (prune command sweeps unreferenced
 * blobs later).
 *
 * Scoped to pool→pool only for now — staff advances/returns/handovers
 * historically never had attachments and the form doesn't offer them.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cash_transfer_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('account_id');
            $table->unsignedBigInteger('cash_transfer_id')->nullable()
                ->comment('NULL = orphan upload — bound on transfer submit, pruned by command if it stays orphan past TTL.');
            $table->string('file_name', 255)->comment('Original filename as uploaded; for display only.');
            $table->string('file_path', 500)->comment('R2 object key — content-addressed by SHA-256.');
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size')->comment('Bytes; capped at 10 MB by the upload endpoint.');
            $table->char('sha256', 64)->comment('Lowercase hex SHA-256 of the file bytes — drives dedup + content-addressed key.');
            $table->unsignedInteger('uploaded_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('cash_transfer_id')->references('id')->on('cash_transfers')->onDelete('set null');
            $table->foreign('uploaded_by')->references('id')->on('users');

            $table->index(['account_id', 'cash_transfer_id'], 'ctatt_account_transfer_idx');
            $table->index(['account_id', 'sha256'], 'ctatt_account_sha_idx');
            $table->index('cash_transfer_id', 'ctatt_transfer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transfer_attachments');
    }
};
