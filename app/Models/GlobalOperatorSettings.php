<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class GlobalOperatorSettings extends BaseModal
{
    use SoftDeletes;

    protected $fillable = [
        'operator_name', 'username', 'password', 'mask', 'test_mode', 'url', 'string_1', 'string_2',
        'account_id', 'created_at', 'updated_at'];

    protected $table = 'global_operator_settings';

    /**
     * Get All Records
     *
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getAllRecordsDictionary()
    {
        return self::get()->getDictionary();
    }
}
