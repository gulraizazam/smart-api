<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp Cloud API (Meta) — Phase 1 storage: one conversation row per
 * WhatsApp user (`wa_id` = E.164 digits without '+'), one message row per
 * inbound/outbound message.
 *
 * Two columns are load-bearing:
 *   - `whatsapp_messages.wamid` UNIQUE — Meta retries webhook deliveries
 *     until it gets a 2xx, so the same message can arrive more than once;
 *     the unique index is the idempotency backstop.
 *   - `whatsapp_conversations.last_inbound_at` — anchors the 24-hour
 *     customer-service window (free-form text is only allowed within 24h
 *     of the customer's last inbound message; outside it, templates only).
 *
 * `patient_id` stays nullable/unlinked in Phase 1 — wa_id → patient matching
 * (against users.phone_normalized) hooks in later via the webhook controller.
 *
 * COEXISTENCE: brand-new tables, additive, crm2-invisible — no shared
 * column/permission/response-shape touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_id', 32)->unique();          // WhatsApp user id (E.164 digits, no '+')
            $table->string('profile_name')->nullable();     // contacts[].profile.name from the webhook
            $table->unsignedBigInteger('patient_id')->nullable(); // future link to users.id (Patients)
            $table->timestamp('last_inbound_at')->nullable();     // 24h service-window anchor
            $table->timestamps();

            $table->index('patient_id', 'wa_conversations_patient');
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('whatsapp_conversation_id');
            $table->string('wamid', 128)->nullable()->unique(); // Meta message id; null when a send fails
            $table->string('direction', 10);                    // inbound | outbound
            $table->string('type', 20)->default('text');        // text | template | image | ...
            $table->text('body')->nullable();
            $table->string('status', 20)->nullable();           // inbound: received; outbound: accepted/sent/delivered/read/failed
            $table->json('payload')->nullable();                // raw Meta message / API response
            $table->timestamps();

            $table->index(['whatsapp_conversation_id', 'created_at'], 'wa_messages_conv_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
    }
};
