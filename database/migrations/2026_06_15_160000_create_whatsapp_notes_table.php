<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal notes on a WhatsApp conversation — private team annotations, never
 * sent to the customer. Many notes per chat, each with an author. Brand-new
 * crm3-only table; crm2 never reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_notes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('whatsapp_conversation_id');
            $table->text('body');
            $table->unsignedBigInteger('created_by')->nullable(); // users.id of the author
            $table->timestamps();

            $table->index('whatsapp_conversation_id', 'wa_notes_conv');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notes');
    }
};
