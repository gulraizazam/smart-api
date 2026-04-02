<?php

declare(strict_types=1);

namespace App\Services\Reports\Enums;

enum ReportType: string
{
    case CollectionByService = 'collection_by_service';
    case DailyEmployeeStats = 'daily_employee_stats';
    case GeneralRevenueDetail = 'general_revenue_report_detail';
    case GeneralRevenueSummary = 'general_revenue_report_summary';
    case ConversionReport = 'conversion_report';
    case ServicesSold = 'services_sold';
    case GenderWiseRevenue = 'gender_wise_revenue';

    public function gate(): ?string
    {
        return match ($this) {
            self::CollectionByService => 'finance_general_revenue_reports_collection_by_service',
            self::DailyEmployeeStats => 'finance_general_revenue_reports_daily_employee_stats',
            self::GeneralRevenueDetail => 'finance_general_revenue_reports_general_revenue__detail_report',
            self::GeneralRevenueSummary => 'finance_general_revenue_reports_general_revenue__summary_report',
            self::ConversionReport => 'finance_general_revenue_reports_conversion_report',
            self::ServicesSold => null,
            self::GenderWiseRevenue => null,
        };
    }

    public function viewPath(): string
    {
        return match ($this) {
            self::CollectionByService => 'admin.reports.collectionbyservice.report',
            self::DailyEmployeeStats => 'admin.reports.dailyemployeestats.report',
            self::GeneralRevenueDetail => 'admin.reports.generalrevenuereport.report',
            self::GeneralRevenueSummary => 'admin.reports.generalrevenuesummaryreport.report',
            self::ConversionReport => 'admin.reports.conversionreport.report',
            self::ServicesSold => 'admin.reports.accountsalesreport.serviceSoldreport',
            self::GenderWiseRevenue => 'admin.reports.accountsalesreport.genderwiserevenue',
        };
    }

    public function printViewPath(): ?string
    {
        return match ($this) {
            self::CollectionByService => 'admin.reports.collectionbyservice.reportprint',
            self::DailyEmployeeStats => 'admin.reports.dailyemployeestats.reportprint',
            self::GeneralRevenueDetail => 'admin.reports.generalrevenuereport.reportprint',
            self::GeneralRevenueSummary => 'admin.reports.generalrevenuesummaryreport.reportprint',
            self::ConversionReport => 'admin.reports.conversionreport.reportprint',
            default => null,
        };
    }

    public function pdfViewPath(): ?string
    {
        return match ($this) {
            self::CollectionByService => 'admin.reports.collectionbyservice.reportpdf',
            self::DailyEmployeeStats => 'admin.reports.dailyemployeestats.reportpdf',
            self::GeneralRevenueDetail => 'admin.reports.generalrevenuereport.reportpdf',
            self::GeneralRevenueSummary => 'admin.reports.generalrevenuesummaryreport.reportpdf',
            self::ConversionReport => 'admin.reports.conversionreport.reportpdf',
            default => null,
        };
    }

    public function pdfPaper(): string
    {
        return match ($this) {
            self::GeneralRevenueDetail, self::ConversionReport => 'A3',
            default => 'A4',
        };
    }

    public function pdfOrientation(): string
    {
        return 'landscape';
    }

    public function pdfFilename(): string
    {
        return match ($this) {
            self::CollectionByService => 'Collection By Service',
            self::DailyEmployeeStats => 'Daily Employee Stats',
            self::GeneralRevenueDetail => 'General Revenue Report',
            self::GeneralRevenueSummary => 'General Revenue Report',
            self::ConversionReport => 'Conversion Report',
            default => 'Report',
        };
    }

    public function excelFilename(): string
    {
        return match ($this) {
            self::CollectionByService => 'Collectionbyservice.xlsx',
            self::DailyEmployeeStats => 'SaleSummaryDoctorsWise.xlsx',
            self::GeneralRevenueDetail => 'GeneralRevenueReport.xlsx',
            self::GeneralRevenueSummary => 'GeneralRevenueReport.xlsx',
            self::ConversionReport => 'conversionreport.xlsx',
            default => 'Report.xlsx',
        };
    }

    public function supportsExcel(): bool
    {
        return match ($this) {
            self::ServicesSold, self::GenderWiseRevenue => false,
            default => true,
        };
    }

    public function supportsPrint(): bool
    {
        return $this->printViewPath() !== null;
    }

    public function supportsPdf(): bool
    {
        return $this->pdfViewPath() !== null;
    }
}
