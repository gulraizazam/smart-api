<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per WhatsApp message, inbound or outbound. `wamid` (Meta's message
 * id) is DB-unique — Meta redelivers webhooks until it gets a 2xx, and the
 * unique index makes duplicate deliveries idempotent.
 */
class WhatsappMessage extends BaseModel
{
    protected $fillable = [
        'whatsapp_conversation_id',
        'wamid',
        'direction',
        'type',
        'body',
        'status',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'whatsapp_conversation_id');
    }
}
