<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Exceptions\AppointmentException;
use App\Exceptions\TreatmentException;
use App\Models\AppointmentTypes;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\Services;
use App\Models\User;
use App\Services\Appointment\AppointmentService;
use App\Services\Appointment\ConsultancyService;
use App\Services\Order\OrderService;
use App\Services\Treatment\TreatmentService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Policy (order carve-out REVERTED 2026-06-17, crm2 delinked): a patient row
 * (`users` with `user_type_id = 3`) is created ONLY by a consultation booking.
 * Treatment booking, generic appointment booking, drag-drop, AND product ORDERS
 * all reject brand-new phone numbers — a product order now requires an
 * ALREADY-REGISTERED `patient_id` (the 2026-06-01 order-only carve-out for crm2
 * parity is gone).
 *
 * This file pins the gates it exercises directly: treatment booking, generic
 * (non-consultancy) appointment booking, and product orders reject an unknown
 * phone; only consultation registers one — so a future refactor that flips any
 * of them fails CI. (Drag-drop rejects through the same non-consultancy
 * AppointmentService gate as the appointment test, so it is covered
 * transitively, not by a separate case.)
 */
class PatientCreationPolicyTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_treatment_service_rejects_unknown_phone_and_creates_no_patient(): void
    {
        $patientCountBefore = Patients::query()->count();

        $payload = $this->validTreatmentPayload([
            'phone' => '+92300' . random_int(1000000, 9999999),
            'name'  => 'New Caller',
        ]);

        try {
            app(TreatmentService::class)->store($payload);
            $this->fail('Treatment booking with an unknown phone must throw — patient creation is consultancy-only.');
        } catch (TreatmentException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('Book a consultation first', $e->getMessage());
            $this->assertSame(
                'PATIENT_NOT_REGISTERED',
                $e->getErrorData()['code'] ?? null,
                'Error payload should include the stable code so the SPA can render the right CTA.',
            );
            $this->assertSame(
                $patientCountBefore,
                Patients::query()->count(),
                'Treatment booking must NOT create a patient row.',
            );
        }
    }

    public function test_treatment_service_accepts_existing_patient_phone(): void
    {
        // Pre-existing patient → treatment lookup should resolve and
        // proceed (we don't assert success of the full booking here,
        // only that the patient-not-registered gate does not fire).
        // Patient phones in production are stored AFTER
        // PhoneFormattingService::cleanNumber, which strips leading
        // 0 / 92 country codes. Mirror that here so the lookup key
        // matches the stored value.
        $cleanedPhone = '301' . random_int(1000000, 9999999);
        $existing = Patients::factory()->create([
            'phone' => $cleanedPhone,
        ]);

        $payload = $this->validTreatmentPayload([
            'phone' => $existing->phone,
            'name'  => $existing->name,
        ]);

        $patientCountBefore = Patients::query()->count();

        try {
            app(TreatmentService::class)->store($payload);
        } catch (TreatmentException $e) {
            // The booking can fail downstream (rota/resource etc) — we
            // only care that it did NOT fail with patientNotRegistered.
            $this->assertNotSame(
                'PATIENT_NOT_REGISTERED',
                $e->getErrorData()['code'] ?? null,
                'Existing patient must not trigger the patient-not-registered gate.',
            );
        }

        $this->assertSame(
            $patientCountBefore,
            Patients::query()->count(),
            'Treatment booking must never create a new patient, even for known phones.',
        );
    }

    public function test_treatment_lookup_finds_patient_stored_with_a_leading_zero(): void
    {
        // Regression (2026-06-05): ~1k consultation-created patients were
        // saved with the raw leading zero (`0307...`) instead of the
        // canonical cleaned form (`307...`). The lookup ran cleanNumber
        // (which peels the zero) and exact-matched, so those patients
        // surfaced as "No registered patient with this phone" and could
        // never be booked for a treatment. Pin both lookup seams here.
        $storedPhone = '0307' . random_int(1000000, 9999999); // leading zero, as stored
        $existing = Patients::factory()->create(['phone' => $storedPhone]);

        // 1) Direct model lookup (the SPA's load/lead phone search) must
        //    resolve the leading-zero row from the cleaned key.
        $found = Patients::getByPhone(
            \App\Services\Phone\PhoneFormattingService::cleanNumber($storedPhone),
        );
        $this->assertNotNull($found, 'getByPhone must find a patient stored with a leading zero.');
        $this->assertSame($existing->id, $found->id);

        // 2) Treatment-submit resolver must NOT fire patient-not-registered
        //    for the same number, and must never spawn a patient.
        $payload = $this->validTreatmentPayload([
            'phone' => $storedPhone,
            'name'  => $existing->name,
        ]);
        $patientCountBefore = Patients::query()->count();

        try {
            app(TreatmentService::class)->store($payload);
        } catch (TreatmentException $e) {
            $this->assertNotSame(
                'PATIENT_NOT_REGISTERED',
                $e->getErrorData()['code'] ?? null,
                'A patient stored with a leading zero must resolve, not be reported unregistered.',
            );
        }

        $this->assertSame(
            $patientCountBefore,
            Patients::query()->count(),
            'Treatment booking must never create a patient, even when resolving a leading-zero phone.',
        );
    }

    public function test_consultancy_service_creates_patient_when_phone_unknown(): void
    {
        // The single sanctioned create path. Without this positive
        // pinning a future "tightening" could silently break the only
        // workflow that may register a patient.
        $location = Locations::factory()->create();
        $doctor   = User::factory()->doctor()->create();
        $consultancyTypeId = AppointmentTypes::query()
            ->where('account_id', 1)
            ->where('slug', 'consultancy')
            ->value('id');
        $this->assertNotNull($consultancyTypeId, 'Consultancy appointment type fixture missing.');

        $phone = '+92305' . random_int(1000000, 9999999);

        $patientCountBefore = Patients::query()->count();

        try {
            app(ConsultancyService::class)->createConsultancy([
                'appointment_type_id'   => $consultancyTypeId,
                'appointment_status_id' => 1,
                'location_id'           => $location->id,
                'doctor_id'             => $doctor->id,
                'phone'                 => $phone,
                'name'                  => 'Brand New Caller',
                'gender'                => 1,
            ]);
        } catch (AppointmentException $e) {
            $this->fail("Consultancy patient-create gate must not fire — got: {$e->getMessage()}");
        } catch (\Throwable) {
            // Other failures (FK constraints, etc.) are out of scope —
            // the patient-create branch fires before any of that.
        }

        $this->assertGreaterThan(
            $patientCountBefore,
            Patients::query()->count(),
            'Consultancy booking with an unknown phone MUST spawn a patient row.',
        );
    }

    public function test_consultancy_with_existing_patient_id_attaches_and_does_not_duplicate(): void
    {
        // Regression: the consultation create form resolved the existing
        // patient by phone but didn't pass patient_id, so the backend (which
        // only checked lead_id) minted a DUPLICATE patient. The new
        // consultation then landed on the duplicate while the patient's
        // earlier consultations stayed on the original — looking like the
        // arrived consultation "disappeared" from the tab. With a supplied
        // patient_id the booking must attach to that patient, not duplicate.
        $location = Locations::factory()->create();
        $doctor   = User::factory()->doctor()->create();
        $consultancyTypeId = AppointmentTypes::query()
            ->where('account_id', 1)
            ->where('slug', 'consultancy')
            ->value('id');

        $existing = Patients::factory()->create([
            'phone' => '309' . random_int(1000000, 9999999),
        ]);

        $patientCountBefore = Patients::query()->count();

        try {
            $appt = app(ConsultancyService::class)->createConsultancy([
                'appointment_type_id'   => $consultancyTypeId,
                'appointment_status_id' => 1,
                'location_id'           => $location->id,
                'doctor_id'             => $doctor->id,
                'patient_id'            => $existing->id,
                'phone'                 => $existing->phone,
                'name'                  => $existing->name,
                'gender'                => 1,
            ]);
            $this->assertSame(
                (int) $existing->id,
                (int) $appt->patient_id,
                'Consultation must attach to the supplied existing patient.',
            );
        } catch (\Throwable) {
            // Downstream FK/rota issues are out of scope — the duplicate
            // guard fires before any appointment insert, so the invariant
            // below still holds.
        }

        $this->assertSame(
            $patientCountBefore,
            Patients::query()->count(),
            'Supplying patient_id must NOT spawn a duplicate patient.',
        );
    }

    public function test_appointment_service_rejects_patient_create_for_non_consultancy_type(): void
    {
        $treatmentTypeId = AppointmentTypes::query()
            ->where('account_id', 1)
            ->where('slug', 'treatment')
            ->value('id');
        $this->assertNotNull($treatmentTypeId, 'Treatment appointment type fixture missing.');

        $patientCountBefore = Patients::query()->count();
        $location = Locations::factory()->create();

        $this->expectException(AppointmentException::class);
        $this->expectExceptionMessage('Book a consultation first');

        try {
            app(AppointmentService::class)->createAppointment([
                'appointment_type_id'   => $treatmentTypeId,
                'appointment_status_id' => 1,
                'location_id'           => $location->id,
                'phone'                 => '+92302' . random_int(1000000, 9999999),
                'name'                  => 'New Caller',
                'gender'                => 1,
            ]);
        } finally {
            $this->assertSame(
                $patientCountBefore,
                Patients::query()->count(),
                'Non-consultancy appointment booking must NOT spawn a patient.',
            );
        }
    }

    public function test_order_with_unknown_phone_is_rejected_and_creates_no_patient(): void
    {
        // Carve-out reverted (2026-06-17): a product order for a NEW phone
        // (name+phone, no patient_id) is rejected and registers NO patient —
        // orders require an already-registered patient. The patient gate fires
        // before product validation, so an empty cart still surfaces it.
        $patientCountBefore = Patients::query()->count();
        $phone = '+92303' . random_int(1000000, 9999999);

        try {
            app(OrderService::class)->createOrder([
                'name'         => 'Walk-in Customer',
                'phone'        => $phone,
                'product_id'   => [],
                'quantity'     => [],
                'sold_to'      => 'patient',
                'payment_mode' => 'cash',
                'location_id'  => $this->defaultLocation->id ?? 1,
            ]);
            $this->fail('A product order for an unregistered patient must be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertNull(
            Patients::where('phone', $phone)->first(),
            'A product order must NOT register a patient (carve-out reverted 2026-06-17).',
        );
        $this->assertSame(
            $patientCountBefore,
            Patients::query()->count(),
            'No patient may be created via a product order.',
        );
    }

    public function test_order_with_existing_phone_but_no_patient_id_is_still_rejected(): void
    {
        // Strict: an order no longer silently resolves a patient by phone — the
        // operator must select the registered patient (patient_id). An existing
        // patient's phone alone (no patient_id) is rejected, and no duplicate is
        // ever spawned.
        $existing = Patients::factory()->create([
            'phone' => '+92304' . random_int(1000000, 9999999),
        ]);
        $patientCountBefore = Patients::query()->count();

        try {
            app(OrderService::class)->createOrder([
                'name'         => 'Walk-in Customer',
                'phone'        => $existing->phone,
                'product_id'   => [],
                'quantity'     => [],
                'sold_to'      => 'patient',
                'payment_mode' => 'cash',
                'location_id'  => $this->defaultLocation->id ?? 1,
            ]);
            $this->fail('An order without an explicit patient_id must be rejected.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(
            $patientCountBefore,
            Patients::query()->count(),
            'Order must never spawn a duplicate patient.',
        );
    }

    /**
     * Build a treatment payload that satisfies StoreTreatmentRequest
     * defaults but does NOT include a patient_id — so the patient
     * lookup-by-phone gate is what determines pass/fail.
     */
    private function validTreatmentPayload(array $overrides = []): array
    {
        $location = Locations::factory()->create();
        $doctor   = User::factory()->doctor()->create();
        $service  = Services::factory()->create();
        $treatmentTypeId = AppointmentTypes::query()
            ->where('account_id', 1)
            ->where('slug', 'treatment')
            ->value('id') ?? 1;

        return array_merge([
            'name'                  => 'New Caller',
            'phone'                 => '+92300' . random_int(1000000, 9999999),
            'service_id'            => $service->id,
            'location_id'           => $location->id,
            'doctor_id'             => $doctor->id,
            'appointment_type_id'   => $treatmentTypeId,
            'appointment_status_id' => 1,
        ], $overrides);
    }
}
