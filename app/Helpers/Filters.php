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
     *
     * @param: $file
     *
     * @return: void
     */
    private static function _init($userId, $file)
    {
        if ($file && $file != '') {
            $userId = self::randId($userId);
            $file_with_path = storage_path(self::$base_path.DIRECTORY_SEPARATOR.$userId);

            /*
             * Check if directory exists or not
             */
            is_dir(storage_path(self::$base_path)) ?: mkdir(storage_path(self::$base_path), 0755, true);
            is_dir($file_with_path) ?: mkdir($file_with_path, 0755, true);

            self::$valuestore = Valuestore::make(storage_path(self::$base_path.DIRECTORY_SEPARATOR.$userId.DIRECTORY_SEPARATOR.$file).'.json');
        }
    }

    /**
     * Get All Keys store in file
     *
     * @param: $userId
     *
     * @param: $file
     *
     * @return: mixed
     */
    public static function all($userId, $file)
    {
        self::_init($userId, $file);

        $values = self::$valuestore->all();

        /**
         * after getting value we are now deleting these as per discussion,
         * so that filters do not conflict
         */
        self::flush($userId, $file);

        return $values;
    }

    /**
     * Store as single key or as an array
     *
     * @param: $userId
     *
     * @param: $file
     *
     * @param: $key
     *
     * @param: $value
     *
     * @return: boolean
     */
    public static function put($userId, $file, $key, $value = null)
    {
        self::_init($userId, $file);
        if (is_array($key)) {
            self::$valuestore->put($key);
        } else {
            self::$valuestore->put($key, $value);
        }
        Filters::RemoveEmptySubFolders(storage_path(self::$base_path));

        return true;
    }

    /**
     * Flush all data
     *
     * @param: $userId
     *
     * @param: $file
     *
     * @return: void
     */
    public static function flush($userId, $file)
    {
        self::_init($userId, $file);

        self::$valuestore->flush();
    }

    /**
     * Forget a key
     *
     * @param: $userId
     *
     * @param: $file
     *
     * @param: $key
     *
     * @return: void
     */
    public static function forget($userId, $file, $key)
    {
        self::_init($userId, $file);

        self::$valuestore->forget($key);
    }

    /**
     * Get a key
     *
     * @param: $userId
     *
     * @param: $file
     *
     * @param: $key
     *
     * @return: void
     */
    public static function get($userId, $file, $key)
    {
        self::_init($userId, $file);

        $value = self::$valuestore->get($key);

        /**
         * after getting value we are now deleting these as per discussion,
         * so that filters do not conflict
         */
        self::forget($userId, $file, $key);

        return $value;
    }

    public static function randId($userId)
    {

        $exist = self::getRandId($userId);

        if (! $exist) {
            $rand = uniqid();
            session()->put($userId, $rand);

            return $rand;
        }

        return $exist;
    }

    public static function getRandId($userId)
    {

        if (session()->has($userId)) {
            return session($userId);
        }

        return false;
    }

    public static function RemoveEmptySubFolders($dir)
    {

        try {
            return self::rmdir($dir);
        } catch (\Exception $e) {
            return true;
        }

    }

    public static function rmdir($path)
    {

        $res = scandir($path);
        foreach ($res as $r) {
            $ar = scandir($path.DIRECTORY_SEPARATOR.$r);
            if (count($ar) == 2) {
                rmdir($path.DIRECTORY_SEPARATOR.$r);
            }
        }

        return false;
    }

    public static function remove_filters($file = ''): bool
    {
        try {

            $dir = storage_path(self::$base_path.DIRECTORY_SEPARATOR.self::getRandId(auth()->id()));

            return self::deleteDirectory($dir);

        } catch (\Exception $e) {
            return false;
        }
    }

    private static function deleteDirectory($dir)
    {
        if (! file_exists($dir)) {
            return true;
        }

        if (! is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (! self::deleteDirectory($dir.DIRECTORY_SEPARATOR.$item)) {
                return false;
            }

        }

        return rmdir($dir);
    }

    public static function getCurrentTimeStamp()
    {
        return date('Y-m-d H:i:s');
    }
}
