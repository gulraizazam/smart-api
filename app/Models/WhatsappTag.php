<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A WhatsApp chat tag (label) — account-scoped, shared across the team.
 * `is_muting` marks a tag (e.g. "Spam") that hides tagged chats from the
 * default inbox and silences their alerts.
 */
class WhatsappTag extends BaseModel
{
    protected $fillable = [
        'account_id',
        'name',
        'color',
        'is_muting',
    ];

    protected function casts(): array
    {
        return ['is_muting' => 'boolean'];
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(
            WhatsappConversation::class,
            'whatsapp_conversation_tag',
            'whatsapp_tag_id',
            'whatsapp_conversation_id',
        );
    }
}
