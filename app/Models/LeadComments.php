<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadComments extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'comment', 'lead_id', 'created_by', 'created_at', 'updated_at',
    ];

    protected $table = 'lead_comments';

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Leads::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
