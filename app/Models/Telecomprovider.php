<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Telecomprovider extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'active', 'created_at', 'updated_at', 'deleted_at'];

    protected static array $_fillable = ['name', 'active'];

    protected $table = 'telecomproviders';

    protected static string $_table = 'telecomproviders';
}
