<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class GlobalOperatorSettings extends BaseModal
{
    use SoftDeletes;

    protected $fillable = [
        'operator_name', 'username', 'password', 'mask', 'test_mode', 'url', 'string_1', 'string_2',
        'account_id', 'created_at', 'updated_at',
    ];

    protected $table = 'global_operator_settings';

    protected $hidden = ['password'];

    /**
     * Get all records as an ID-keyed dictionary array.
     */
    public static function getAllRecordsDictionary(): array
    {
        return self::get()->getDictionary();
    }
}
