<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per WhatsApp user we have ever exchanged messages with, keyed by
 * `wa_id` (E.164 digits without '+'). `last_inbound_at` anchors the 24-hour
 * customer-service window — see WhatsAppService::windowIsOpen().
 *
 * No `account_id` column: the clinic runs a single WhatsApp number, and the
 * webhook carries no tenant context. Patient linking (`patient_id`, scoped
 * to account at match time) lands in a later phase.
 */
class WhatsappConversation extends BaseModel
{
    protected $fillable = [
        'wa_id',
        'profile_name',
        'patient_id',
        'last_inbound_at',
    ];

    protected function casts(): array
    {
        return [
            'last_inbound_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }
}
