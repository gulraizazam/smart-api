<?php
/**
 * Created by PhpStorm.
 * User: REDSignal
 * Date: 3/22/2018
 * Time: 3:49 PM
 */

namespace App\Helpers;

use Spatie\Valuestore\Valuestore;

class Filters
{

    /**
     * Base path for settings
     */
    protected static $base_path = 'settings';

    /**
     * Value Store Object Handler
     */
    protected static $valuestore;

    /**
     * Initialize files setup
     *
     * @param: $userId
     * @param: $file
     *
     * @return: void
     */
    private static function _init($userId, $file) {
        $file_with_path = storage_path(self::$base_path . DIRECTORY_SEPARATOR . $userId);

        /*
         * Check if directory exists or not
         */
        is_dir(storage_path(self::$base_path)) ?: mkdir(storage_path(self::$base_path), 0755, true);
        is_dir($file_with_path) ?: mkdir($file_with_path, 0755, true);

        self::$valuestore = Valuestore::make(storage_path(self::$base_path . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . $file) . '.json');
    }


    /**
     * Get All Keys store in file
     *
     * @param: $userId
     * @param: $file
     *
     * @return: mixed
     */
    public static function all($userId, $file) {
        self::_init($userId, $file);

        return self::$valuestore->all();
    }

    /**
     * Store as single key or as an array
     *
     * @param: $userId
     * @param: $file
     * @param: $key
     * @param: $value
     *
     * @return: boolean
     */
    public static function put($userId, $file, $key, $value = null) {
        self::_init($userId, $file);

        if(is_array($key)) {
            self::$valuestore->put($key);
        } else {
            self::$valuestore->put($key, $value);
        }

        return true;
    }

    /**
     * Flush all data
     *
     * @param: $userId
     * @param: $file
     *
     * @return: void
     */
    public static function flush($userId, $file) {
        self::_init($userId, $file);

        self::$valuestore->flush();
    }

    /**
     * Forget a key
     *
     * @param: $userId
     * @param: $file
     * @param: $key
     *
     * @return: void
     */
    public static function forget($userId, $file, $key) {
        self::_init($userId, $file);

        self::$valuestore->forget($key);
    }

    /**
     * Get a key
     *
     * @param: $userId
     * @param: $file
     * @param: $key
     *
     * @return: void
     */
    public static function get($userId, $file, $key) {
        self::_init($userId, $file);

        return self::$valuestore->get($key);
    }

}