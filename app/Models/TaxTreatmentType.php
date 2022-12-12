<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxTreatmentType extends Model
{
    use SoftDeletes;

    protected $fillable = ['name','created_at', 'updated_at', 'deleted_at'];

    protected static $_fillable = ['name','created_at', 'updated_at', 'deleted_at'];

    protected $table = 'tax_treatment_type';

    protected static $_table = 'tax_treatment_type';
}
