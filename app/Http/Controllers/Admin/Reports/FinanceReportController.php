<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;

/**
 * FinanceReportController — legacy stub.
 *
 * All domain methods have been moved to focused controllers under
 * App\Http\Controllers\Admin\Reports\Finance\:
 *
 *  - FinanceRevenueReportController  (revenue, discount, conversion reports)
 *  - FinanceCollectionReportController (collection-by-service, machine-wise reports)
 *  - FinanceStaffReportController    (partner collection, staff-wise revenue)
 *  - FinancePlanReportController     (consume-plan revenue, services sold)
 *  - FinanceArrivalReportController  (daily arrival, staff/doctor wise, tax, incentive)
 *
 * The following two route references still point here and are dead (methods
 * never existed); they should be removed from admin-reports.php when cleaned up:
 *   - reports.revenue_reports  → revenue_reports()
 *   - reports.account_revenue_report_load → revenueReportLoad()
 */
class FinanceReportController extends Controller
{
    // Intentionally empty — all methods relocated to Finance/ sub-controllers.
}
