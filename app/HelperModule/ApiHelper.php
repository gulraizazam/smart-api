<?php


namespace App\HelperModule;


class ApiHelper
{

    /**
     *
     * return api response according to status
     *
     * @param int $code
     * @param bool $status
     * @param string $message
     * @param bool $status
     * @param null $data
     * @return \Illuminate\Http\JsonResponse
     */
    static public function apiResponse(int $code, string $message = 'Success', bool $status = true, $data = null)
    {
        try {
            return response()->json(['status' => $status, 'message' => $message, 'data' => $data], $code);
        } catch (\Exception $e) {
            return response()->json(['status' => config('constants.api_status.error'), 'message' => $e->getMessage(), 'data' => null], config('constants.api_status.error'));
        }
    }

    /**
     *
     * return api response according to status
     *
     * @param int $status
     * @param string $message
     * @param array $data
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

}
