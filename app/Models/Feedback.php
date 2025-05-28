<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedback';

    protected $guarded = [];

    public function location(){
        return $this->belongsTo(Locations::class);
    }
    public function service()
    {
        return $this->belongsTo(Services::class);
    }
    public function treatment()
    {
        return $this->belongsTo(Services::class,'treatment_id');
    }
    public function patient()
    {
        return $this->belongsTo(User::class);
    }
    public function doctor()
    {
        return $this->belongsTo(User::class);
    }
    public function appointment()
    {
        return $this->belongsTo(Appointments::class);
    }
}
