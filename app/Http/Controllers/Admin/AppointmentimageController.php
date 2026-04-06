<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Appointment\AppointmentImageService;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AppointmentimageController extends Controller
{
    public function __construct(
        private readonly AppointmentImageService $appointmentImageService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        if (! Gate::allows('appointments_image_manage')) {
            return abort(401);
        }
        $appointment = $this->appointmentImageService->getAppointment($id);

        return view('admin.appointments.images.index', compact('appointment'));
    }

    public function imagestore_before(Request $request, $id)
    {
        $result = $this->appointmentImageService->storeImages($request->file, $request->type, $id);

        if (! $result['success']) {
            return response()->json([
                'status' => true,
                'message' => flash($result['error'])->warning()->important(),
                'id' => $result['id'],
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => flash($result['message'])->success()->important(),
            'id' => $result['id'],
        ]);
    }

    public function datatable(Request $request, $id)
    {
        $records = $this->appointmentImageService->getDatatableData($request, Auth::User()->account_id, $id);

        return response()->json($records);
    }

    public function destroy($id)
    {
        if (! Gate::allows('appointments_image_destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $response = $this->appointmentImageService->deleteImage($id);

            if ($response['status']) {
                return $this->successResponse($response['message'], null, 200);
            }

            return $this->errorResponse($response['message'], 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'AppointmentimageController');
        }
    }
}
