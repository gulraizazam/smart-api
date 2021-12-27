<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DBBackups extends Model
{

    protected $fillable = [
        'path', 'file'
    ];

    protected $table = 'db_backups';
}
