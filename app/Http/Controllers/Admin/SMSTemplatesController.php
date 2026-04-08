<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SMS\SMSTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class SMSTemplatesController extends Controller
{
    public function __construct(
        private readonly SMSTemplateService $smsTemplateService,
    ) {}

    /**
     * Display a listing of Sms Templates.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\never
     */
    public function index(): \Illuminate\View\View
    {
        if (! Gate::allows('sms_templates_manage')) {
            return abort(401);
        }

        return view('admin.sms_templates.index');
    }

    /**
     * Display a listing of Sms Templates.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('sms_templates_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $records = $this->smsTemplateService->getDatatableData($request, Auth::user()->account_id, Auth::user()->id);

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'SMSTemplatesController');
        }
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(): \Illuminate\View\View
    {
        if (! Gate::allows('sms_templates_manage')) {
            return abort(401);
        }

        return view('admin.sms_templates.create');
    }

    /**
     * Store a newly created Sms Templates in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('sms_templates_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->smsTemplateService->validateAndCreate($request->all(), Auth::user()->account_id);

            if ($result['success']) {
                return $this->successResponse($result['message']);
            }

            return $this->successResponse($result['error'], false, $result['errors'] ?? null);
        } catch (\Exception $e) {
            return $this->handleException($e, 'SMSTemplatesController');
        }
    }

    /**
     * Show data for editing Sms Templates.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('sms_templates_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->smsTemplateService->getEditData($id);

            if (! $result['success']) {
                return $this->errorResponse($result['error'], 404);
            }

            return $this->successResponse('Success', $result['sms_template']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'SMSTemplatesController');
        }
    }

    /**
     * Update Sms Templates in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('sms_templates_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->smsTemplateService->validateAndUpdate($request->all(), $id, Auth::user()->account_id);

            if ($result['success']) {
                return $this->successResponse($result['message']);
            }

            return $this->successResponse($result['error'], false, $result['errors'] ?? null);
        } catch (\Exception $e) {
            return $this->handleException($e, 'SMSTemplatesController');
        }
    }

    /**
     * Remove Sms Template from storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('sms_templates_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->smsTemplateService->deleteTemplate($id);

            if ($result['success']) {
                return $this->successResponse($result['message']);
            }

            return $this->errorResponse($result['error'], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'SMSTemplatesController');
        }
    }

    /**
     * Change status of SMS Template
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if ($request->status == 0) {
                if (! Gate::allows('sms_templates_inactive')) {
                    return $this->errorResponse('You are not authorized to access this resource.', 401);
                }
            } else {
                if (! Gate::allows('sms_templates_active')) {
                    return $this->errorResponse('You are not authorized to access this resource.', 401);
                }
            }

            $result = $this->smsTemplateService->changeStatus((int) $request->id, (int) $request->status);

            if ($result['success']) {
                return $this->successResponse($result['message']);
            }

            return $this->errorResponse($result['error'], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'SMSTemplatesController');
        }
    }
}
