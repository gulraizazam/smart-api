<?php

declare(strict_types=1);

namespace App\Models;

/**
 * A saved quick-answer for the WhatsApp inbox, shared across the team within an
 * account. Inserted into the composer by the agent — never sent automatically.
 */
class WhatsappCannedReply extends BaseModel
{
    protected $fillable = [
        'account_id',
        'title',
        'body',
        'created_by',
    ];
}
