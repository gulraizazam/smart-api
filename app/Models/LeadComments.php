<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadComments extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'comment', 'lead_id', 'created_by','created_at', 'updated_at'
    ];

    protected $table = 'lead_comments';

    /**
     * Get the lead that owns the comments.
     */
    public function lead()
    {
        return $this->belongsTo('App\Models\Leads');
    }

    /**
     * Get the User that owns the Lead comment.
     */
    public function user()
    {
        return $this->belongsTo('App\User', 'created_by');
    }
}
