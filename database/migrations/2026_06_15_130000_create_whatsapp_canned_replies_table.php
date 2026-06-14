<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canned replies (saved quick-answers) for the WhatsApp inbox — account-scoped,
 * shared across the team. Brand-new crm3-only table; crm2 never reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_canned_replies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('title', 80);
            $table->text('body');
            $table->unsignedBigInteger('created_by')->nullable(); // users.id of the author
            $table->timestamps();

            $table->index('account_id', 'wa_canned_account');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_canned_replies');
    }
};
