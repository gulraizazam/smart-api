<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Activity extends Model
{
    use HasFactory;

    protected $table = 'activities';

    protected $fillable = ['planId', 'action', 'service', 'appointment_type', 'patient', 'created_by', 'invoice_id', 'amount'];

    public $timestamps = false;

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'planId');
    }
}
