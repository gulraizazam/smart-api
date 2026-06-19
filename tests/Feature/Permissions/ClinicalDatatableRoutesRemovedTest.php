<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 2026-06-19 QA #21 — clinical-records datatable routes are REMOVED.
 *
 * These six legacy Blade-datatable list endpoints (patient medical /
 * measurement / custom-form-feedback history) had NO permission gate on
 * the read, and the medical/measurement ones carried a bulk-delete branch
 * inside the datatable method whose id lookup (Measurement::getBulkData_*)
 * was not account-scoped — a hand-crafted call could read PHI or delete
 * another clinic's rows. The crm3 SPA never called any of them (verified
 * grep = 0; only an `implemented: false` placeholder) and crm2/Blade is
 * delinked, so they were dead, reachable, ungated PII surfaces. User
 * decision (2026-06-19): remove the dead routes outright.
 *
 * The Medical/Measurement MODELS stay (clinical-compliance history, pinned
 * by PatientMedicalHistoryTest / PatientMeasurementTest), the WRITE (fill)
 * routes stay, and the gated `custom_form_feedbacks` resource stays — only
 * the ungated list (datatable) endpoints are gone.
 *
 * Teeth: re-register any of these routes and its URI reappears → red.
 */
class ClinicalDatatableRoutesRemovedTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function removedDatatableRoutes(): array
    {
        return [
            'patient custom-form feedback list' => ['customformfeedbackspatient/datatable'],
            'patient medical history list'      => ['medicalhistoryform/datatable'],
            'patient measurement history list'  => ['measurementhistoryform/datatable'],
            'appointment measurement list'      => ['appointmentsmeasurement/datatable'],
            'appointment medical list'          => ['appointmentsmedical/datatable'],
            'custom-form feedback list (admin)' => ['custom_form_feedbacks/datatable'],
        ];
    }

    /**
     * @dataProvider removedDatatableRoutes
     */
    public function test_dead_clinical_datatable_route_is_not_registered(string $uriFragment): void
    {
        $registered = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->contains(fn (string $uri) => str_contains($uri, $uriFragment));

        $this->assertFalse(
            $registered,
            "Dead clinical PII list route '{$uriFragment}' must be removed — it was an ungated read (and unscoped bulk-delete) reachable by direct API call.",
        );
    }

    public function test_surviving_clinical_surfaces_are_left_intact(): void
    {
        // Guard against over-removal: the WRITE (fill) routes and the gated
        // custom_form_feedbacks resource (its destroy checks
        // custom_form_feedbacks_manage) must still exist.
        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route) => $route->uri());

        $this->assertTrue(
            $uris->contains(fn (string $u) => str_contains($u, 'medicalhistoryform/{id}')),
            'The medical-history FILL (write) route must remain — only the list endpoint was removed.',
        );
        $this->assertTrue(
            $uris->contains(fn (string $u) => str_contains($u, 'custom_form_feedbacks/{id}/export_pdf')),
            'The custom_form_feedbacks export route (part of the kept resource group) must remain.',
        );
    }
}
