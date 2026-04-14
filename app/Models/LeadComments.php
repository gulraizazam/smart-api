<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadComments extends Model
{
    use SoftDeletes;

    protected $table = 'lead_comments';

    protected $fillable = [
        'comment', 'lead_id', 'created_by', 'account_id', 'created_at', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'lead_id' => 'integer',
            'created_by' => 'integer',
            'account_id' => 'integer',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Leads::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
