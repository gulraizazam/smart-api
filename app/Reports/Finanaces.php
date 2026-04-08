<?php

declare(strict_types=1);
namespace App\Reports;

use Auth;
use Config;
use App\User;
use Carbon\Carbon;
use App\Helpers\ACL;
use App\Models\Packages;
use App\Models\Locations;
use App\Models\Resources;
use App\Models\MachineType;
use App\Models\Appointments;
use App\Models\InvoiceDetails;
use App\Models\PackageBundles;
use App\Models\PackageService;
use App\Models\InvoiceStatuses;
use App\Models\PackageAdvances;
use App\Models\ResourceHasRota;
use App\Models\AppointmentTypes;
use App\Helpers\GeneralFunctions;
use App\Models\DoctorHasLocations;
use App\Services\Conversion\ConversionService;
use Illuminate\Support\Facades\DB;
use App\Helpers\Widgets\AppointmentEditWidget;

class Finanaces
{
    // -------------------------------------------------------------------------
    // Performance Reports — delegated to Finance\FinancePerformanceReport
    // -------------------------------------------------------------------------

    public static function centerperformancestatsbyrevenue(array $data, array $filters = []): array
    {
        return \App\Reports\Finance\FinancePerformanceReport::centerperformancestatsbyrevenue($data, $filters);
    }

    public static function centerperformancestatsbyservices(array $data, array $filters = []): array
    {
        return \App\Reports\Finance\FinancePerformanceReport::centerperformancestatsbyservices($data, $filters);
    }

    // -------------------------------------------------------------------------
    // Ledger Reports — delegated to Finance\FinanceLedgerReport
    // -------------------------------------------------------------------------

    public static function Customerpaymentledgerallentries(array $data, int $account_id): array
    {
        return \App\Reports\Finance\FinanceLedgerReport::Customerpaymentledgerallentries($data, $account_id);
    }

    public static function customertreatmentpackageledger(array $data, int $account_id): array
    {
        return \App\Reports\Finance\FinanceLedgerReport::customertreatmentpackageledger($data, $account_id);
    }

    public static function lsitofadvanacesoftodayplan(array $data, int $account_id): array
    {
        return \App\Reports\Finance\FinanceLedgerReport::lsitofadvanacesoftodayplan($data, $account_id);
    }

    public static function lsitofadvanacesoftodaynonplan(array $data, array $filters, int $account_id): array
    {
        return \App\Reports\Finance\FinanceLedgerReport::lsitofadvanacesoftodaynonplan($data, $filters, $account_id);
    }

    public static function ListofClientswhoclaimedrefunds(array $data, int $account_id): array
    {
        return \App\Reports\Finance\FinanceLedgerReport::ListofClientswhoclaimedrefunds($data, $account_id);
    }

    public static function ListofClientswhoclaimedrefundsnonplans(array $data, array $filters, int $account_id): array
    {
        return \App\Reports\Finance\FinanceLedgerReport::ListofClientswhoclaimedrefundsnonplans($data, $filters, $account_id);
    }

    public static function ListofClientswhoclaimedrefundsdaywise(array $data, int $account_id): array
    {
        return \App\Reports\Finance\FinanceLedgerReport::ListofClientswhoclaimedrefundsdaywise($data, $account_id);
    }

    public static function ListofClientswhoclaimedrefundsdaysbasenonplans(array $data, array $filters, int $account_id): array
    {
        return \App\Reports\Finance\FinanceLedgerReport::ListofClientswhoclaimedrefundsdaysbasenonplans($data, $filters, $account_id);
    }

    public static function SummarizeddataofDiscountsgiventothecustomer(array $data, int $account_id): array
    {
        return \App\Reports\Finance\FinanceLedgerReport::SummarizeddataofDiscountsgiventothecustomer($data, $account_id);
    }

    // -------------------------------------------------------------------------
    // Revenue Reports — delegated to Finance\FinanceRevenueReport
    // -------------------------------------------------------------------------

    public static function generalrevenuereportdetail(array $data, int $account_id): array
    {
        return \App\Reports\Finance\FinanceRevenueReport::generalrevenuereportdetail($data, $account_id);
    }

    public static function generalrevenuereportsummary(array $data, int $account_id): array
    {
        return \App\Reports\Finance\FinanceRevenueReport::generalrevenuereportsummary($data, $account_id);
    }

    public static function machinewiseinvoicerevenuereport(array $data, int $account_id): array
    {
        return \App\Reports\Finance\FinanceRevenueReport::machinewiseinvoicerevenuereport($data, $account_id);
    }

    // -------------------------------------------------------------------------
    // Staff Reports — delegated to Finance\FinanceStaffReport
    // -------------------------------------------------------------------------

    public static function staffwiserevenue(array $data, int $account_id): array
    {
        return \App\Reports\Finance\FinanceStaffReport::staffwiserevenue($data, $account_id);
    }

    public static function genericfunctionforstaffwiserevenue(mixed $packagesadvance): ?array
    {
        return \App\Reports\Finance\FinanceStaffReport::genericfunctionforstaffwiserevenue($packagesadvance);
    }

    public static function partnercollectionreport(array $data, int $account_id): array
    {
        return \App\Reports\Finance\FinanceStaffReport::partnercollectionreport($data, $account_id);
    }

    public static function machinewisecollectionreport(array $data, int $account_id): array
    {
        return \App\Reports\Finance\FinanceStaffReport::machinewisecollectionreport($data, $account_id);
    }

    // -------------------------------------------------------------------------
    // Conversion Reports — delegated to Finance\FinanceConversionReport
    // -------------------------------------------------------------------------

    public static function conversion_report(array $data, int $account_id): array
    {
        return \App\Reports\Finance\FinanceConversionReport::conversion_report($data, $account_id);
    }

    public static function LoadConversionReport(array $data, int $account_id): array
    {
        return \App\Reports\Finance\FinanceConversionReport::LoadConversionReport($data, $account_id);
    }

    public static function consumeplanrevenue(array $data, int $account): array
    {
        return \App\Reports\Finance\FinanceConversionReport::consumeplanrevenue($data, $account);
    }

    public static function planmaturityreport(array $data, int $account_id): array
    {
        return \App\Reports\Finance\FinanceConversionReport::planmaturityreport($data, $account_id);
    }
}
