<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Exports\GenericTableExport;
use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Models\DoctorHasLocations;
use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use App\Reports\Finanaces;
use App\Services\Reports\Concerns\ParsesDateRange;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Conversion report — SPA bridge over the legacy
 * `Finanaces::LoadConversionReport` flow. Reshapes the legacy 11-tuple
 * return value into a flat JSON envelope that's friendly for the SPA's
 * tables and KPI tiles.
 *
 *   GET  /api/reports/conversion/filters → centres + services + doctors
 *   GET  /api/reports/conversion/doctors → centre-narrowed doctor list
 *   POST /api/reports/conversion         → JSON data
 *   POST /api/reports/conversion/export  → PDF / XLSX
 */
class ConversionReportApiController extends Controller
{
    use ParsesDateRange;

    private const DOCTOR_USER_TYPE_ID = 5;

    public function __invoke(Request $request): JsonResponse
    {
        if (! Gate::allows('conversion_report_manage')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $validated = $request->validate([
                'location_id' => 'nullable|array',
                'location_id.*' => 'integer|exists:locations,id',
                'doctor_id' => 'nullable|integer|exists:users,id',
                'service_id' => 'nullable|integer|exists:services,id',
                'date_range' => 'nullable|string',
            ]);

            $payload = $this->buildPayload($validated);

            $started = microtime(true);
            $result = Finanaces::LoadConversionReport($payload, Auth::user()->account_id);
            $elapsed = round((microtime(true) - $started) * 1000, 1);

            [
                $reportData,
                $locationData,
                $maxConversion,
                $minConversion,
                $categoryData,
                $arrivalToConversionRatio,
                $averageClientConversion,
                $conversionsByPatient,
                $totalConversion,
                $totalArrival,
                $avgClientValue,
            ] = $result;

            [$startDate, $endDate] = self::parseDateRange($payload['date_range'] ?? null);

            return $this->successResponse('Conversion report generated successfully', [
                'rows' => $this->shapeRows($reportData, $conversionsByPatient),
                'location_data' => $this->shapeLocationData($locationData),
                'category_data' => array_values($categoryData ?? []),
                'stats' => [
                    'total_conversion' => (int) $totalConversion,
                    'total_arrival' => (int) $totalArrival,
                    'max_conversion' => $maxConversion !== null ? (float) $maxConversion : null,
                    'min_conversion' => $minConversion !== null ? (float) $minConversion : null,
                    'arrival_to_conversion_ratio' => (float) $arrivalToConversionRatio,
                    'average_client_conversion' => (float) $averageClientConversion,
                    'avg_client_value' => (float) $avgClientValue,
                ],
                'meta' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'centres' => count($payload['location_id']),
                    'elapsed_ms' => $elapsed,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'ConversionReport');
        }
    }

    public function filters(): JsonResponse
    {
        if (! Gate::allows('conversion_report_manage')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $accountId = Auth::user()->account_id;

            $locations = collect(Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId))
                ->map(fn ($l) => ['id' => (int) $l->id, 'name' => $l->name])
                ->values()
                ->all();

            $services = Services::where('parent_id', 0)
                ->where('slug', '!=', 'all')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($s) => ['id' => (int) $s->id, 'name' => $s->name])
                ->all();

            $doctors = User::getAllRecords($accountId)
                ->where('user_type_id', self::DOCTOR_USER_TYPE_ID)
                ->where('active', 1)
                ->values()
                ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                ->all();

            return $this->successResponse('OK', [
                'locations' => $locations,
                'services' => $services,
                'doctors' => $doctors,
            ]);
        } catch (\Throwable $e) {
            Log::error('ConversionReport filters failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    public function doctorsForCentres(Request $request): JsonResponse
    {
        if (! Gate::allows('conversion_report_manage')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $request->validate([
                'centre_ids' => 'nullable|array',
                'centre_ids.*' => 'integer|exists:locations,id',
            ]);

            $accountId = Auth::user()->account_id;
            $centreIds = (array) $request->input('centre_ids', []);
            $centreIds = array_values(array_filter(array_map('intval', $centreIds), fn ($id) => $id > 0));

            if (empty($centreIds)) {
                $doctors = User::getAllRecords($accountId)
                    ->where('user_type_id', self::DOCTOR_USER_TYPE_ID)
                    ->where('active', 1)
                    ->values()
                    ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                    ->all();

                return $this->successResponse('OK', ['doctors' => $doctors]);
            }

            $allocated = DoctorHasLocations::where('is_allocated', 1)
                ->whereIn('location_id', $centreIds)
                ->distinct()
                ->pluck('user_id')
                ->all();

            $doctors = empty($allocated)
                ? []
                : User::whereIn('id', $allocated)
                    ->where('account_id', $accountId)
                    ->where('user_type_id', self::DOCTOR_USER_TYPE_ID)
                    ->where('active', 1)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                    ->all();

            return $this->successResponse('OK', ['doctors' => $doctors]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('ConversionReport doctorsForCentres failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    public function export(Request $request): SymfonyResponse
    {
        if (! Gate::allows('conversion_report_manage')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,excel',
            'location_id' => 'nullable|array',
            'location_id.*' => 'integer|exists:locations,id',
            'doctor_id' => 'nullable|integer|exists:users,id',
            'service_id' => 'nullable|integer|exists:services,id',
            'date_range' => 'nullable|string',
        ]);

        $payload = $this->buildPayload($validated);

        $result = Finanaces::LoadConversionReport($payload, Auth::user()->account_id);
        [$reportData, , , , , , , $conversionsByPatient, , ,] = $result;

        [$startDate, $endDate] = self::parseDateRange($payload['date_range'] ?? null);
        $stamp = "-{$startDate}-to-{$endDate}";
        $title = 'Conversion Report';
        $subtitle = "Period: {$startDate} → {$endDate}";

        $headings = ['Patient ID', 'Patient', 'Doctor', 'Service', 'Conversion Spend', 'Conversion Date', 'Centre', 'Client Value'];
        $flatRows = [];
        foreach ($reportData as $appointment) {
            $converted = $appointment['converted'] ?? '';
            $spend = $appointment['conversion_spend'] ?? '';
            if ($converted === '' || $spend === '' || (float) $spend <= 0) {
                continue;
            }
            $patientId = $appointment['patient_id'] ?? null;
            $clientValue = $patientId !== null && isset($conversionsByPatient[$patientId])
                ? (float) $conversionsByPatient[$patientId]
                : 0.0;
            $flatRows[] = [
                $patientId,
                $appointment['client'] ?? '',
                $appointment['doctor'] ?? '',
                $appointment['service'] ?? '',
                number_format((float) $spend, 2, '.', ''),
                $appointment['conversion_date']
                    ? date('Y-m-d', strtotime((string) $appointment['conversion_date']))
                    : '',
                $appointment['centre'] ?? '',
                number_format($clientValue, 2, '.', ''),
            ];
        }

        if ($validated['format'] === 'excel') {
            return Excel::download(new GenericTableExport($headings, $flatRows), "conversion-report{$stamp}.xlsx");
        }

        $pdf = Pdf::loadView('admin.reports.exports.generic-table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headings' => $headings,
            'rows' => $flatRows,
        ])->setPaper('A4', 'landscape');

        return $pdf->download("conversion-report{$stamp}.pdf");
    }

    /**
     * Normalise the SPA payload into the shape the legacy
     * `LoadConversionReport` expects (location_id always present as array,
     * fallback to ACL centres when empty).
     */
    private function buildPayload(array $validated): array
    {
        $locationIds = $validated['location_id'] ?? [];
        $locationIds = array_values(array_filter(array_map('intval', (array) $locationIds), fn ($id) => $id > 0));
        if (empty($locationIds)) {
            $locationIds = array_map('intval', (array) ACL::getUserCentres());
        }

        return [
            'location_id' => $locationIds,
            'doctor_id' => $validated['doctor_id'] ?? null,
            'service_id' => $validated['service_id'] ?? null,
            'date_range' => $validated['date_range'] ?? null,
        ];
    }

    /**
     * Drop the rare appointments where converted is blank or spend is zero
     * (the legacy view filters them out, so the SPA gets the same set).
     */
    private function shapeRows(array $reportData, $conversionsByPatient): array
    {
        $rows = [];
        foreach ($reportData as $appointment) {
            $converted = $appointment['converted'] ?? '';
            $spend = $appointment['conversion_spend'] ?? '';
            if ($converted === '' || $spend === '' || (float) $spend <= 0) {
                continue;
            }
            $patientId = $appointment['patient_id'] ?? null;
            $clientValue = $patientId !== null && isset($conversionsByPatient[$patientId])
                ? (float) $conversionsByPatient[$patientId]
                : 0.0;
            $rows[] = [
                'patient_id' => $patientId,
                'appointment_id' => $appointment['appointment_id'] ?? null,
                'doctor' => $appointment['doctor'] ?? null,
                'client' => $appointment['client'] ?? null,
                'phone' => $appointment['phone'] ?? null,
                'service' => $appointment['service'] ?? null,
                'region' => $appointment['region'] ?? null,
                'city' => $appointment['city'] ?? null,
                'centre' => $appointment['centre'] ?? null,
                'doi' => $appointment['doi'] ?? null,
                'converted' => $converted,
                'conversion_spend' => (float) $spend,
                'conversion_date' => $appointment['conversion_date'] ?? null,
                'client_value' => $clientValue,
            ];
        }

        return $rows;
    }

    /**
     * Re-key the legacy `[centreName => [total_count, total]]` map into a
     * sortable list the SPA can drop straight into a table.
     */
    private function shapeLocationData(array $locationData): array
    {
        $rows = [];
        foreach ($locationData as $name => $entry) {
            $rows[] = [
                'centre' => (string) $name,
                'total_count' => (int) ($entry['total_count'] ?? 0),
                'total' => (float) ($entry['total'] ?? 0),
            ];
        }

        return $rows;
    }
}
