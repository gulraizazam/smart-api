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
    public function index(): mixed
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
    public function datatable(Request $request): mixed
    {
        try {
            if (! Gate::allows('sms_templates_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $records = $this->smsTemplateService->getDatatableData($request, Auth::User()->account_id, Auth::User()->id);

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
    public function create(): mixed
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
    public function store(Request $request): mixed
    {
        try {
            if (! Gate::allows('sms_templates_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->smsTemplateService->validateAndCreate($request->all(), Auth::User()->account_id);

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
    public function edit($id): mixed
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
    public function update(Request $request, $id): mixed
    {
        try {
            if (! Gate::allows('sms_templates_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            $result = $this->smsTemplateService->validateAndUpdate($request->all(), $id, Auth::User()->account_id);

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
    public function destroy($id): mixed
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
    public function status(Request $request): mixed
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

            $result = $this->smsTemplateService->changeStatus($request->id, $request->status);

            if ($result['success']) {
                return $this->successResponse($result['message']);
            }

            return $this->errorResponse($result['error'], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'SMSTemplatesController');
        }
    }
}
