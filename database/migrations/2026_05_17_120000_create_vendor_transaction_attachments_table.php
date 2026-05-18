<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-file attachments for vendor transactions (purchases / payments).
 *
 * Exact mirror of `expense_attachments` (2026_05_13_120000) and
 * `cash_transfer_attachments` (2026_05_15_130000) — same Cloudflare R2
 * private `invoices` bucket, same content-addressed-by-SHA-256 storage,
 * same orphan-upload model. Replaces the to-be-legacy single
 * `vendor_transactions.attachment_url` Google Drive link; that column
 * stays put so pre-cutover rows keep rendering their link until edited.
 *
 * `vendor_transaction_id` is NULLABLE so the SPA can upload files while
 * the purchase/deliver form is still being filled, then bind the
 * returned IDs on submit (same as `expense_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_transaction_attachments', function (Blueprint $table) {
            // `accounts.id` and `users.id` are int(10) unsigned in this
            // DB (legacy migrations); `vendor_transactions.id` is bigint
            // per the cashflow-era convention — match each exactly so the
            // FKs form.
            $table->id();
            $table->unsignedInteger('account_id');
            $table->unsignedBigInteger('vendor_transaction_id')->nullable()
                ->comment('NULL = orphan upload — bound to a vendor transaction on form submit, pruned by command if it stays orphan past the TTL.');
            $table->string('file_name', 255)->comment('Original filename as uploaded; for display only.');
            $table->string('file_path', 500)->comment('R2 object key — content-addressed by SHA-256.');
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size')->comment('Bytes; capped at 10 MB by the upload endpoint.');
            $table->char('sha256', 64)->comment('Lowercase hex SHA-256 of the file bytes — drives dedup.');
            $table->unsignedInteger('uploaded_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('vendor_transaction_id')->references('id')->on('vendor_transactions')->onDelete('set null');
            $table->foreign('uploaded_by')->references('id')->on('users');

            $table->index(['account_id', 'vendor_transaction_id'], 'vtxatt_account_tx_idx');
            $table->index(['account_id', 'sha256'], 'vtxatt_account_sha_idx');
            $table->index('vendor_transaction_id', 'vtxatt_tx_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_transaction_attachments');
    }
};
