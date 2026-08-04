<?php

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One-off demo data generator for the sales demo / screenshot PDF.
 *
 * Populates the modules that ship empty on a fresh clone (patients, appointments,
 * plans, invoices, memberships, inventory, orders, cash flow, HR) with FAKE data
 * scoped to account_id = 1. Factories are instantiated by class (not via
 * Model::factory()) to sidestep name-guessing (PatientFactory != PatientsFactory).
 *
 * Every section is wrapped so a single failure does not abort the run; a summary
 * of what succeeded is printed at the end.
 */
class DemoDataSeeder extends Seeder
{
    /** @var array<string,string> */
    private array $report = [];

    public function run(): void
    {
        $acc = 1;
        $faker = \Faker\Factory::create();

        // The factory files are flat + singular (PatientFactory), but the models
        // are plural / namespaced (App\Models\Patients, App\Models\CashFlow\CashPool).
        // Default guessing looks for Database\Factories\PatientsFactory /
        // Database\Factories\CashFlow\CashPoolFactory, which don't exist. Resolve
        // to the flat singular name instead. This also fixes nested Model::factory()
        // calls inside each factory's definition().
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            $base = class_basename($modelName);
            $ns = 'Database\\Factories\\';
            foreach ([$base, Str::singular($base)] as $cand) {
                if (class_exists($ns.$cand.'Factory')) {
                    return $ns.$cand.'Factory';
                }
            }
            return $ns.$base.'Factory';
        });

        // ---- resolve reference data already present on the clone -------------
        $doctorIds = \App\Models\User::where('account_id', $acc)
            ->where('can_perform_consultation', 1)->pluck('id')->all();
        $staffIds = \App\Models\User::where('account_id', $acc)
            ->whereIn('user_type_id', [1, 2, 5])->pluck('id')->all();
        $creator = 1; // superadmin
        $locationIds = \App\Models\Locations::where('account_id', $acc)
            ->whereNotIn('id', [2, 3, 6])->pluck('id')->all();
        if (empty($locationIds)) {
            $locationIds = \App\Models\Locations::where('account_id', $acc)->pluck('id')->all();
        }
        $serviceIds = \App\Models\Services::where('account_id', $acc)
            ->where('parent_id', '<>', 0)->limit(40)->pluck('id')->all();
        $paymentModeIds = \App\Models\PaymentModes::pluck('id')->all() ?: [1];
        $packageIds = \App\Models\Packages::pluck('id')->all();
        $poolIds = \App\Models\CashFlow\CashPool::pluck('id')->all() ?: [1];
        $leadIds = \App\Models\Leads::limit(80)->pluck('id')->all();
        $regionId = (int) (\App\Models\Regions::value('id') ?? 1);
        $cityId = (int) (\App\Models\Cities::value('id') ?? 1);
        $expenseCatIds = DB::table('expense_categories')->pluck('id')->all();

        // ---- extra doctors + employees (so People/HR screens look alive) -----
        $newDoctorIds = [];
        $this->safe('doctors', function () use ($acc, $faker, &$newDoctorIds, &$doctorIds) {
            if (count($doctorIds) >= 4) { return 'skip: already '.count($doctorIds); }
            foreach (range(1, 4) as $i) {
                $id = DB::table('users')->insertGetId([
                    'name' => 'Dr. '.$faker->firstName().' '.$faker->lastName(),
                    'email' => 'doctor'.$i.'.'.Str::random(4).'@example.com',
                    'password' => Hash::make('password'),
                    'phone' => '0300'.$faker->numerify('#######'),
                    'gender' => $faker->randomElement([0, 1]),
                    'user_type_id' => 5,
                    'account_id' => $acc,
                    'main_account' => 1,
                    'active' => 1,
                    'hr_managed' => 1,
                    'can_perform_consultation' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $newDoctorIds[] = $id;
            }
            $doctorIds = array_merge($doctorIds, $newDoctorIds);
            return count($newDoctorIds).' doctors';
        });
        if (empty($doctorIds)) { $doctorIds = $staffIds ?: [$creator]; }

        // ---- Departments + Designations --------------------------------------
        $deptIds = [];
        $desigIds = [];
        $this->safe('departments+designations', function () use ($acc, &$deptIds, &$desigIds) {
            $deptIds = DB::table('departments')->where('account_id', $acc)->pluck('id')->all();
            $desigIds = DB::table('designations')->where('account_id', $acc)->pluck('id')->all();
            if (! empty($deptIds) && ! empty($desigIds)) {
                return 'skip: '.count($deptIds).' depts, '.count($desigIds).' designations exist';
            }
            $depts = ['Clinical', 'Front Desk', 'Finance', 'Marketing', 'Operations'];
            foreach ($depts as $d) {
                $deptIds[] = DB::table('departments')->insertGetId([
                    'name' => $d, 'account_id' => $acc, 'active' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $desigs = ['Consultant', 'Therapist', 'Receptionist', 'Accountant', 'Manager', 'Nurse', 'Marketing Executive'];
            foreach ($desigs as $i => $name) {
                $desigIds[] = DB::table('designations')->insertGetId([
                    'name' => $name, 'department_id' => $deptIds[$i % count($deptIds)],
                    'account_id' => $acc, 'active' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return count($deptIds).' depts, '.count($desigIds).' designations';
        });

        // ---- Employees (users hr_managed=1 + employee_details) ---------------
        $this->safe('employees', function () use ($acc, $faker, $deptIds, $desigIds, $locationIds) {
            $existing = DB::table('employee_details')->where('account_id', $acc)->count();
            if ($existing >= 10) { return 'skip: '.$existing.' employee_details exist'; }
            $n = 0;
            foreach (range(1, 12) as $i) {
                $uid = DB::table('users')->insertGetId([
                    'name' => $faker->firstName().' '.$faker->lastName(),
                    'email' => 'employee'.$i.'.'.Str::random(4).'@example.com',
                    'password' => Hash::make('password'),
                    'phone' => '0300'.$faker->numerify('#######'),
                    'gender' => $faker->randomElement([0, 1]),
                    'cnic' => '35202-'.$faker->numerify('#######').'-'.$faker->numerify('#'),
                    'dob' => $faker->date('Y-m-d', '-25 years'),
                    'user_type_id' => 2,
                    'account_id' => $acc,
                    'main_account' => 1,
                    'active' => 1,
                    'hr_managed' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $row = ['user_id' => $uid, 'account_id' => $acc, 'created_at' => now(), 'updated_at' => now()];
                foreach ([
                    'department_id' => $deptIds ? Arr::random($deptIds) : null,
                    'designation_id' => $desigIds ? Arr::random($desigIds) : null,
                    'location_id' => $locationIds ? Arr::random($locationIds) : null,
                    'joining_date' => $faker->date('Y-m-d', '-1 years'),
                    'employment_type' => 'full_time',
                    'status' => 'active',
                ] as $col => $val) {
                    if ($this->hasColumn('employee_details', $col)) { $row[$col] = $val; }
                }
                DB::table('employee_details')->insert($row);
                $n++;
            }
            return "$n employees";
        });

        // ---- Recruitment candidates ------------------------------------------
        $this->safe('recruitment_candidates', function () use ($acc, $faker, $desigIds, $cityId, $locationIds, $creator) {
            $existing = DB::table('recruitment_candidates')->where('account_id', $acc)->count();
            if ($existing >= 8) { return 'skip: '.$existing.' candidates exist'; }
            $n = 0;
            $stages = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'];
            foreach (range(1, 10) as $i) {
                $row = [
                    'name' => $faker->firstName().' '.$faker->lastName(),
                    'designation_id' => $desigIds ? Arr::random($desigIds) : 1,
                    'city_id' => $cityId,
                    'location_id' => $locationIds ? Arr::random($locationIds) : 1,
                    'account_id' => $acc,
                    'created_by' => $creator,
                    'created_at' => now(), 'updated_at' => now(),
                ];
                foreach ([
                    'email' => 'candidate'.$i.'@example.com',
                    'phone' => '0300'.$faker->numerify('#######'),
                    'stage' => Arr::random($stages),
                    'status' => Arr::random($stages),
                ] as $col => $val) {
                    if ($this->hasColumn('recruitment_candidates', $col)) { $row[$col] = $val; }
                }
                DB::table('recruitment_candidates')->insert($row);
                $n++;
            }
            return "$n candidates";
        });

        // ---- Patients ---------------------------------------------------------
        $patientIds = \App\Models\User::where('account_id', $acc)->where('user_type_id', 3)->pluck('id')->all();
        $this->safe('patients', function () use (&$patientIds) {
            if (count($patientIds) >= 40) { return 'skip: '.count($patientIds).' patients exist'; }
            $made = \Database\Factories\PatientFactory::new()->count(40)->create();
            $patientIds = array_merge($patientIds, $made->pluck('id')->all());
            return count($made).' new patients';
        });
        if (empty($patientIds)) { $patientIds = [3498]; }

        // ---- Inventory: warehouses, brands, products -------------------------
        $whIds = DB::table('warehouses')->where('account_id', $acc)->pluck('id')->all();
        $this->safe('warehouses', function () use (&$whIds, $acc, $faker, $regionId, $cityId) {
            if (count($whIds) >= 2) { return 'skip: '.count($whIds).' warehouses exist'; }
            foreach (range(1, 3) as $i) {
                $whIds[] = DB::table('warehouses')->insertGetId([
                    'account_id' => $acc,
                    'name' => ucfirst($faker->unique()->word()).' Warehouse',
                    'manager_name' => $faker->firstName().' '.$faker->lastName(),
                    'manager_phone' => '0300'.$faker->numerify('#######'),
                    'address' => $faker->streetAddress(),
                    'region_id' => $regionId,
                    'city_id' => $cityId,
                    'active' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return count($whIds).' warehouses';
        });
        if (empty($whIds)) { $whIds = DB::table('warehouses')->pluck('id')->all() ?: [1]; }

        $brandIds = \App\Models\Brand::where('account_id', $acc)->pluck('id')->all();
        $this->safe('brands', function () use (&$brandIds) {
            if (count($brandIds) >= 5) { return 'skip: '.count($brandIds).' brands exist'; }
            $made = \Database\Factories\BrandFactory::new()->count(6)->create();
            $brandIds = array_merge($brandIds, $made->pluck('id')->all());
            return count($made).' brands';
        });
        if (empty($brandIds)) { $brandIds = [1]; }

        // Products via guarded direct insert (schema here has no warehouse_id).
        $productIds = \App\Models\Product::where('account_id', $acc)->pluck('id')->all();
        $this->safe('products', function () use (&$productIds, $acc, $faker, $brandIds, $locationIds, $whIds, $creator) {
            if (count($productIds) >= 15) { return 'skip: '.count($productIds).' products exist'; }
            $n = 0;
            foreach (range(1, 20) as $i) {
                $price = $faker->randomFloat(2, 100, 5000);
                $want = [
                    'name' => ucwords($faker->unique()->words(2, true)),
                    'account_id' => $acc,
                    'brand_id' => Arr::random($brandIds),
                    'location_id' => $locationIds ? Arr::random($locationIds) : null,
                    'warehouse_id' => $whIds ? Arr::random($whIds) : null,
                    'sale_price' => $price,
                    'purchase_price' => $faker->randomFloat(2, 50, $price),
                    'product_type' => 'for_sale',
                    'status' => 1,
                    'created_by' => $creator,
                    'sku' => 'SKU-'.strtoupper(Str::random(6)),
                    'quantity' => $faker->numberBetween(0, 100),
                ];
                $row = ['created_at' => now(), 'updated_at' => now()];
                foreach ($want as $col => $val) {
                    if ($this->hasColumn('products', $col)) { $row[$col] = $val; }
                }
                $productIds[] = DB::table('products')->insertGetId($row);
                $n++;
            }
            return "$n products";
        });
        if (empty($productIds)) { $productIds = [1]; }

        // ---- Vendors ----------------------------------------------------------
        $this->safe('vendors', function () use ($acc, $creator) {
            $existing = \App\Models\CashFlow\Vendor::where('account_id', $acc)->count();
            if ($existing >= 5) { return 'skip: '.$existing.' vendors exist'; }
            $made = \Database\Factories\VendorFactory::new()->count(6)->state(fn () => ['created_by' => $creator])->create();
            return count($made).' vendors';
        });

        // ---- Appointments (varied statuses) ----------------------------------
        $this->safe('appointments', function () use ($patientIds, $leadIds, $serviceIds, $doctorIds, $locationIds, $regionId, $cityId) {
            if (DB::table('appointments')->count() >= 45) { return 'skip: enough appointments'; }
            if (empty($leadIds)) { throw new \RuntimeException('no leads to link'); }
            $statuses = [1, 2, 3, 4, 5, 11, 12];
            $made = \Database\Factories\AppointmentFactory::new()->count(45)->state(fn () => [
                'patient_id' => Arr::random($patientIds),
                'lead_id' => Arr::random($leadIds),
                'service_id' => Arr::random($serviceIds),
                'doctor_id' => Arr::random($doctorIds),
                'location_id' => Arr::random($locationIds),
                'region_id' => $regionId,
                'city_id' => $cityId,
                'appointment_status_id' => Arr::random($statuses),
                'scheduled_date' => now()->addDays(random_int(-20, 20))->format('Y-m-d'),
            ])->create();
            return count($made).' appointments';
        });

        // ---- Plans (package_advances) ----------------------------------------
        $this->safe('plans', function () use ($patientIds, $paymentModeIds, $locationIds, $packageIds, $creator) {
            if (DB::table('package_advances')->count() >= 50) { return 'skip: enough plans'; }
            if (empty($packageIds)) { throw new \RuntimeException('no packages'); }
            $made = \Database\Factories\PackageAdvanceFactory::new()->count(30)->state(fn () => [
                'patient_id' => Arr::random($patientIds),
                'payment_mode_id' => Arr::random($paymentModeIds),
                'location_id' => Arr::random($locationIds),
                'package_id' => Arr::random($packageIds),
                'created_by' => $creator,
                'cash_amount' => random_int(2000, 60000),
            ])->create();
            return count($made).' plan advances';
        });

        // ---- Invoices + details (direct insert; schema has no is_settlement) --
        $this->safe('invoices', function () use ($acc, $faker, $patientIds, $doctorIds, $locationIds, $serviceIds, $creator) {
            if (DB::table('invoices')->count() >= 25) { return 'skip: enough invoices'; }
            $count = 0; $details = 0;
            foreach (range(1, 20) as $i) {
                $iid = DB::table('invoices')->insertGetId([
                    'total_price' => $faker->randomFloat(2, 500, 50000),
                    'account_id' => $acc,
                    'patient_id' => Arr::random($patientIds),
                    'invoice_status_id' => 1,
                    'created_by' => $creator,
                    'location_id' => Arr::random($locationIds),
                    'doctor_id' => Arr::random($doctorIds),
                    'active' => 1,
                    'is_exclusive' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $count++;
                foreach (range(1, random_int(1, 3)) as $j) {
                    $sp = $faker->randomFloat(2, 100, 10000);
                    DB::table('invoice_details')->insert([
                        'qty' => 1,
                        'service_price' => $sp,
                        'net_amount' => $sp,
                        'service_id' => Arr::random($serviceIds),
                        'invoice_id' => $iid,
                        'active' => 1,
                        'tax_exclusive_serviceprice' => $sp,
                        'tax_price' => 0,
                        'tax_including_price' => $sp,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $details++;
                }
            }
            return "$count invoices, $details lines";
        });

        // ---- Memberships ------------------------------------------------------
        $this->safe('memberships', function () use ($patientIds, $creator) {
            if (DB::table('memberships')->count() >= 15) { return 'skip: enough memberships'; }
            $types = \Database\Factories\MembershipTypeFactory::new()->count(4)->state(fn () => ['created_by' => $creator])->create();
            $typeIds = $types->pluck('id')->all();
            $made = \Database\Factories\MembershipFactory::new()->count(20)->state(fn () => [
                'membership_type_id' => Arr::random($typeIds),
                'patient_id' => Arr::random($patientIds),
                'created_by' => $creator,
            ])->create();
            return count($types).' types, '.count($made).' memberships';
        });

        // ---- Orders + details (denormalized schema: orders.product_id NOT NULL)
        $this->safe('orders', function () use ($acc, $faker, $patientIds, $locationIds, $whIds, $productIds, $creator) {
            if (DB::table('orders')->count() >= 15) { return 'skip: enough orders'; }
            $count = 0;
            foreach (range(1, 20) as $i) {
                $prod = Arr::random($productIds);
                $qty = random_int(1, 5);
                $price = $faker->randomFloat(2, 500, 20000);
                $oid = DB::table('orders')->insertGetId([
                    'patient_id' => Arr::random($patientIds),
                    'product_id' => $prod,
                    'location_id' => Arr::random($locationIds),
                    'warehouse_id' => $whIds ? Arr::random($whIds) : null,
                    'total_price' => $price,
                    'order_type' => 'sale',
                    'payment_mode' => Arr::random(['cash', 'card', 'bank_wire']),
                    'status' => 1,
                    'is_refunded' => 0,
                    'created_by' => $creator,
                    'quantity' => (string) $qty,
                    'account_id' => $acc,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('order_details')->insert([
                    'order_id' => $oid,
                    'product_id' => $prod,
                    'quantity' => $qty,
                    'sale_price' => $price,
                    'sale_price_after_discount' => $price,
                    'order_type' => 'sale',
                    'account_id' => $acc,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $count++;
            }
            return "$count orders";
        });

        // ---- Cash flow: expenses, transfers, staff advances/returns ----------
        $this->safe('expenses', function () use ($expenseCatIds, $poolIds, $locationIds, $paymentModeIds, $creator) {
            if (DB::table('expenses')->count() >= 20) { return 'skip: enough expenses'; }
            $cat = $expenseCatIds ?: [DB::table('expense_categories')->insertGetId(['name' => 'General', 'account_id' => 1, 'created_at' => now(), 'updated_at' => now()])];
            $made = \Database\Factories\ExpenseFactory::new()->count(30)->state(fn () => [
                'category_id' => Arr::random($cat),
                'paid_from_pool_id' => Arr::random($poolIds),
                'for_branch_id' => Arr::random($locationIds),
                'payment_method_id' => Arr::random($paymentModeIds),
                'created_by' => $creator,
                'status' => Arr::random(['pending', 'approved', 'approved', 'rejected']),
            ])->create();
            return count($made).' expenses';
        });

        $this->safe('cash_transfers', function () use ($poolIds, $creator) {
            if (DB::table('cash_transfers')->count() >= 10) { return 'skip: enough transfers'; }
            if (count($poolIds) < 2) { throw new \RuntimeException('need 2 pools'); }
            $made = \Database\Factories\CashTransferFactory::new()->count(15)->state(function () use ($poolIds, $creator) {
                $from = Arr::random($poolIds);
                $to = Arr::random(array_values(array_diff($poolIds, [$from])));
                return ['from_pool_id' => $from, 'to_pool_id' => $to, 'created_by' => $creator];
            })->create();
            return count($made).' transfers';
        });

        $this->safe('staff_advances', function () use ($staffIds, $poolIds, $creator) {
            if (DB::table('staff_advances')->count() >= 10) { return 'skip: enough advances'; }
            $made = \Database\Factories\StaffAdvanceFactory::new()->count(15)->state(fn () => [
                'user_id' => Arr::random($staffIds),
                'pool_id' => Arr::random($poolIds),
                'created_by' => $creator,
            ])->create();
            return count($made).' advances';
        });

        $this->safe('staff_returns', function () use ($staffIds, $poolIds, $creator) {
            if (DB::table('staff_returns')->count() >= 8) { return 'skip: enough returns'; }
            $made = \Database\Factories\StaffReturnFactory::new()->count(10)->state(fn () => [
                'user_id' => Arr::random($staffIds),
                'pool_id' => Arr::random($poolIds),
                'created_by' => $creator,
            ])->create();
            return count($made).' returns';
        });

        // ---- summary ----------------------------------------------------------
        $this->command->info('==== DemoDataSeeder summary ====');
        foreach ($this->report as $k => $v) {
            $this->command->info(sprintf('  %-24s %s', $k, $v));
        }
    }

    private function safe(string $label, \Closure $fn): void
    {
        try {
            $res = $fn();
            $this->report[$label] = 'OK: '.(is_string($res) ? $res : 'done');
        } catch (\Throwable $e) {
            $this->report[$label] = 'FAIL: '.$e->getMessage();
        }
    }

    private function hasColumn(string $table, string $col): bool
    {
        static $cache = [];
        if (! isset($cache[$table])) {
            $cache[$table] = DB::getSchemaBuilder()->getColumnListing($table);
        }
        return in_array($col, $cache[$table], true);
    }
}
