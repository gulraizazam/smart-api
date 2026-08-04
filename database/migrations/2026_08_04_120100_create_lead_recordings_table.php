<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per successfully-downloaded recording for a `lead_calls` row.
 *
 * Populated by DownloadLeadRecordingJob after Plivo's recording webhook
 * lands. Phase 1 stores the audio bytes on the server's local disk under
 * `storage/app/lead-recordings/{account_id}/{lead_id}/{lead_call_id}.mp3`;
 * the R2 migration path (see plan §Storage) is a one-line swap of the disk
 * name — the schema stays identical.
 *
 * `sha256` gives us dedup + integrity: the download job hashes the stream
 * while writing, and if a webhook re-delivery lands (Plivo will re-POST if
 * we return non-2xx) the existing row is reused instead of writing a
 * second copy.
 *
 * `plivo_recording_id` is Plivo's own recording ID — we index it because
 * the recording webhook posts it as its stable key, so we can detect a
 * re-delivery before we spend bandwidth on the download.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_recordings', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('account_id');
            // lead_calls.id is bigint (our own table, native ->id()), but
            // legacy leads.id is int unsigned — FK types must match exactly.
            $table->unsignedBigInteger('lead_call_id')->unique(); // strictly 1:1
            $table->unsignedInteger('lead_id');

            // Relative to `storage/app/` when disk = local, or the R2 object
            // key when disk = r2_invoices. Always includes the account prefix
            // so a tenant leak means we notice at the path level.
            $table->string('file_path', 512);
            $table->unsignedInteger('file_size')->comment('Bytes.');
            $table->string('mime_type', 64)->default('audio/mpeg');
            $table->char('sha256', 64);
            $table->unsignedInteger('duration_seconds');

            $table->string('plivo_recording_id', 64)->nullable();
            $table->timestamp('uploaded_at');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('lead_call_id')->references('id')->on('lead_calls')->onDelete('cascade');
            $table->foreign('lead_id')->references('id')->on('leads');

            $table->index(['account_id', 'lead_id'], 'lead_recordings_lead_idx');
            $table->index(['account_id', 'sha256'], 'lead_recordings_sha_idx');
            $table->index('plivo_recording_id', 'lead_recordings_plivo_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_recordings');
    }
};
