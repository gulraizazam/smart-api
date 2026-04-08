<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUpdateCustomFormsRequest;
use App\Services\CustomForm\CustomFormService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomFormsController extends Controller
{
    public function __construct(
        private readonly CustomFormService $service,
    ) {}

    /**
     * Display a listing of Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): \Illuminate\View\View
    {
        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }

        return view('admin.custom_forms.index');
    }

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     *
     * @throws \Throwable
     */
    public function datatable(Request $request): \Illuminate\Http\JsonResponse
    {
        try {

            $records = $this->service->getDatatableData($request);

            return response()->json($records);

        } catch (\Exception $e) {

            return $this->handleException($e, 'CustomFormsController');
        }
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(): \Illuminate\Http\RedirectResponse
    {
        if (! Gate::allows('custom_forms_create_general') && ! Gate::allows('custom_forms_edit')) {
            return abort(401);
        }

        $form = $this->service->createGeneralForm();

        return redirect()->route('admin.custom_forms.edit', $form);
    }

    /**
     * Show the form for creating measurement new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create_measurement(): \Illuminate\Http\RedirectResponse
    {
        if (! Gate::allows('custom_forms_create_measurement') && ! Gate::allows('custom_forms_edit')) {
            return abort(401);
        }

        $form = $this->service->createMeasurementForm();

        return redirect()->route('admin.custom_forms.edit', $form);
    }

    /**
     * Show the form for creating medical history from with new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create_medical(): \Illuminate\Http\RedirectResponse
    {
        if (! Gate::allows('custom_forms_create_medical_history_form') && ! Gate::allows('custom_forms_edit')) {
            return abort(401);
        }

        $form = $this->service->createMedicalForm();

        return redirect()->route('admin.custom_forms.edit', $form);
    }

    public function sortorder_save(): \Illuminate\Http\JsonResponse
    {
        $result = $this->service->saveSortOrder();

        return response()->json($result);
    }

    public function sort_fields(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }

        if (! $this->service->sortFields($request, $id)) {

            return response()->json(['Unprocessable Entities' => '.', 'code' => 422], 422);
        }

        return response()->json(['message' => 'Records has been sorted successfully.', 'code' => 200], 200);
    }

    public function sortorder(): \Illuminate\View\View
    {
        $custom_forms = $this->service->getSortOrderData();

        return view('admin.custom_forms.sort', compact('custom_forms'));
    }

    /**
     * Store a newly created Permission in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(StoreUpdateCustomFormsRequest $request): \Illuminate\Http\RedirectResponse
    {
        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }

        if ($this->service->storeRecord($request)) {

            flash('Record has been created successfully.')->success()->important();
        } else {
            flash('Something went wrong, please try again later.')->error()->important();
        }

        return redirect()->route('admin.custom_forms.index');
    }

    /**
     * Show the form for editing Permission.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(int $id): \Illuminate\View\View
    {
        if (! Gate::allows('custom_forms_edit') && ! Gate::allows('custom_forms_create_measurement') && ! Gate::allows('custom_forms_create_general')) {
            return abort(401);
        }

        $data = $this->service->getEditData($id);

        if (isset($data['error'])) {
            return view('error');
        }

        return view('admin.custom_forms.edit', $data);
    }

    /**
     * Update Permission in storage.
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateCustomFormsRequest  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, int $id): \Illuminate\Http\RedirectResponse
    {
        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }

        if ($this->service->updateRecord($id, $request)) {

            flash('Record has been updated successfully.')->success()->important();
        } else {
            flash('Something went wrong, please try again later.')->error()->important();
        }

        return redirect()->route('admin.custom_forms.index');
    }

    public function form_update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }

        $result = $this->service->formUpdate($id, $request);

        if ($result['success']) {

            return response()->json($result['data'], 200);

        } else {
            return response()->json(['message' => 'Some went wrong, please try again later'], 400);
        }
    }

    public function create_field(Request $request, int $id): \Illuminate\Http\JsonResponse
    {

        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }

        $result = $this->service->createField($request, $id);

        if ($result['success']) {
            return response()->json(['request' => $result['request'], 'data' => $result['data']]);
        } else {
            return response()->json(['error' => $result['error'], 'id' => $result['id'], 'data' => $result['data']], 401);
        }
    }

    /**
     * update form field
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update_field(Request $request, int $form_id, int $field_id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }

        $result = $this->service->updateField($request, $form_id, $field_id);

        if ($result['success']) {
            return response()->json(['request' => $result['request'], 'data' => $result['data']]);
        } else {
            return response()->json(['error' => $result['error'], 'form_id' => $result['form_id'], 'field_id' => $result['field_id'], 'data' => $result['data']], 401);
        }
    }

    public function delete_field(Request $request, int $form_id, int $field_id): \Illuminate\Http\JsonResponse
    {

        if (! Gate::allows('custom_forms_manage')) {
            return abort(401);
        }

        $result = $this->service->deleteField($form_id, $field_id);

        return response()->json(['message' => $result['message'], 'code' => $result['code']], $result['code']);

    }

    /**
     * Remove Permission from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('custom_forms_destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $result = $this->service->destroyRecord($id);

        if ($result['success']) {
            return $this->successResponse($result['message'], null, 200);
        }

        return $this->errorResponse($result['message'], 200);
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function status(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('custom_forms_inactive')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $result = $this->service->updateStatus((int) $request->id, (int) $request->status);

        if ($result['success']) {
            return $this->successResponse($result['message'], null, 200);
        }

        return $this->errorResponse($result['message'], 200);

    }
}
