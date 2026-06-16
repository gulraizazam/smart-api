<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One row per WhatsApp user we have ever exchanged messages with, keyed by
 * `wa_id` (E.164 digits without '+'). `last_inbound_at` anchors the 24-hour
 * customer-service window; `last_read_at` marks when the team last opened
 * the conversation (inbound messages newer than it are unread).
 *
 * No `account_id` column: the clinic runs a single WhatsApp number, and the
 * webhook carries no tenant context. Patient linking (`patient_id`, scoped
 * to account at match time) lands in a later phase.
 */
class WhatsappConversation extends BaseModel
{
    /**
     * Meta's messaging rule: free-form text only within this many hours of
     * the customer's last inbound message; outside it, templates only.
     */
    public const SERVICE_WINDOW_HOURS = 24;

    protected $fillable = [
        'wa_id',
        'profile_name',
        'patient_id',
        'last_inbound_at',
        'last_read_at',
        'opted_out_at',
        'assigned_to_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'last_inbound_at' => 'datetime',
            'last_read_at' => 'datetime',
            'opted_out_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * True once the customer has texted STOP (Meta policy: no further sends
     * until they opt back in). Single source for the rule; WhatsAppService and
     * the reply endpoints delegate here.
     */
    public function isOptedOut(): bool
    {
        return $this->opted_out_at !== null;
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(WhatsappNote::class);
    }

    /**
     * The matched patient (users.user_type_id = 3), linked by the webhook from
     * the customer's phone number. Null until matched / if no patient matches.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }

    /** The staff member handling this conversation (users.id), or null. */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(WhatsappMessage::class)->latestOfMany();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            WhatsappTag::class,
            'whatsapp_conversation_tag',
            'whatsapp_conversation_id',
            'whatsapp_tag_id',
        );
    }

    /**
     * True when any applied tag is a muting tag (e.g. "Spam") — such chats are
     * hidden from the default inbox and don't raise alerts. Requires `tags`
     * to be loaded.
     */
    public function isMuted(): bool
    {
        return $this->tags->contains('is_muting', true);
    }

    /**
     * Conversations with at least one unread inbound message — i.e. inbound
     * newer than `last_read_at` (NULL = never opened, everything unread).
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereHas('messages', function ($q): void {
            $q->where('direction', 'inbound')
                ->where(function ($w): void {
                    $w->whereNull('whatsapp_conversations.last_read_at')
                        ->orWhereColumn('whatsapp_messages.created_at', '>', 'whatsapp_conversations.last_read_at');
                });
        });
    }

    /**
     * Conversations whose 24h reply window has CLOSED — never messaged us, or
     * not within the last 24 hours. The inverse of windowIsOpen(); same rule.
     */
    public function scopeWindowClosed(Builder $query): Builder
    {
        return $query->where(function ($w): void {
            $w->whereNull('last_inbound_at')
                ->orWhere('last_inbound_at', '<=', now()->subHours(self::SERVICE_WINDOW_HOURS));
        });
    }

    /**
     * True while the 24h customer-service window is open — i.e. the customer
     * has sent us a message within the last 24 hours. Single source of the
     * rule; WhatsAppService delegates here.
     */
    public function windowIsOpen(): bool
    {
        return $this->last_inbound_at !== null
            && $this->last_inbound_at->gt(now()->subHours(self::SERVICE_WINDOW_HOURS));
    }
}
