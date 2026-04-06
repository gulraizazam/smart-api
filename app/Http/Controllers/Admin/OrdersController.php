<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Helpers\ACL;
use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Models\Locations;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderRefund;
use App\Models\OrderRefundDetail;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrdersController extends Controller
{
    private readonly int $success;

    private readonly int $error;

    private readonly int $unauthorized;

    public function __construct(
        private readonly OrderService $orderService,
    ) {
        $this->success = (int) config('constants.api_status.success');
        $this->error = (int) config('constants.api_status.error');
        $this->unauthorized = (int) config('constants.api_status.unauthorized');
    }

    public function index(): View
    {
        if (! Gate::allows('order_manage')) {
            abort(401);
        }

        return view('admin.orders.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        try {
            $records = [];
            $records['data'] = [];
            $filename = 'transfer_product';
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);

            if (isset($apply_filter['delete'])) {
                $ids = explode(',', $apply_filter['delete']);
                $bulkResult = $this->orderService->bulkDeleteOrders($ids);
                $records['status'] = $bulkResult['status'];
                $records['message'] = $bulkResult['message'];
            }

            $params = [
                'apply_filter' => $apply_filter,
                'filters' => $filters,
                'request_data' => $request->all(),
            ];

            $iTotalRecords = $this->orderService->getDatatableCount($params);
            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $orders = $this->orderService->getDatatableRecords([
                ...$params,
                'offset' => $iDisplayStart,
                'limit' => $iDisplayLength,
            ]);

            $centres = Locations::getAllRecordsDictionary(Auth::user()->account_id, 'custom', 'id', 'desc', ACL::getUserCentres());

            $records['data'] = $this->orderService->transformForDatatable($orders, $centres);
            $records['permissions'] = $this->orderService->getOrderPermissions();
            $records['active_filters'] = $filters;
            $records['filter_values'] = $this->orderService->getOrderFilterValues();
            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function refund(): View
    {
        if (! Gate::allows('inventory_refund_manage')) {
            abort(401);
        }

        return view('admin.order_refunds.index');
    }

    public function refunddatatable(Request $request): JsonResponse
    {
        try {
            $records = [];
            $records['data'] = [];
            $filename = 'transfer_product';
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);

            if (isset($apply_filter['delete'])) {
                $ids = explode(',', $apply_filter['delete']);
                $bulkResult = $this->orderService->bulkDeleteRefunds($ids);
                $records['status'] = $bulkResult['status'];
                $records['message'] = $bulkResult['message'];
            }

            $params = [
                'apply_filter' => $apply_filter,
                'request_data' => $request->all(),
            ];

            $iTotalRecords = $this->orderService->getRefundDatatableCount($params);
            [$orderBy, $order] = getSortBy($request);
            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $orders = $this->orderService->getRefundDatatableRecords([
                ...$params,
                'offset' => $iDisplayStart,
                'limit' => $iDisplayLength,
            ]);

            $accountId = Auth::user()->account_id;
            $centres = Locations::getAllRecordsDictionary($accountId, 'custom', 'id', 'desc', ACL::getUserCentres());
            $warehouse = Warehouse::getAllRecordsDictionary($accountId, ACL::getUserWarehouse());

            $records['data'] = $this->orderService->transformRefundsForDatatable($orders, $centres, $warehouse);
            $records['active_filters'] = $filters;
            $records['permissions'] = $this->orderService->getRefundPermissions();
            $records['filter_values'] = $this->orderService->getRefundFilterValues();
            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getProducts(Request $request): JsonResponse
    {
        $data = $this->orderService->getProducts($request);

        return ApiHelper::apiResponse($this->success, 'Record found.', true, $data);
    }

    public function getDiscounts(): JsonResponse
    {
        $data = $this->orderService->getDiscounts();

        return ApiHelper::apiResponse($this->success, 'Record found.', true, $data);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('order_create')) {
                abort(401);
            }

            if ($request->payment_mode === null) {
                return ApiHelper::apiResponse($this->error, 'Payment method is required.', false);
            }

            $stockError = $this->orderService->validateOrderStock($request->all());
            if ($stockError) {
                return ApiHelper::apiResponse($this->error, $stockError, false);
            }

            if ($request->sold_to === 'patient' && $request->doctor_id === null) {
                return ApiHelper::apiResponse($this->error, 'prescribed by field is required', false);
            }

            $order = $this->orderService->createOrder($request->all());

            if ($order) {
                return ApiHelper::apiResponse($this->success, 'Record has been created successfully.', true, $order->id);
            }

            return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function cancel(int $id): JsonResponse
    {
        try {
            $result = $this->orderService->cancelOrder($id);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->orderService->deleteOrder($id);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function orderRefundDetail(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('order_refund_manage')) {
                abort(401);
            }

            $order = $this->orderService->getRefundDetail($id);

            if (! $order) {
                return ApiHelper::apiResponse($this->success, 'This Order is already refunded!', false);
            }

            return ApiHelper::apiDataTable(['data' => $order]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function orderRefund(int $id, Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('order_refund_manage')) {
                abort(401);
            }

            $result = $this->orderService->processRefund($id, $request->all());

            return ApiHelper::apiResponse(
                $result['success'] ? $this->success : $this->error,
                $result['message'],
                $result['success'],
            );
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function displayInvoiceAppointment(int $id): View|JsonResponse
    {
        if (! Gate::allows('order_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $data = $this->orderService->getInvoiceData($id);

        return view('admin.orders.displayInvoice', [
            'invoice_info' => $data['invoice_info'],
            'patient' => $data['patient'],
            'products' => $data['products'],
            'company_phone_number' => $data['company_phone_number'],
            'location_info' => $data['location_info'],
            'account' => $data['account'],
        ]);
    }

    public function checkMembership(Request $request): JsonResponse
    {
        $result = $this->orderService->checkMembership($request->input('patient_id') ? (int) $request->input('patient_id') : null);

        return response()->json($result);
    }

    public function invoicePdf(int $id, ?string $download = null): View|\Illuminate\Http\Response
    {
        if (! Gate::allows('order_manage')) {
            abort(401);
        }

        $data = $this->orderService->getInvoiceData($id);

        $viewData = [
            'invoice_info' => $data['invoice_info'],
            'patient' => $data['patient'],
            'account' => $data['account'],
            'products' => $data['products'],
            'company_phone_number' => $data['company_phone_number'],
            'location_info' => $data['location_info'],
            'download' => $download,
        ];

        if ($download) {
            $content = view('admin.orders.invoice_pdf', $viewData)->render();
            $pdf = App::make('dompdf.wrapper');
            $pdf->loadHTML($content);

            return $pdf->download('order-invoice-C-' . $data['invoice_info']->patient_id . '.pdf');
        }

        return view('admin.orders.invoice_pdf', $viewData);
    }

    public function getEmployees(Request $request): JsonResponse
    {
        $users = $this->orderService->getEmployees((int) $request->location_id);

        return response()->json(['users' => $users]);
    }

    public function getDoctors(Request $request): JsonResponse
    {
        $users = $this->orderService->getDoctorsWithFDM((int) $request->location_id);

        return response()->json(['users' => $users]);
    }

    public function getDoctorsForSales(Request $request): JsonResponse
    {
        $users = $this->orderService->getDoctorsForSales((int) $request->location_id);

        return response()->json(['users' => $users]);
    }

    public function getCentreDoctors(Request $request): JsonResponse
    {
        $users = $this->orderService->getCentreDoctors((int) $request->location_id);

        return response()->json(['users' => $users]);
    }
}
