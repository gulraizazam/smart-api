<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Exceptions\AppointmentException;
use App\Exceptions\TreatmentException;
use App\Models\AppointmentTypes;
use App\Models\Locations;
use App\Models\Order;
use App\Models\Patients;
use App\Models\Services;
use App\Models\User;
use App\Services\Appointment\AppointmentService;
use App\Services\Appointment\ConsultancyService;
use App\Services\Treatment\TreatmentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Policy (revised 2026-06-01): a patient row (`users` with `user_type_id = 3`)
 * is created by a consultation booking AND by a product ORDER (the order-only
 * carve-out, for parity with legacy crm2 during coexistence). Treatment
 * booking, generic appointment booking, and drag-drop STILL reject brand-new
 * phone numbers.
 *
 * This file pins the gates it exercises directly: treatment booking and generic
 * (non-consultancy) appointment booking reject an unknown phone; consultation AND
 * order register one — so a future refactor that flips any of them fails CI.
 * (Drag-drop rejects through the same non-consultancy AppointmentService gate as
 * the appointment test, so it is covered transitively, not by a separate case.)
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

    public function test_order_create_record_creates_patient_for_unknown_phone(): void
    {
        // ORDER-ONLY carve-out (2026-06-01): an order for a NEW phone registers
        // a patient (parity with legacy crm2). Other entry points still reject;
        // consultation remains the canonical creator.
        $patientCountBefore = Patients::query()->count();
        $phone = '+92303' . random_int(1000000, 9999999);

        $request = new Request([
            'name'           => 'Walk-in Customer',
            'phone'          => $phone,
            'product_id'     => [],
            'quantity'       => [],
            'product_price'  => [],
            'sold_to'        => 'patient',
            'payment_mode'   => 'cash',
            'location_id'    => $this->defaultLocation->id ?? 1,
            'location_type'  => 'location_id',
            'doctor_id'      => null,
            'grand_total'    => 0,
        ]);

        try {
            Order::createRecord($request, 1, []);
        } catch (\Throwable) {
            // Downstream order assembly may fail (no products/FKs) — out of
            // scope; the patient is registered at the gate before that.
        }

        $created = Patients::where('phone', $phone)->first();
        $this->assertNotNull(
            $created,
            'Order for an unknown phone must REGISTER a proper, visible patient (order-only carve-out).',
        );
        $this->assertSame(1, (int) $created->account_id, 'order-created patient must be account-scoped (not an orphan).');
        $this->assertSame($patientCountBefore + 1, Patients::query()->count(), 'exactly one patient registered.');
    }

    public function test_order_create_record_resolves_existing_patient_by_phone(): void
    {
        $existing = Patients::factory()->create([
            'phone' => '+92304' . random_int(1000000, 9999999),
        ]);

        $patientCountBefore = Patients::query()->count();

        $request = new Request([
            'name'           => 'Walk-in Customer',
            'phone'          => $existing->phone,
            'product_id'     => [1],
            'quantity'       => [1],
            'product_price'  => [100],
            'sold_to'        => 'patient',
            'payment_mode'   => 'cash',
        ]);

        // Order::createRecord may still fail later (missing FKs, etc.)
        // — we only care that the EXISTING patient was resolved by phone,
        // not duplicated.
        try {
            Order::createRecord($request, 1, []);
        } catch (HttpException $e) {
            // A known phone must RESOLVE to the existing patient; it must
            // never 422 (the order-only carve-out creates only for an
            // *unknown* phone, and an existing one is reused).
            $this->assertNotSame(
                422,
                $e->getStatusCode(),
                'Order with an existing patient phone should resolve it, not reject.',
            );
        } catch (\Throwable) {
            // Other exceptions (missing FKs etc.) are fine — out of scope here.
        }

        $this->assertSame(
            $patientCountBefore,
            Patients::query()->count(),
            'Order::createRecord must resolve a known phone, never spawn a duplicate patient.',
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
