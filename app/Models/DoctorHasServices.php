<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DoctorHasServices extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'service_id'];

    protected $table = 'doctor_has_services';

    public $timestamps = false;
}
