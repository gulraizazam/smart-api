<?php

declare(strict_types=1);
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CashFlow\CashflowAuditService;
use App\Services\CashFlow\CashflowSettingService;
use App\Services\CashFlow\CategoryService;
use App\Services\CashFlow\DashboardService;
use App\Services\CashFlow\ExportService;
use App\Services\CashFlow\ExpenseService;
use App\Services\CashFlow\NotificationService;
use App\Services\CashFlow\PeriodLockService;
use App\Services\CashFlow\PoolService;
use App\Services\CashFlow\ReportService;
use App\Services\CashFlow\StaffAdvanceService;
use App\Services\CashFlow\TransferService;
use App\Services\CashFlow\VendorService;

/**
 * @deprecated All methods have been moved to domain-specific controllers
 *             in App\Http\Controllers\Api\CashFlow\. This class is retained
 *             for backwards-compatibility references only.
 */
class CashFlowController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly PoolService $poolService,
        private readonly CategoryService $categoryService,
        private readonly CashflowSettingService $settingService,
        private readonly NotificationService $notificationService,
        private readonly CashflowAuditService $auditService,
        private readonly TransferService $transferService,
        private readonly VendorService $vendorService,
        private readonly StaffAdvanceService $staffAdvanceService,
        private readonly DashboardService $dashboardService,
        private readonly ReportService $reportService,
        private readonly ExportService $exportService,
        private readonly PeriodLockService $periodLockService,
    ) {}
}
