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
 * Policy: a patient row (`users` with `user_type_id = 3`) may be
 * created **only** as a side-effect of a consultation booking.
 * Treatment booking, generic appointment booking, drag-drop, and
 * order/inventory sales must all reject brand-new phone numbers.
 *
 * This test pins all four gates so a future refactor that re-opens
 * any of them — even accidentally — fails CI.
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

    public function test_order_create_record_rejects_unknown_phone(): void
    {
        $patientCountBefore = Patients::query()->count();

        $request = new Request([
            'name'           => 'Walk-in Customer',
            'phone'          => '+92303' . random_int(1000000, 9999999),
            'product_id'     => [],
            'quantity'       => [],
            'product_price'  => [],
            'sold_to'        => 'patient',
            'payment_mode'   => 'cash',
        ]);

        try {
            Order::createRecord($request, 1, []);
            $this->fail('Order::createRecord with an unknown phone must throw — patient creation is consultancy-only.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('Book a consultation first', $e->getMessage());
        }

        $this->assertSame(
            $patientCountBefore,
            Patients::query()->count(),
            'Order creation must NOT spawn a patient row.',
        );
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
        // — we only care that the patient-creation gate did NOT trip.
        try {
            Order::createRecord($request, 1, []);
        } catch (HttpException $e) {
            // If we get here at all, it must NOT be 422 with the
            // policy message — that would mean an existing patient
            // was rejected.
            $this->assertNotSame(
                422,
                $e->getStatusCode(),
                'Order with an existing patient phone should not hit the patient-not-registered gate.',
            );
        } catch (\Throwable) {
            // Other exceptions (missing FKs etc.) are fine — out of scope here.
        }

        $this->assertSame(
            $patientCountBefore,
            Patients::query()->count(),
            'Order::createRecord must never spawn a new patient, even for known phones.',
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
