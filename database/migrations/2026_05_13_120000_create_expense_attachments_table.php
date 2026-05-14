<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-file attachments for cash-flow payments.
 *
 * Replaces the upcoming-to-be-legacy single `expenses.attachment_url`
 * text field with a proper attachments table backed by Cloudflare R2
 * (private `invoices` bucket). The URL column on `expenses` stays put —
 * pre-2026-05-13 rows continue to render their Google Drive link until
 * staff edit them; new rows write here instead.
 *
 * Content-addressed storage: the R2 key is derived from the SHA-256 of
 * the file bytes, so re-uploading identical bytes (the classic
 * "submit the same invoice twice" pattern) yields a second row pointing
 * at the existing blob — no duplicate storage cost, and the SHA gives
 * the flag rules a concrete equality check that survives renames.
 *
 * `expense_id` is NULLABLE so the SPA can upload files while the user
 * is still typing the expense form. A scheduled prune command sweeps
 * unbound (orphan) rows older than the configured TTL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_attachments', function (Blueprint $table) {
            $table->id();
            // `accounts.id` and `users.id` are int(10) unsigned in this
            // DB (legacy migrations), not bigint — must match exactly for
            // the FK to form. `expenses.id` is bigint, matching the
            // cashflow-era convention.
            $table->unsignedInteger('account_id');
            $table->unsignedBigInteger('expense_id')->nullable()
                ->comment('NULL = orphan upload — bound to an expense on form submit, pruned by command if it stays orphan past the TTL.');
            $table->string('file_name', 255)->comment('Original filename as uploaded; for display only.');
            $table->string('file_path', 500)->comment('R2 object key — content-addressed by SHA-256.');
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size')->comment('Bytes; capped at 10 MB by the upload endpoint.');
            $table->char('sha256', 64)->comment('Lowercase hex SHA-256 of the file bytes — drives dedup + the flag-rule equality check.');
            $table->unsignedInteger('uploaded_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('expense_id')->references('id')->on('expenses')->onDelete('set null');
            $table->foreign('uploaded_by')->references('id')->on('users');

            // Index list is intentionally minimal — the table will be
            // queried almost exclusively as "this expense's attachments"
            // (covered by `expense_id`), "this account's orphans"
            // (covered by `account_id` + a soft-delete scan), and
            // "any row matching this SHA for the dedup flag" (covered
            // by [`account_id`, `sha256`]).
            $table->index(['account_id', 'expense_id'], 'expatt_account_expense_idx');
            $table->index(['account_id', 'sha256'], 'expatt_account_sha_idx');
            $table->index('expense_id', 'expatt_expense_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_attachments');
    }
};
