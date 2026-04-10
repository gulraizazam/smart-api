<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoices;
use App\Models\Locations;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\PaymentModes;
use App\Models\Refunds;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refunds>
 *
 * Default state mirrors a refund-out flow on a package — the most common
 * pattern in the audit-fixed cancellation pipeline. Use the `inFlow` state
 * for the rare reversal direction.
 */
class RefundFactory extends Factory
{
    protected $model = Refunds::class;

    public function definition(): array
    {
        return [
            'cash_flow' => 'out',
            'cash_amount' => $this->faker->randomFloat(2, 100, 5000),
            'active' => 1,
            'patient_id' => Patients::factory(),
            'payment_mode_id' => PaymentModes::factory(),
            'account_id' => 1,
            'appointment_type_id' => 1,
            'location_id' => Locations::factory(),
            'created_by' => User::factory(),
            'package_id' => Packages::factory(),
            'invoice_id' => Invoices::factory(),
            'is_refund' => 1,
            'is_setteled' => 0,
        ];
    }
}
