<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action_by',
        'action_for',
        'action',
        'screen',
        'address',
        'date',
        'time',
        'type',
    ];

    public $timestamps = false;
}
