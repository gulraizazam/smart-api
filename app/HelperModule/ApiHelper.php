<?php

namespace App\HelperModule;

class ApiHelper
{
    /**
     * return api response according to status
     *
     * @param  null  $data
     * @return \Illuminate\Http\JsonResponse
     */
    public static function apiResponse(int $code, string $message = 'Success', bool $status = true, $data = null)
    {
        try {
            return response()->json(['status' => $status, 'message' => $message, 'data' => $data], $code);
        } catch (\Exception $e) {
            return response()->json(['status' => config('constants.api_status.error'), 'message' => $e->getMessage(), 'data' => null], config('constants.api_status.error'));
        }
    }

    /**
     * return api response according to status
     *
     * @param  int  $status
     * @param  string  $message
     * @param  array  $data
     * @return \Illuminate\Http\JsonResponse
     */
    public static function apiDataTable($data = [])
    {
        try {
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['status' => config('constants.api_status.error'), 'message' => $e->getMessage(), 'data' => null], config('constants.api_status.error'));
        }
    }

    public static function makeResponse($data = [], $view = null, $code = 200, $status = true, $message = 'Record found')
    {
        try {
            if (request()->hasHeader('Authorization')) {
                return self::apiResponse($code, $message, $status, $data);
            }

            return view($view, $data);
        } catch (\Exception $e) {
            if (request()->hasHeader('Authorization')) {
                return response()->json(['status' => config('constants.api_status.error'), 'message' => $e->getMessage(), 'data' => null], config('constants.api_status.error'));
            }

            throw $e;
        }
    }

    public static function denyAccess()
    {

        if (request()->hasHeader('Authorization')) {
            return ApiHelper::apiResponse(config('constants.api_status.unauthorized'), 'You are not authorized to access this resource.', false);
        }

        return abort(401);
    }

    /**
     * Api Exception Response
     *
     * @param  string  $type
     * @return \Illuminate\Http\JsonResponse
     */
    public static function apiException(\Exception $e)
    {
        $code = config('constants.api_status.error');
        $msg = 'Something went wrong, please try again later.';
        if (config('app.debug')) {
            $msg = $e->getMessage().' Line '.$e->getLine().' File '.$e->getFile();
        }

        return self::apiResponse($code, $msg, false);
    }
}
