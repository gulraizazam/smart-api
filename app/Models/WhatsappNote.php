<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A private internal note on a WhatsApp conversation — team-only, never sent
 * to the customer.
 */
class WhatsappNote extends BaseModel
{
    protected $fillable = [
        'whatsapp_conversation_id',
        'body',
        'created_by',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
