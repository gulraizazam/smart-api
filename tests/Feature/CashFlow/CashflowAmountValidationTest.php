<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\Expense;
use App\Models\CashFlow\ExpenseCategory;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Adversarial amount-validation probes. The intended contract is
 * `numeric|min:1|max:99999999|integer` — a whole-rupee amount up to
 * ~10 crore. The audit raised a concern that scientific notation
 * ("1e9") or fractional strings might slip through and inflate the
 * cap. These tests pin what actually passes vs. fails so a future
 * dependency-bump can't change the semantics under us.
 */
class CashflowAmountValidationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_amount_validation_rejects_scientific_notation_and_overflow(): void
    {
        $rules = ['amount' => 'required|numeric|min:1|max:99999999|integer'];

        $cases = [
            // value, should-pass, label
            [100, true, 'small whole int'],
            [99_999_999, true, 'at max'],
            [100_000_000, false, 'one over max'],
            [0, false, 'zero rejected by min:1'],
            [-50, false, 'negative rejected by min:1'],
            [99.5, false, 'fractional float rejected by integer rule'],
            ['100', true, 'numeric string accepted'],
            ['100.0', false, 'string with decimal rejected by integer rule'],
            ['1e9', false, 'scientific-notation string rejected — bypass test'],
            ['1.99e8', false, 'fractional scientific-notation rejected'],
            [1e9, false, 'numeric scientific notation rejected by max'],
            ['abc', false, 'non-numeric rejected'],
            [null, false, 'null rejected by required'],
        ];

        foreach ($cases as [$value, $shouldPass, $label]) {
            $v = Validator::make(['amount' => $value], $rules);
            $passes = $v->passes();
            $this->assertSame(
                $shouldPass,
                $passes,
                "Amount validation drift on `{$label}` — value=".var_export($value, true)
                ." expected ".($shouldPass ? 'PASS' : 'FAIL')." got ".($passes ? 'PASS' : 'FAIL').'.'
                .' Errors: '.json_encode($v->errors()->all()),
            );
        }
    }
}
