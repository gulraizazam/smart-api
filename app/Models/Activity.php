<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Activity extends Model
{
    use HasFactory;

    protected $table = 'activities';

    protected $guarded = [];

    public $timestamps = false;
    
    protected $casts = [
        'created_by' => 'integer',
        'patient_id' => 'integer',
        'centre_id' => 'integer',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'planId');
    }

    public function serviceR()
    {
        return $this->belongsTo(Services::class, 'service_id');
    }
    

    public function centre()
    {
        return $this->belongsTo(Locations::class, 'centre_id');
    }
    

    public function patientR()
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }
    
    
    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function rescheduleBy()
    {
        return $this->belongsTo(User::class, 'rescheduled_by');
    }
    public function deleteBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
