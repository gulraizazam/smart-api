<?php

declare(strict_types=1);

namespace App\Services\Reports\Enums;

enum OperationsReportType: string
{
    case DarReport = 'dar_report';
    case WalkingReport = 'walking_report';
    case AgentReport = 'agent_report';

    public function gate(): string
    {
        return 'operations_reports_dar_report';
    }

    public function viewPath(): string
    {
        return match ($this) {
            self::DarReport => 'admin.reports.operations.darreport.report',
            self::WalkingReport => 'admin.reports.operations.walkinreport.report',
            self::AgentReport => 'admin.reports.operations.agentreport.report',
        };
    }

    public function printViewPath(): string
    {
        return match ($this) {
            self::DarReport => 'admin.reports.operations.darreport.reportprint',
            self::WalkingReport => 'admin.reports.operations.walkinreport.reportprint',
            self::AgentReport => 'admin.reports.operations.agentreport.reportprint',
        };
    }

    public function pdfViewPath(): string
    {
        return match ($this) {
            self::DarReport => 'admin.reports.operations.darreport.reportpdf',
            self::WalkingReport => 'admin.reports.operations.walkinreport.reportpdf',
            self::AgentReport => 'admin.reports.operations.agentreport.reportpdf',
        };
    }

    public function pdfPaper(): string
    {
        return 'A2';
    }

    public function pdfOrientation(): string
    {
        return 'landscape';
    }

    public function pdfFilename(): string
    {
        return match ($this) {
            self::DarReport => 'DAR Report',
            self::WalkingReport => 'Walkin Report',
            self::AgentReport => 'Agent Report',
        };
    }

    public function excelFilename(): string
    {
        return match ($this) {
            self::DarReport => 'DAR Report.xlsx',
            self::WalkingReport => 'Walkin Report.xlsx',
            self::AgentReport => 'Agent Report.xlsx',
        };
    }

    public function excelType(): string
    {
        return match ($this) {
            self::DarReport => 'dar',
            self::WalkingReport => 'walkin',
            self::AgentReport => 'agent',
        };
    }
}
