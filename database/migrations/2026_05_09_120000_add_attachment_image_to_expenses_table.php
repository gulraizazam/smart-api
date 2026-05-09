<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a locally-uploaded image attachment to expenses.
 *
 * Until now the only supported receipt was a Google Drive URL, which
 * forces every operator to upload a file to Drive first and then paste
 * the link. Branch staff (especially on mobile) need to attach a phone
 * photo of the receipt directly. This column stores the path relative
 * to the `public` disk; the existing `attachment_url` field remains
 * for cases where the operator prefers to link a Drive document.
 *
 * Either column may now be present; the URL field is now fully optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('attachment_image', 255)
                ->nullable()
                ->after('attachment_url')
                ->comment('Path on the `public` disk to a locally-uploaded receipt image');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('attachment_image');
        });
    }
};
