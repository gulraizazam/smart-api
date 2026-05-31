<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Models\Locations;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderRefund;
use App\Models\OrderRefundDetail;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrdersController extends Controller
{


    public function __construct(
        private readonly OrderService $orderService,
    ) {


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

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'OrdersController');
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

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'OrdersController');
        }
    }

    public function getProducts(Request $request): JsonResponse
    {
        $data = $this->orderService->getProducts($request);

        return $this->successResponse('Record found.', $data);
    }

    public function getDiscounts(): JsonResponse
    {
        $data = $this->orderService->getDiscounts();

        return $this->successResponse('Record found.', $data);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('order_create')) {
                abort(401);
            }

            if ($request->payment_mode === null) {
                return $this->errorResponse('Payment method is required.', 500);
            }

            $stockError = $this->orderService->validateOrderStock($request->all());
            if ($stockError) {
                return $this->errorResponse($stockError, 500);
            }

            if ($request->sold_to === 'patient' && $request->doctor_id === null) {
                return $this->errorResponse('prescribed by field is required', 500);
            }

            $order = $this->orderService->createOrder($request->all());

            if ($order) {
                return $this->successResponse('Record has been created successfully.', $order->id);
            }

            return $this->errorResponse('Something went wrong, please try again later.', 404);
        } catch (InsufficientStockException $e) {
            // The FIFO consumer hit the floor mid-transaction — another
            // order claimed the units we previewed as available. Surface
            // a precise 422 with the new on-hand so the operator can
            // retry against the updated total.
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->handleException($e, 'OrdersController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $result = $this->orderService->deleteOrder($id);

            return $result['status'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (\Exception $e) {
            return $this->handleException($e, 'OrdersController');
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
                return $this->errorResponse('This Order is already refunded!', 404);
            }

            return response()->json(['data' => $order]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'OrdersController');
        }
    }

    public function orderRefund(int $id, Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('order_refund_manage')) {
                abort(401);
            }

            $result = $this->orderService->processRefund($id, $request->all());

            return $this->successResponse($result['message'], $result['success']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'OrdersController');
        }
    }

    public function displayInvoiceAppointment(int $id): View|JsonResponse
    {
        if (! Gate::allows('order_manage')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
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

    /**
     * JSON invoice payload for the SPA print page.
     *
     * Shape matches what consultation + treatment invoice endpoints
     * return so a single shared invoice template can render all three.
     * The legacy `displayInvoiceAppointment` Blade endpoint still
     * exists for direct-link / PDF rendering.
     */
    public function invoiceJson(int $id): JsonResponse
    {
        if (! Gate::allows('order_manage')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        try {
            $payload = $this->orderService->getInvoiceJson($id);

            return $this->successResponse('Invoice retrieved.', $payload);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'OrdersController');
        }
    }

    /**
     * Display the order invoice as PDF.
     *
     * Always returns a PDF binary. `$download` truthy → save-as dialog
     * via `dompdf->download()`. `$download` null/falsy → inline PDF via
     * `dompdf->stream()` so the browser opens its built-in viewer.
     *
     * Was a hybrid HTML/PDF endpoint where the no-download branch
     * returned a Blade view — that branch was unreachable from the SPA
     * (orders-api.ts always passes download=true) but stayed live as a
     * cutover landmine: any caller dropping the flag (direct URL hit,
     * future SPA refactor, external integration) would get a Blade
     * page that 500s once the legacy admin views are deleted. Mirrors
     * the Phase 1A.1 fix to InvoicesController::invoice_pdf.
     */
    public function invoicePdf(int $id, ?string $download = null): \Symfony\Component\HttpFoundation\Response
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

        $content = view('admin.orders.invoice_pdf', $viewData)->render();
        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($content);

        // patient_id is nullable since the 2026-05 inventory revamp
        // (employee sales have it null). Fall back to employee_id or
        // the order id so the download always has a sensible filename.
        $info = $data['invoice_info'];
        $buyerId = $info->patient_id ?: ($info->employee_id ?: $info->id);
        $filename = 'order-invoice-C-' . $buyerId . '.pdf';

        return $download ? $pdf->download($filename) : $pdf->stream($filename);
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
        $users = $this->orderService->getDoctorsForSales($request->input('location_id', []));

        return response()->json(['users' => $users]);
    }

    public function getCentreDoctors(Request $request): JsonResponse
    {
        $users = $this->orderService->getCentreDoctors((int) $request->location_id);

        return response()->json(['users' => $users]);
    }
}
