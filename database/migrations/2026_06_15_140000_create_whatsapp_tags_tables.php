<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp chat tags (labels like "Interested", "Not interested", "Spam") —
 * account-scoped, many-to-many with conversations. `is_muting` marks a tag
 * (e.g. Spam) that hides the chat from the default inbox and silences its
 * alerts ("hide & mute"). Brand-new crm3-only tables; crm2 never reads them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_tags', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('name', 40);
            $table->string('color', 16)->default('neutral'); // a Badge design-token variant
            $table->boolean('is_muting')->default(false); // hide + mute chats with this tag
            $table->timestamps();

            $table->unique(['account_id', 'name'], 'wa_tags_account_name');
        });

        Schema::create('whatsapp_conversation_tag', function (Blueprint $table): void {
            $table->unsignedBigInteger('whatsapp_conversation_id');
            $table->unsignedBigInteger('whatsapp_tag_id');

            $table->primary(['whatsapp_conversation_id', 'whatsapp_tag_id'], 'wa_conv_tag_pk');
            $table->index('whatsapp_tag_id', 'wa_conv_tag_tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversation_tag');
        Schema::dropIfExists('whatsapp_tags');
    }
};
