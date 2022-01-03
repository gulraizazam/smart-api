<?php


namespace App\HelperModule;


class ApiHelper
{

    /**
     *
     * return api response according to status
     *
     * @param int $status
     * @param string $message
     * @param null $data
     * @return \Illuminate\Http\JsonResponse
     */
    static public function apiResponse(int $status, string $message = 'Success', $data = null)
    {
        try {
            return response()->json(['status' => $status, 'message' => $message, 'data' => $data], $status);
        } catch (\Exception $e) {
            return response()->json(['status' => config('constants.api_status.error'), 'message' => $e->getMessage(), 'data' => null], config('constants.api_status.error'));
        }
    }
}
