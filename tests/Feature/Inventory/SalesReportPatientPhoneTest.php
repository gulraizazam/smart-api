<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Brand;
use App\Models\Inventory;
use App\Models\Patients;
use App\Models\Product;
use App\Models\Stock;
use App\Services\Order\OrderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The inventory Sales report's patient column shows the patient's phone and
 * C-id (copyable), not just the name — so the API row must carry
 * `patient_phone`. Pins that a patient product-sale surfaces the patient's
 * phone through `GET /api/reports/inventory/sales`, and that employee sales
 * (no patient) leave it null.
 */
class SalesReportPatientPhoneTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin(); // super-admin → inventory_report_manage gate bypassed
    }

    private function makeSaleableProduct(float $salePrice = 1000): Product
    {
        $brand = Brand::create(['account_id' => 1, 'name' => 'Brand '.uniqid(), 'status' => 1]);

        $product = new Product();
        $product->account_id = 1;
        $product->brand_id = $brand->id;
        $product->name = 'Product '.uniqid();
        $product->slug = Str::slug($product->name);
        $product->sale_price = $salePrice;
        $product->purchase_price = 500;
        $product->product_type = 'for_sale';
        $product->status = 1;
        $product->active = 1;
        $product->created_by = Auth::id() ?? 1;
        $product->save();

        return $product;
    }

    private function stockAt(int $productId, int $locationId, int $qty, float $salePrice): int
    {
        Stock::create([
            'account_id' => 1,
            'product_id' => $productId,
            'quantity' => $qty,
            'stock_type' => 'in',
            'location_id' => $locationId,
        ]);

        return (int) Inventory::create([
            'product_id' => $productId,
            'location_id' => $locationId,
            'quantity' => $qty,
            'sale_price' => $salePrice,
            'is_saleable' => 1,
        ])->id;
    }

    public function test_sales_report_row_carries_the_patient_phone(): void
    {
        $loc = (int) $this->defaultLocation->id;
        $product = $this->makeSaleableProduct(1000);
        $invId = $this->stockAt($product->id, $loc, 5, 1000);

        $phone = '+92307'.random_int(1000000, 9999999);
        app(OrderService::class)->createOrder([
            'product_id' => [$product->id],
            'quantity' => [1],
            'product_price' => [0],
            'inventory_id' => [$invId],
            'payment_mode' => 1,
            'sold_to' => 'patient',
            'name' => 'Walk-in Patient',
            'phone' => $phone,
            'location_id' => $loc,
            'discount' => 0,
        ]);

        $response = $this->postJson('/api/reports/inventory/sales', [
            'centre_id' => [$loc],
            'date_range' => '2020-01-01 - 2035-12-31',
        ]);

        $response->assertOk();
        $row = collect($response->json('data.rows'))->firstWhere('patient_id', '!==', null);
        $this->assertNotNull($row, 'a patient product-sale should appear in the sales report.');
        $this->assertSame($phone, $row['patient_phone'],
            'the report row must expose the patient phone for the SPA patient cell.');

        // Sanity: the patient was registered with that phone.
        $this->assertNotNull(Patients::where('phone', $phone)->first());
    }
}
