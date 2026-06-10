<?php

declare(strict_types=1);

namespace App\Services\Plan;

use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Helpers\Widgets\DiscountWidget;
use App\Helpers\Widgets\LocationsWidget;
use App\Models\BaseDiscountService;
use App\Models\BundleHasServices;
use App\Models\Bundles;
use App\Models\DiscountHasLocations;
use App\Models\Discounts;
use App\Models\GetDiscountService;
use App\Models\Locations;
use App\Models\Membership;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\PackageVouchers;
use App\Models\Patients;
use App\Models\ServiceBundle;
use App\Models\Services;
use App\Models\Settings;
use App\Models\User;
use App\Models\UserVouchers;
use Carbon\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class PlanDiscountService
{
    // ──────────────────────────────────────────────────
    //  Discount Info
    // ──────────────────────────────────────────────────

    /**
     * Get discount info for a service/bundle.
     *
     * @return array{success: bool, message: string, data?: array, status_code?: int}
     */
    public function getDiscountInfo(array $data): array
    {
        if (empty($data['discount_id'])) {
            return ['success' => false, 'message' => 'No Record Found', 'status_code' => 404];
        }

        $discountIsVoucher = false;
        $serviceId = $data['service_id'];
        $patientId = $data['patient_id'] ?? null;
        $serviceData = Bundles::find($serviceId);

        $discountId = $data['discount_id'];
        $discountData = Discounts::find($discountId);

        if ($discountData->slug == 'custom') {
            return [
                'success' => true,
                'message' => 'custom',
                'data' => ['custom_checked' => 1],
            ];
        }

        $discountType = '';
        $discountPrice = 0;
        $netAmount = $serviceData->price;

        if ($discountData->type == Config::get('constants.Fixed') && $discountData->discount_type != 'voucher') {
            $discountType = Config::get('constants.Fixed');
            // Cap a fixed discount at the service price (net 0 when the amount
            // exceeds the price), consistent with getServiceInfoForPlan and
            // the voucher min() cap.
            $discountPrice = min((float) $discountData->amount, (float) $serviceData->price);
            $netAmount = ($serviceData->price) - $discountPrice;
        } elseif ($discountData->type == Config::get('constants.Percentage') && $discountData->discount_type != 'voucher') {
            $discountType = Config::get('constants.Percentage');
            $discountPrice = $discountData->amount;
            $discountPriceCal = $serviceData->price * (($discountPrice) / 100);
            $netAmount = ($serviceData->price) - ($discountPriceCal);
        } elseif ($discountData->type == 'Configurable' && $discountData->discount_type != 'voucher') {
            $discountType = 'Configurable';
            $discountPrice = $discountData->amount;
            $discountPriceCal = $serviceData->price * (($discountPrice) / 100);
            $netAmount = ($serviceData->price) - ($discountPriceCal);
        } elseif ($discountData->discount_type == 'voucher') {
            $patientVoucher = UserVouchers::where('user_id', $patientId)->where('voucher_id', $discountId)->first();
            // Voucher rule (agreed with the user):
            //   - voucher_balance >= service_price → wipe service to 0 and
            //     consume `service_price` from the balance (NOT the full
            //     balance; otherwise the operator silently burns 20k from
            //     a 50k voucher on a 5k service).
            //   - voucher_balance <  service_price → consume the remaining
            //     balance, patient owes the residual.
            // The cap is `min(balance, service_price)` — the same formula
            // `consumeVoucherForBundle` already uses for the journal
            // entry. Surface it here so the discount_price stamped on
            // the saved bundle row matches what the ledger actually
            // burned, and so the SPA's reservation request doesn't ask
            // for more than is owed.
            // Balance==0 returns discount_price=0 (the dropdown filter
            // in PlanService::buildEligibleDiscounts blocks the depleted
            // voucher upstream, but keep the zero-balance branch here
            // as a safety net in case the catalog is stale).
            if ($patientVoucher && (float) $patientVoucher->amount > 0) {
                $discountType = Config::get('constants.Fixed');
                $discountPrice = min((float) $patientVoucher->amount, (float) $serviceData->price);
                $discountIsVoucher = true;
                $netAmount = ((float) $serviceData->price) - $discountPrice;
                if ($netAmount < 0) {
                    $netAmount = 0;
                }
            } else {
                $discountType = '';
                $discountPrice = 0;
                $discountIsVoucher = false;
                $netAmount = $serviceData->price;
            }
        }

        return [
            'success' => true,
            'message' => 'Record Found',
            'data' => [
                'discount_type' => $netAmount < 0 ? '' : $discountType,
                'discount_price' => $discountPrice,
                'net_amount' => $netAmount < 0 ? $serviceData->price : $netAmount,
                'custom_checked' => 0,
                'discount_is_voucher' => $discountIsVoucher,
            ],
        ];
    }

    /**
     * Get custom discount info for a service/bundle.
     *
     * @return array{success: bool, message: string, data?: array, status_code?: int}|false
     */
    public function getCustomDiscountInfo(array $data): array|false
    {
        $status = true;
        $serviceId = $data['service_id'];
        $serviceData = Bundles::find($serviceId);
        $discountId = $data['discount_id'];
        $discountData = Discounts::find($discountId);
        $discountValue = $data['discount_value'] ?? 0;
        $discountTypeInput = $data['discount_type'] ?? null;
        $patientId = $data['patient_id'] ?? null;

        if ($discountData->slug == 'custom') {
            // discount_id stays as is
        } else {
            if ($discountData->discount_type == 'voucher') {
                $voucherRecord = UserVouchers::where('user_id', $patientId)->where('voucher_id', $discountId)->first();
                $discountValue = $voucherRecord ? $voucherRecord->amount : 0;
            } else {
                $discountValue = $discountData->amount;
            }
        }

        $discountPrice = 0;
        $netAmount = $serviceData->price;

        if ($discountData->type == 'Fixed' && $discountData->discount_type != 'voucher') {
            if ($discountTypeInput == Config::get('constants.Fixed')) {
                if ($discountValue > $discountData->amount || $discountValue > $serviceData->price) {
                    return false;
                }
                $discountPrice = $discountValue;
                $netAmount = ($serviceData->price) - ($discountPrice);
            } else {
                $discountPrice = $discountValue;
                $discountPriceCal = ($discountData->amount / $serviceData->price) * 100;
                if ($discountValue > $discountPriceCal) {
                    $status = false;
                }
                $amountAfterPer = ($discountValue / 100) * $serviceData->price;
                $netAmount = $serviceData->price - $amountAfterPer;
            }
        } elseif ($discountData->type == 'Fixed' && $discountData->discount_type == 'voucher') {
            $voucherRecord = UserVouchers::where('user_id', $patientId)->where('voucher_id', $discountId)->first();
            // Voucher cap: min(balance, service_price). See identical
            // block in `getDiscountInfo` for rationale.
            if ($voucherRecord && (float) $voucherRecord->amount > 0) {
                $discountPrice = min((float) $voucherRecord->amount, (float) $serviceData->price);
                $netAmount = ((float) $serviceData->price) - $discountPrice;
                if ($netAmount < 0) {
                    $netAmount = 0;
                }
            } else {
                $discountPrice = 0;
                $netAmount = $serviceData->price;
            }
        } elseif ($discountData->type == 'Percentage' && $discountData->discount_type == 'voucher') {
            $voucherRecord = UserVouchers::where('user_id', $patientId)->where('voucher_id', $discountId)->first();
            // Percentage-voucher cap: compute the equivalent fixed
            // discount (`balance/100 * service_price`), then cap at the
            // service price so a 200% voucher (legacy data) doesn't
            // wipe more than the service is worth.
            if ($voucherRecord && (float) $voucherRecord->amount > 0) {
                $discountPriceInPercentage = ((float) $voucherRecord->amount / 100) * (float) $serviceData->price;
                $discountPrice = min($discountPriceInPercentage, (float) $serviceData->price);
                $netAmount = ((float) $serviceData->price) - $discountPrice;
                if ($netAmount < 0) {
                    $netAmount = 0;
                }
            } else {
                $discountPrice = 0;
                $netAmount = $serviceData->price;
            }
        } else {
            if ($discountTypeInput == Config::get('constants.Fixed')) {
                $discountPrice = $discountValue;
                $discountPriceInPercentage = ($discountPrice / $serviceData->price) * 100;
                if ($discountData->discount_type != 'voucher' && $discountPriceInPercentage > $discountData->amount) {
                    return false;
                }
                $netAmount = ($serviceData->price) - ($discountValue);
            } else {
                if ($discountData->discount_type != 'voucher' && $discountValue > $discountData->amount) {
                    return false;
                }
                $discountPrice = $discountValue;
                $discountPriceInPercentage = ($discountValue / 100) * $serviceData->price;
                $netAmount = ($serviceData->price) - ($discountPriceInPercentage);
            }
        }

        if ($status) {
            return [
                'success' => true,
                'message' => 'Net Amount',
                'data' => ['net_amount' => $netAmount < 0 ? 0 : $netAmount],
            ];
        }

        return ['success' => false, 'message' => 'Net Amount', 'status_code' => 404];
    }

    /**
     * Delete configurable package service by base_service_id.
     *
     * @return array{success: bool, message: string, data?: array, status_code?: int}
     */
    public function deleteConfigurablePackageService(array $data): array
    {
        $id = $data['id'];

        // Block if ANY service in the config group is consumed
        $hasConsumedInGroup = PackageService::where([
            ['base_service_id', '=', $id],
            ['is_consumed', '=', '1'],
        ])->exists();

        if ($hasConsumedInGroup) {
            return ['success' => false, 'message' => 'Cannot delete. A service in this configurable discount group has been consumed.', 'status_code' => 404, 'data' => ['del' => 1]];
        }

        $packageService = PackageBundles::where('base_service_id', $id)->first();

        $packageTotal = $data['package_total'] ?? '';
        if ($packageTotal == '') {
            $packageTotal = 0;
        }
        $packageTotal = str_replace(',', '', (string) $packageTotal);

        $total = $packageTotal - $packageService->tax_including_price;

        PackageService::where('base_service_id', '=', $id)->delete();
        PackageBundles::where('base_service_id', $id)->forcedelete();

        $updateStatus = $data['update_status'] ?? 0;
        if ($updateStatus == 1) {
            if ($packageService->package_id) {
                Packages::where('id', $packageService->package_id)->update(['total_price' => $total]);
            }
        }

        return [
            'success' => true,
            'message' => 'Record found',
            'data' => [
                'total' => $total,
                'id' => $id,
            ],
        ];
    }

    /**
     * Delete exclusive package service by random_id.
     *
     * @return array{success: bool, status: bool}
     */
    public function deleteExclusiveService(array $data): array
    {
        if (! empty($data['random_id'])) {
            PackageService::where('random_id', '=', $data['random_id'])->forcedelete();
            PackageBundles::where('random_id', '=', $data['random_id'])->forcedelete();

            return ['success' => true, 'status' => true];
        }

        return ['success' => false, 'status' => false];
    }

    /**
     * Get bundle services (price) for zero-discount scenarios.
     *
     * @return array{success: bool, message: string, data?: array, status_code?: int}
     */
    public function getBundleServices(int $bundleId): array
    {
        $serviceData = Bundles::where('id', '=', $bundleId)->first();

        if ($serviceData) {
            return [
                'success' => true,
                'message' => 'Records found',
                'data' => ['net_amount' => $serviceData->price],
            ];
        }

        return ['success' => false, 'message' => 'No record found', 'status_code' => 404];
    }

    /**
     * Get active bundles for the authenticated user's account.
     *
     * @param  int  $locationId  Location ID (currently unused but kept for future filtering)
     * @return array Contains 'bundles' key with collection of bundles
     */
    public function getBundlesByLocation(int $locationId): array
    {
        $accountId = Auth::user()->account_id;

        // Existing packages (bundles table) — active and within date range
        $packages = Bundles::where('account_id', $accountId)
            ->where('active', 1)
            ->where('type', '!=', 'single')
            ->where(fn ($q) => $q->whereNull('end')->orWhereDate('end', '>=', now()))
            ->select('id', 'name', 'price', 'services_price')
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'price' => $b->price,
                'regular_price' => (float) ($b->services_price ?: $b->price),
                'source_type' => 'bundle',
            ]);

        // New service bundles — active, no date restriction
        $serviceBundles = ServiceBundle::where('account_id', $accountId)
            ->where('active', 1)
            ->with('service:id,name,price')
            ->get()
            ->map(function ($sb) {
                $unitPrice = (float) ($sb->service->price ?? 0);
                $regularPrice = $unitPrice * $sb->sessions;
                $savings = $regularPrice - (float) $sb->price;

                return [
                    'id' => $sb->id,
                    'name' => $sb->sessions.'x '.($sb->service->name ?? 'Unknown'),
                    'price' => $sb->price,
                    'regular_price' => $regularPrice,
                    'source_type' => 'service_bundle',
                ];
            });

        $allBundles = $packages->merge($serviceBundles)->values();

        if ($allBundles->isEmpty()) {
            return ['bundles' => []];
        }

        return ['bundles' => $allBundles];
    }

    /**
     * Reset voucher package bundles and restore voucher amounts.
     */
    public function resetVoucherPackageBundles(array $data): array
    {
        $servicesIds = $data['package_bundles'];
        $randomId = $data['random_id'];

        $vouchers = PackageVouchers::where('package_random_id', $randomId)
            ->whereIn('service_id', $servicesIds)
            ->get();

        $voucherAmounts = [];
        foreach ($vouchers as $voucher) {
            $key = $voucher->user_id.'_'.$voucher->voucher_id;
            $voucherAmounts[$key]['user_id'] = $voucher->user_id;
            $voucherAmounts[$key]['voucher_id'] = $voucher->voucher_id;
            $voucherAmounts[$key]['amount'] = ($voucherAmounts[$key]['amount'] ?? 0) + $voucher->amount;
        }

        // Update user vouchers and log activity
        foreach ($voucherAmounts as $item) {
            UserVouchers::where('user_id', $item['user_id'])
                ->where('voucher_id', $item['voucher_id'])
                ->increment('amount', $item['amount']);

            // ActivityLogger::logVoucherRefunded() is typed to
            // App\Models\Patients (extends BaseModel, NOT User). Fetching
            // via User::find() returns a User instance and TypeErrors at
            // the logger call — same bug pattern as UserVoucherService and
            // PlanService, discovered via VoucherRedemptionTest.
            $patient = Patients::find($item['user_id']);
            $voucherModel = Discounts::find($item['voucher_id']);
            if ($patient && $voucherModel) {
                ActivityLogger::logVoucherRefunded($item['amount'], $patient, $voucherModel);
            }
        }

        // Delete package vouchers
        PackageVouchers::where('package_random_id', $randomId)
            ->whereIn('service_id', $servicesIds)
            ->delete();

        return ['success' => true, 'message' => 'Vouchers reset successfully'];
    }

    /**
     * Get service info for packages (bundle-based).
     * Extracted from PackagesController::getserviceinfo().
     */
    public function getServiceInfoForPackage(array $data): array
    {
        // Service bundles have fixed pricing — no discount lookup needed
        if (($data['source_type'] ?? '') === 'service_bundle') {
            $serviceBundle = ServiceBundle::with('service:id,name,price')->find($data['bundle_id'] ?? null);

            if (! $serviceBundle) {
                return [
                    'success' => false,
                    'message' => 'Service bundle not found.',
                    'status_code' => 404,
                    'data' => ['net_amount' => 0],
                ];
            }

            $unitPrice = (float) ($serviceBundle->service->price ?? 0);
            $regularPrice = $unitPrice * $serviceBundle->sessions;

            return [
                'success' => true,
                'message' => 'Records found.',
                'data' => [
                    'net_amount' => $serviceBundle->price,
                    'regular_price' => $regularPrice,
                    'discounts' => [],
                    'checked_custom' => '0',
                ],
            ];
        }

        $discounts = SupportCollection::make();
        $today = Carbon::now()->toDateString();

        $userRoleIds = Auth::user()->user_roles()->pluck('role_id')->toArray();
        $isSuperAdmin = Auth::user()->hasRole('Super-Admin');

        $patientActiveMembership = null;
        $patientMembershipTypeId = null;

        if ($data['patient_id'] ?? null) {
            $patientActiveMembership = Membership::where('patient_id', $data['patient_id'])
                ->where('active', 1)
                ->whereDate('end_date', '>=', $today)
                ->orderBy('assigned_at', 'desc')
                ->first();

            if ($patientActiveMembership) {
                $patientMembershipTypeId = $patientActiveMembership->membership_type_id;
            }
        }

        $bundle = Bundles::find($data['bundle_id'] ?? null);

        if ($bundle && $bundle->type == 'single') {

            $bundleService = BundleHasServices::where([
                'bundle_id' => $bundle->id,
            ])->first();

            $service_id = $bundleService->service_id;

            $location_id = $data['location_id'];

            $discountIds = DiscountWidget::loadPlanDsicountByLocationService($location_id, $service_id, Auth::user()->account_id);

            $generalDiscountsQuery = Discounts::whereIn('id', $discountIds)
                ->where('discount_type', '!=', 'voucher')
                ->where('active', '=', '1')
                ->whereDate('start', '<=', $today)
                ->whereDate('end', '>=', $today);

            if (! $isSuperAdmin) {
                $generalDiscountsQuery->whereHas('roles', function ($query) use ($userRoleIds) {
                    $query->whereIn('role_id', $userRoleIds);
                });
            }

            if ($patientActiveMembership && $patientMembershipTypeId) {
                $generalDiscountsQuery->where(function ($query) use ($patientMembershipTypeId) {
                    $query->where('customer_type_id', $patientMembershipTypeId)
                        ->orWhereNull('customer_type_id');
                });
            } else {
                $generalDiscountsQuery->whereNull('customer_type_id');
            }

            $generalDiscounts = $generalDiscountsQuery->get();

            $voucherDiscounts = SupportCollection::make();
            $checkUserVouchers = UserVouchers::where('user_id', $data['patient_id'])
                ->pluck('voucher_id')
                ->toArray();

            if ($checkUserVouchers) {
                $voucherDiscountsQuery = Discounts::whereIn('id', $discountIds)
                    ->whereIn('id', $checkUserVouchers)
                    ->where('discount_type', '=', 'voucher');

                $voucherDiscounts = $voucherDiscountsQuery->get();
            }

            $discounts = $generalDiscounts->merge($voucherDiscounts);

        } else {

            if ($bundle && $bundle->apply_discount == '1') {
                $bundleServices = BundleHasServices::where([
                    'bundle_id' => $bundle->id,
                ])->get();
                $discountIds = [];
                foreach ($bundleServices as $bundleService) {
                    $service_id = $bundleService->service_id;
                    $location_id = $data['location_id'];
                    $discountIds[] = DiscountWidget::loadPlanDsicountByLocationService($location_id, $service_id, Auth::user()->account_id);
                }
                $uniq_array = [];
                foreach ($discountIds as $discountId) {
                    foreach ($discountId as $singledata) {
                        if (! in_array($singledata, $uniq_array, true)) {
                            $uniq_array[] = $singledata;
                        }
                    }
                }
                $generalDiscountsQuery = Discounts::whereIn('id', $uniq_array)
                    ->where('discount_type', '!=', 'voucher')
                    ->where('active', '=', '1')
                    ->whereDate('start', '<=', $today)
                    ->whereDate('end', '>=', $today);

                if (! $isSuperAdmin) {
                    $generalDiscountsQuery->whereHas('roles', function ($query) use ($userRoleIds) {
                        $query->whereIn('role_id', $userRoleIds);
                    });
                }

                if ($patientActiveMembership && ! empty($membershipDiscountIds)) {
                    $generalDiscountsQuery->where(function ($query) use ($membershipDiscountIds, $allMembershipLinkedDiscountIds) {
                        $query->whereIn('id', $membershipDiscountIds)
                            ->orWhereNotIn('id', $allMembershipLinkedDiscountIds);
                    });
                } elseif (! empty($allMembershipLinkedDiscountIds)) {
                    $generalDiscountsQuery->whereNotIn('id', $allMembershipLinkedDiscountIds);
                }

                $generalDiscounts = $generalDiscountsQuery->get();

                $voucherDiscounts = SupportCollection::make();
                $checkUserVouchers = UserVouchers::where('user_id', $data['patient_id'])
                    ->pluck('voucher_id')
                    ->toArray();

                if ($checkUserVouchers) {
                    $voucherDiscountsQuery = Discounts::whereIn('id', $uniq_array)
                        ->whereIn('id', $checkUserVouchers)
                        ->where('discount_type', '=', 'voucher');

                    $voucherDiscounts = $voucherDiscountsQuery->get();
                }

                $discounts = $generalDiscounts->merge($voucherDiscounts);

            }
        }

        $temp_discounts = [];

        foreach ($discounts as $key => $discount) {

            if ($discount->slug == 'birthday') {
                $pre_days = $discount->pre_days;
                $post_days = $discount->post_days;

                $today_1 = Carbon::today();
                $today_2 = Carbon::today();
                $today_3 = Carbon::today();

                $predate = $today_1->subDay($pre_days)->format('Y-m-d');
                $postdate = $today_2->addDay($post_days)->format('Y-m-d');

                $patient_info = User::find($data['patient_id']);

                if ($patient_info->dob) {

                    $patientbirthday = Carbon::parse($patient_info->dob)->format($today_3->year.'-'.'m-d');

                    if (($patientbirthday >= $predate) && ($patientbirthday <= $postdate)) {
                    } else {
                        $discounts->forget($key);
                    }
                } else {
                    $discounts->forget($key);
                }
            }
        }

        $Discount_array = [];

        if (! empty($discounts)) {
            $service_data = Bundles::where('id', '=', $data['bundle_id'])->first();
            if ($service_data) {
                foreach ($discounts as $discount) {
                    if ($discount->slug != 'custom') {
                        if ($discount->type == Config::get('constants.Fixed')) {

                            $discount_type = $discount->type;
                            $discount_price = $discount->amount;
                            $net_amount = ($service_data->price) - ($discount_price);
                            $Discount_array[$discount->id] = [
                                'id' => $discount->id,
                                'discount_type' => $discount_type,
                                'discount_price' => $discount_price,
                                'net_amount' => $net_amount,
                            ];
                        } else {
                            $discount_type = $discount->type;
                            $discount_price = $discount->amount;
                            $discount_price_cal = $service_data->price * (($discount_price) / 100);
                            $net_amount = ($service_data->price) - ($discount_price_cal);
                            $Discount_array[$discount->id] = [
                                'id' => $discount->id,
                                'discount_type' => $discount_type,
                                'discount_price' => $discount_price,
                                'net_amount' => $net_amount,
                            ];
                        }
                    }
                }

                $select_discount = [];
                $lowest = false;
                if (! empty($Discount_array)) {
                    foreach ($Discount_array as $value) {
                        if ($lowest === false || $value['net_amount'] < $lowest) {
                            $lowest = $value['net_amount'];
                            $select_discount = $value;
                        }
                    }
                    $discounts = $discounts->toArray();
                    $service_data = Bundles::where('id', '=', $data['bundle_id'])->first();

                    $net_amount = $service_data->price;
                    if ($service_data->type == 'single') {
                        $bundleService = BundleHasServices::where('bundle_id', $service_data->id)->first();
                        if ($bundleService) {
                            $actualService = Services::find($bundleService->service_id);
                            if ($actualService) {
                                $net_amount = $actualService->price;
                            }
                        }
                    }

                    return [
                        'success' => true,
                        'message' => 'Records found.',
                        'data' => [
                            'discounts' => $discounts,
                            'checked_custom' => '0',
                            'dis_price_info' => $select_discount,
                            'net_amount' => $net_amount,
                            'regular_price' => (float) ($service_data->services_price ?: $service_data->price),
                        ],
                    ];
                } else {
                    $discounts = $discounts->toArray();
                    $service_data = Bundles::where('id', '=', $data['bundle_id'])->first();

                    $net_amount = $service_data->price;
                    if ($service_data->type == 'single') {
                        $bundleService = BundleHasServices::where('bundle_id', $service_data->id)->first();
                        if ($bundleService) {
                            $actualService = Services::find($bundleService->service_id);
                            if ($actualService) {
                                $net_amount = $actualService->price;
                            }
                        }
                    }

                    return [
                        'success' => true,
                        'message' => 'Records found.',
                        'data' => [
                            'discounts' => $discounts,
                            'checked_custom' => '1',
                            'net_amount' => $net_amount,
                            'regular_price' => (float) ($service_data->services_price ?: $service_data->price),
                        ],
                    ];
                }
            }

        }

        $net_amount = isset($bundle) ? $bundle->price : 0;
        $regularPrice = isset($bundle) ? (float) ($bundle->services_price ?: $bundle->price) : 0;
        if ($bundle && $bundle->type == 'single') {
            $bundleService = BundleHasServices::where('bundle_id', $bundle->id)->first();
            if ($bundleService) {
                $actualService = Services::find($bundleService->service_id);
                if ($actualService) {
                    $net_amount = $actualService->price;
                    $regularPrice = $actualService->price;
                }
            }
        }

        return [
            'success' => false,
            'message' => 'Records found.',
            'status_code' => 404,
            'data' => [
                'net_amount' => $net_amount,
                'regular_price' => $regularPrice,
            ],
        ];
    }

    /**
     * Get service info for simple plans (non-bundle).
     * Extracted from PackagesController::getserviceinfo_for_plan().
     */
    public function getServiceInfoForPlan(array $data): array
    {
        $discounts = SupportCollection::make();
        $today = Carbon::now()->toDateString();
        $location_information = Locations::find($data['location_id']);

        $userRoleIds = Auth::user()->user_roles()->pluck('role_id')->toArray();
        $isSuperAdmin = Auth::user()->hasRole('Super-Admin');

        $patientActiveMembership = null;
        $patientMembershipTypeId = null;

        if ($data['patient_id'] ?? null) {
            $patientActiveMembership = Membership::where('patient_id', $data['patient_id'])
                ->where('active', 1)
                ->whereDate('end_date', '>=', $today)
                ->orderBy('assigned_at', 'desc')
                ->first();

            if ($patientActiveMembership) {
                $patientMembershipTypeId = $patientActiveMembership->membership_type_id;
            }
        }

        $service = Services::find($data['service_id'] ?? null);

        if (! $service) {
            return [
                'success' => false,
                'message' => 'Service not found.',
                'status_code' => 500,
            ];
        }

        $service_id = $service->id;
        $location_id = $data['location_id'];

        $allocations = DiscountWidget::loadPlanDiscountAllocationsByLocationService($location_id, $service_id, Auth::user()->account_id);
        $discountIds = array_keys($allocations);

        $generalDiscountsQuery = Discounts::whereIn('id', $discountIds)
            ->where('discount_type', '!=', 'voucher')
            ->where('type', '!=', 'Configurable')
            ->where('active', '=', '1')
            ->whereDate('start', '<=', $today)
            ->whereDate('end', '>=', $today);

        if (! $isSuperAdmin) {
            $generalDiscountsQuery->whereHas('roles', function ($query) use ($userRoleIds) {
                $query->whereIn('role_id', $userRoleIds);
            });
        }

        if ($patientActiveMembership && $patientMembershipTypeId) {
            $generalDiscountsQuery->where(function ($query) use ($patientMembershipTypeId) {
                $query->where('customer_type_id', $patientMembershipTypeId)
                    ->orWhereNull('customer_type_id');
            });
        } else {
            $generalDiscountsQuery->whereNull('customer_type_id');
        }

        $generalDiscounts = $generalDiscountsQuery->get();

        $voucherDiscounts = SupportCollection::make();
        // patient_id can legitimately be absent on a pre-patient-pick
        // sanity call (legacy admin sometimes hit this for the lookup
        // payload). Coalesce so the missing-key warning doesn't blow the
        // request up — the rest of the function gates voucher logic on
        // the resulting collection being non-empty anyway.
        $patientIdForVouchers = $data['patient_id'] ?? null;
        $checkUserVouchers = $patientIdForVouchers
            ? UserVouchers::where('user_id', $patientIdForVouchers)
                ->pluck('voucher_id')
                ->toArray()
            : [];

        if ($checkUserVouchers) {
            $voucherDiscountsQuery = Discounts::whereIn('id', $discountIds)
                ->whereIn('id', $checkUserVouchers)
                ->where('discount_type', '=', 'voucher');

            $voucherDiscounts = $voucherDiscountsQuery->get();
        }

        $discounts = $generalDiscounts->merge($voucherDiscounts);

        foreach ($discounts as $key => $discount) {
            if ($discount->slug == 'birthday') {
                $pre_days = $discount->pre_days;
                $post_days = $discount->post_days;

                $today_1 = Carbon::today();
                $today_2 = Carbon::today();
                $today_3 = Carbon::today();

                $predate = $today_1->subDay($pre_days)->format('Y-m-d');
                $postdate = $today_2->addDay($post_days)->format('Y-m-d');

                $patient_info = User::find($data['patient_id']);

                if ($patient_info->dob) {
                    $patientbirthday = Carbon::parse($patient_info->dob)->format($today_3->year.'-'.'m-d');

                    if (($patientbirthday >= $predate) && ($patientbirthday <= $postdate)) {
                        // Birthday is valid
                    } else {
                        $discounts->forget($key);
                    }
                } else {
                    $discounts->forget($key);
                }
            }
        }

        // Also load configurable discounts allocated to this location (slug='configurable')
        $configurableDiscountIds = DiscountHasLocations::where('location_id', $location_id)
            ->where('slug', 'configurable')
            ->pluck('discount_id')
            ->toArray();

        $configurableDiscounts = SupportCollection::make();
        if (! empty($configurableDiscountIds)) {
            $configurableQuery = Discounts::whereIn('id', $configurableDiscountIds)
                ->where('type', 'Configurable')
                ->where('active', '=', '1')
                ->whereDate('start', '<=', $today)
                ->whereDate('end', '>=', $today);

            if (! $isSuperAdmin) {
                $configurableQuery->whereHas('roles', function ($query) use ($userRoleIds) {
                    $query->whereIn('role_id', $userRoleIds);
                });
            }

            if ($patientActiveMembership && $patientMembershipTypeId) {
                $configurableQuery->where(function ($query) use ($patientMembershipTypeId) {
                    $query->where('customer_type_id', $patientMembershipTypeId)
                        ->orWhereNull('customer_type_id');
                });
            } else {
                $configurableQuery->whereNull('customer_type_id');
            }

            $searchServices = Services::where('account_id', Auth::user()->account_id)
                ->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id')->toArray();
            $serviceParentIds = LocationsWidget::findServiceParents($service_id, $searchServices);
            $serviceParentIds = array_merge($serviceParentIds ?? [], [$service_id]);

            // Note: filter must remain post-query because the callback runs additional DB lookups
            // (BaseDiscountService queries) per discount that cannot be expressed as a single Eloquent where().
            $configurableDiscounts = $configurableQuery->get()->filter(function ($discount) use ($service_id, $serviceParentIds) {
                $directMatch = BaseDiscountService::where('discount_id', $discount->id)
                    ->where('service_id', $service_id)
                    ->where(function ($q) {
                        $q->where('is_category', 0)->orWhereNull('is_category');
                    })
                    ->first();
                if ($directMatch) {
                    return true;
                }

                $categoryMatch = BaseDiscountService::where('discount_id', $discount->id)
                    ->where('is_category', 1)
                    ->whereIn('service_id', $serviceParentIds)
                    ->first();

                return $categoryMatch !== null;
            });
        }

        $discounts = $discounts->merge($configurableDiscounts);

        $Discount_array = [];

        if (! empty($discounts)) {
            foreach ($discounts as $discount) {
                if ($discount->type === 'Configurable') {
                    $Discount_array[$discount->id] = [
                        'id' => $discount->id,
                        'discount_type' => 'Configurable',
                        'discount_price' => 0,
                        'net_amount' => $service->price,
                        'slug' => 'configurable',
                    ];

                    continue;
                }

                $allocation = $allocations[$discount->id] ?? null;

                if (! $allocation || ! $allocation->type || $allocation->amount === null) {
                    continue;
                }

                if ($allocation->slug == 'custom') {
                    continue;
                }

                $effective_type = $allocation->type;
                $effective_amount = $allocation->amount;

                if ($effective_type == Config::get('constants.Fixed')) {
                    $discount_type = $effective_type;
                    $discount_price = $effective_amount;
                    $net_amount = ($service->price) - ($discount_price);
                    $Discount_array[$discount->id] = [
                        'id' => $discount->id,
                        'discount_type' => $discount_type,
                        'discount_price' => $discount_price,
                        'net_amount' => $net_amount,
                        'slug' => $allocation->slug,
                    ];
                } else {
                    $discount_type = $effective_type;
                    $discount_price = $effective_amount;
                    $discount_price_cal = $service->price * (($discount_price) / 100);
                    $net_amount = ($service->price) - ($discount_price_cal);
                    $Discount_array[$discount->id] = [
                        'id' => $discount->id,
                        'discount_type' => $discount_type,
                        'discount_price' => $discount_price,
                        'net_amount' => $net_amount,
                        'slug' => $allocation->slug,
                    ];
                }
            }

            $select_discount = [];
            $lowest = false;
            if (! empty($Discount_array)) {
                foreach ($Discount_array as $value) {
                    if ($value['discount_type'] === 'Configurable') {
                        continue;
                    }
                    if ($lowest === false || $value['net_amount'] < $lowest) {
                        $lowest = $value['net_amount'];
                        $select_discount = $value;
                    }
                }
                $discounts = $discounts->toArray();

                return [
                    'success' => true,
                    'message' => 'Records found.',
                    'data' => [
                        'discounts' => $discounts,
                        'checked_custom' => '0',
                        'dis_price_info' => $select_discount,
                        'net_amount' => $service->price,
                        'tax_treatment_type_id' => $service->tax_treatment_type_id,
                        'location_tax_percentage' => $location_information->tax_percentage ?? 0,
                        'service_name' => $service->name,
                    ],
                ];
            } else {
                $discounts = $discounts->toArray();

                return [
                    'success' => true,
                    'message' => 'Records found.',
                    'data' => [
                        'discounts' => $discounts,
                        'checked_custom' => '1',
                        'net_amount' => $service->price,
                        'tax_treatment_type_id' => $service->tax_treatment_type_id,
                        'location_tax_percentage' => $location_information->tax_percentage ?? 0,
                        'service_name' => $service->name,
                    ],
                ];
            }
        }

        $location_information = Locations::find($data['location_id']);

        return [
            'success' => false,
            'message' => 'Records found.',
            'status_code' => 404,
            'data' => [
                'net_amount' => $service->price,
                'tax_treatment_type_id' => $service->tax_treatment_type_id,
                'location_tax_percentage' => $location_information->tax_percentage ?? 0,
                'service_name' => $service->name,
            ],
        ];
    }

    /**
     * Get discount info for simple plans (non-bundle).
     * Extracted from PackagesController::getdiscountinfo_for_plan().
     */
    public function getDiscountInfoForPlan(array $data): array
    {
        if ($data['discount_id'] ?? null) {
            $discount_is_voucher = false;
            $service_id = $data['service_id'];
            $patient_id = $data['patient_id'];
            $location_id = $data['location_id'];
            $service_data = Services::find($service_id);

            if (! $service_data) {
                return [
                    'success' => false,
                    'message' => 'Service not found',
                    'status_code' => 500,
                ];
            }

            $discount_id = $data['discount_id'];
            $discount_data = Discounts::find($discount_id);

            $allocations = DiscountWidget::loadPlanDiscountAllocationsByLocationService($location_id, $service_id, Auth::user()->account_id);
            $allocation = $allocations[$discount_id] ?? null;

            $effective_type = $allocation?->type;
            $effective_amount = $allocation?->amount;
            $allocation_slug = $allocation?->slug ?? 'default';

            if ($allocation_slug == 'custom') {
                $max_percentage = $effective_type == 'Percentage' ? $effective_amount : 100;
                $max_fixed_amount = $service_data->price * ($effective_amount / 100);

                return [
                    'success' => true,
                    'message' => 'custom',
                    'data' => [
                        'custom_checked' => 1,
                        'allocation_type' => $effective_type,
                        'allocation_amount' => $effective_amount,
                        'service_price' => $service_data->price,
                        'max_percentage' => $max_percentage,
                        'max_fixed_amount' => round($max_fixed_amount, 2),
                    ],
                ];
            } else {
                // Handle configurable discounts - return preview of all services to be added
                if ($discount_data->type === 'Configurable') {
                    $baseServices = BaseDiscountService::where('discount_id', $discount_id)->get();
                    $getServices = GetDiscountService::where('discount_id', $discount_id)->get();

                    $isCategoryMode = $baseServices->isNotEmpty() && $baseServices->first()->is_category == 1;

                    $preview_rows = [];
                    $loc = Locations::find($location_id);
                    $locTaxPct = $loc->tax_percentage ?? 0;

                    if ($isCategoryMode) {
                        $sessionCount = (int) ($baseServices->first()->sessions ?? $baseServices->count());
                        for ($i = 0; $i < $sessionCount; $i++) {
                            $preview_rows[] = [
                                'service_id' => $service_data->id,
                                'service_name' => $service_data->name,
                                'service_price' => $service_data->price,
                                'net_amount' => $service_data->price,
                                'discount_type' => '-',
                                'discount_price' => 0,
                                'row_type' => 'buy',
                                'tax_treatment_type_id' => $service_data->tax_treatment_type_id ?? null,
                                'location_tax_percentage' => $locTaxPct,
                            ];
                        }
                    } else {
                        $serviceCache = [];
                        foreach ($baseServices as $bs) {
                            if (! isset($serviceCache[$bs->service_id])) {
                                $serviceCache[$bs->service_id] = Services::find($bs->service_id);
                            }
                            $svc = $serviceCache[$bs->service_id];
                            if (! $svc) {
                                continue;
                            }
                            $preview_rows[] = [
                                'service_id' => $svc->id,
                                'service_name' => $svc->name,
                                'service_price' => $svc->price,
                                'net_amount' => $svc->price,
                                'discount_type' => '-',
                                'discount_price' => 0,
                                'row_type' => 'buy',
                                'tax_treatment_type_id' => $svc->tax_treatment_type_id ?? null,
                                'location_tax_percentage' => $locTaxPct,
                            ];
                        }
                    }

                    if ($isCategoryMode) {
                        $getGroups = [];
                        foreach ($getServices as $gs) {
                            $key = $gs->discount_type.'_'.$gs->discount_amount.'_'.($gs->same_service ? 'same' : $gs->service_id);
                            if (! isset($getGroups[$key])) {
                                $getGroups[$key] = ['record' => $gs, 'count' => 0];
                            }
                            $getGroups[$key]['count']++;
                        }
                        foreach ($getGroups as $group) {
                            $gs = $group['record'];
                            $svc = $gs->same_service ? $service_data : Services::find($gs->service_id);
                            if (! $svc) {
                                continue;
                            }
                            for ($i = 0; $i < $group['count']; $i++) {
                                [$disc_price, $net, $disc_label] = self::resolveGetDiscountAmounts($svc->price, $gs);
                                $preview_rows[] = [
                                    'service_id' => $svc->id,
                                    'service_name' => $svc->name,
                                    'service_price' => $svc->price,
                                    'net_amount' => $net,
                                    'discount_type' => $disc_label,
                                    'discount_price' => $disc_price,
                                    'row_type' => 'get',
                                    'gs_discount_type' => $gs->discount_type,
                                    'gs_discount_amount' => $gs->discount_amount,
                                    'tax_treatment_type_id' => $svc->tax_treatment_type_id ?? null,
                                    'location_tax_percentage' => $locTaxPct,
                                ];
                            }
                        }
                    } else {
                        foreach ($getServices as $gs) {
                            $svc = $gs->same_service ? $service_data : Services::find($gs->service_id);
                            if (! $svc) {
                                continue;
                            }
                            [$disc_price, $net, $disc_label] = self::resolveGetDiscountAmounts($svc->price, $gs);
                            $preview_rows[] = [
                                'service_id' => $svc->id,
                                'service_name' => $svc->name,
                                'service_price' => $svc->price,
                                'net_amount' => $net,
                                'discount_type' => $disc_label,
                                'discount_price' => $disc_price,
                                'row_type' => 'get',
                                'gs_discount_type' => $gs->discount_type,
                                'gs_discount_amount' => $gs->discount_amount,
                                'tax_treatment_type_id' => $svc->tax_treatment_type_id ?? null,
                                'location_tax_percentage' => $locTaxPct,
                            ];
                        }
                    }

                    $total_net = array_sum(array_column($preview_rows, 'net_amount'));

                    return [
                        'success' => true,
                        'message' => 'Configurable',
                        'data' => [
                            'is_configurable' => true,
                            'discount_type' => 'Configurable',
                            'discount_price' => 0,
                            'net_amount' => $service_data->price,
                            'total_net_amount' => $total_net,
                            'custom_checked' => 0,
                            'slug' => 'configurable',
                            'preview_rows' => $preview_rows,
                            'service_name' => $service_data->name,
                            'tax_treatment_type_id' => $service_data->tax_treatment_type_id,
                            'location_tax_percentage' => $locTaxPct,
                        ],
                    ];
                }

                // Initialize default values
                $discount_type = '';
                $discount_price = 0;
                $net_amount = $service_data->price;

                if ($effective_type == Config::get('constants.Fixed') && $discount_data->discount_type != 'voucher') {
                    $discount_type = Config::get('constants.Fixed');
                    // Cap a fixed discount at the service price so an amount
                    // larger than the price makes the service free (net 0)
                    // rather than dropping the discount. Mirrors the voucher
                    // min() cap used elsewhere in this service.
                    $discount_price = min((float) $effective_amount, (float) $service_data->price);
                    $net_amount = ($service_data->price) - $discount_price;
                } elseif ($effective_type == Config::get('constants.Percentage') && $discount_data->discount_type != 'voucher') {
                    $discount_type = Config::get('constants.Percentage');
                    $discount_price = $effective_amount;
                    $discount_price_cal = $service_data->price * (($discount_price) / 100);
                    $net_amount = ($service_data->price) - ($discount_price_cal);
                } elseif ($discount_data->discount_type == 'voucher') {
                    $patientVoucher = UserVouchers::where('user_id', $patient_id)->where('voucher_id', $discount_id)->first();
                    // Voucher cap: `min(balance, service_price)` — see
                    // the equivalent block in `getDiscountInfo` for the
                    // full rationale. The previous code stamped the
                    // un-capped voucher balance as `discount_price` and
                    // only clamped `net_amount` to 0, which left the
                    // saved bundle row carrying a discount value WAY
                    // higher than the service price (e.g. discount=
                    // 49,990 on a 24,995 service when the voucher had
                    // 50k balance). The user observed this directly on
                    // plan #47275.
                    if ($patientVoucher && (float) $patientVoucher->amount > 0) {
                        $discount_type = Config::get('constants.Fixed');
                        $discount_price = min((float) $patientVoucher->amount, (float) $service_data->price);
                        $discount_is_voucher = true;
                        $net_amount = ((float) $service_data->price) - $discount_price;
                        if ($net_amount < 0) {
                            $net_amount = 0;
                        }
                    } else {
                        $discount_type = '';
                        $discount_price = 0;
                        $discount_is_voucher = false;
                        $net_amount = $service_data->price;
                    }
                }
                $loc = Locations::find($location_id);

                return [
                    'success' => true,
                    'message' => 'Record Found',
                    'data' => [
                        'discount_type' => $net_amount < 0 ? '' : $discount_type,
                        'discount_price' => $discount_price,
                        'net_amount' => $net_amount < 0 ? $service_data->price : $net_amount,
                        'custom_checked' => 0,
                        'discount_is_voucher' => $discount_is_voucher,
                        'slug' => $allocation_slug,
                        'service_name' => $service_data->name,
                        'service_price' => $service_data->price,
                        'tax_treatment_type_id' => $service_data->tax_treatment_type_id,
                        'location_tax_percentage' => $loc->tax_percentage ?? 0,
                    ],
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'No Record Found',
            'status_code' => 404,
        ];
    }

    /**
     * Get custom discount info for simple plans (non-bundle).
     * Extracted from PackagesController::getdiscountinfocustom_for_plan().
     */
    public function getCustomDiscountInfoForPlan(array $data): array
    {
        $status = true;
        $service_id = $data['service_id'];
        $location_id = $data['location_id'];
        $service_data = Services::find($service_id);

        if (! $service_data) {
            return [
                'success' => false,
                'message' => 'Service not found',
                'status_code' => 500,
            ];
        }

        $discount_id = $data['discount_id'];
        $discount_data = Discounts::find($discount_id);

        $allocations = DiscountWidget::loadPlanDiscountAllocationsByLocationService($location_id, $service_id, Auth::user()->account_id);
        $allocation = $allocations[$discount_id] ?? null;

        $effective_type = $allocation?->type;
        $effective_amount = $allocation?->amount;
        $allocation_slug = $allocation?->slug ?? 'default';

        $discount_value = $data['discount_value'] ?? 0;
        $discount_type_input = $data['discount_type'] ?? null;
        $patient_id = $data['patient_id'] ?? null;

        if ($allocation_slug == 'custom') {
            $discount_id = $data['discount_id'];
        } else {
            if ($discount_data->discount_type == 'voucher') {
                $discountValue = UserVouchers::where('user_id', $patient_id)->where('voucher_id', $discount_id)->first();
                if ($discountValue) {
                    $discount_value = $discountValue->amount;
                } else {
                    $discount_value = 0;
                }
            } else {
                $discount_value = $effective_amount;
            }
        }

        $net_amount = $service_data->price;
        $discount_price = 0;

        if ($effective_type == 'Fixed' && $discount_data->discount_type != 'voucher') {
            if ($discount_type_input == Config::get('constants.Fixed')) {
                if ($discount_value > $effective_amount || $discount_value > $service_data->price) {
                    $status = false;
                }
                $discount_type = Config::get('constants.Fixed');
                $discount_price = $discount_value;
                $discount_price_in_percentage = ($discount_price / $service_data->price) * 100;
                $net_amount = ($service_data->price) - ($discount_price);
            } else {
                $discount_type = Config::get('constants.Percentage');
                $discount_price = $discount_value;
                $discount_price_cal = ($effective_amount / $service_data->price) * 100;
                if ($discount_value > $discount_price_cal) {
                    $status = false;
                }
                $amount_after_per = ($discount_value / 100) * $service_data->price;
                $net_amount = $service_data->price - $amount_after_per;
            }
        } elseif ($effective_type == 'Fixed' && $discount_data->discount_type == 'voucher') {
            $discountValue = UserVouchers::where('user_id', $patient_id)->where('voucher_id', $discount_id)->first();
            if ($discountValue) {
                $discount_type = Config::get('constants.Fixed');
                $discount_price = $discountValue->amount;
                $discount_price_in_percentage = ($discount_price / $service_data->price) * 100;
                $net_amount = ($service_data->price) - ($discount_price);
                if ($net_amount < 0) {
                    $net_amount = 0;
                }
            } else {
                $discount_price = 0;
                $net_amount = ($service_data->price) - ($discount_price);
            }
        } elseif ($effective_type == 'Percentage' && $discount_data->discount_type == 'voucher') {
            $discountValue = UserVouchers::where('user_id', $patient_id)->where('voucher_id', $discount_id)->first();
            if ($discountValue) {
                $discount_price = $discountValue->amount;
                $discount_price_in_percentage = ($discount_price / 100) * $service_data->price;
                $net_amount = ($service_data->price) - ($discount_price_in_percentage);
                if ($net_amount < 0) {
                    $net_amount = 0;
                }
            } else {
                $discount_price = 0;
                $net_amount = $service_data->price;
            }
        } else {
            if ($discount_type_input == Config::get('constants.Fixed')) {
                $discount_price = $discount_value;
                if ($service_data->price > 0) {
                    $discount_price_in_percentage = ($discount_price / $service_data->price) * 100;
                    if ($discount_data->discount_type != 'voucher' && $discount_price_in_percentage > $effective_amount) {
                        $status = false;
                    }
                }
                $net_amount = ($service_data->price) - ($discount_value);
            } else {
                if ($discount_data->discount_type != 'voucher' && $discount_value > $effective_amount) {
                    $status = false;
                }
                $discount_price = $discount_value;
                $discount_price_in_percentage = ($discount_value / 100) * $service_data->price;
                $net_amount = ($service_data->price) - ($discount_price_in_percentage);
            }
        }

        if ($status == true) {
            return [
                'success' => true,
                'message' => 'Net Amount',
                'data' => [
                    'net_amount' => $net_amount < 0 ? 0 : $net_amount,
                ],
            ];
        }

        return [
            'success' => false,
            'message' => 'Invalid discount value',
            'status_code' => 404,
        ];
    }

    /**
     * Save service to plan - handles both simple and configurable discounts.
     * Extracted from PackagesController::savepackages_service_for_plan().
     */
    public function saveServiceForPlan(array $data): array
    {
        Log::info('=== saveServiceForPlan CALLED ===', [
            'service_id_from_request' => $data['service_id'] ?? null,
            'discount_id' => $data['discount_id'] ?? null,
            'random_id' => $data['random_id'] ?? null,
            'location_id' => $data['location_id'] ?? null,
        ]);

        $location_information = Locations::find($data['location_id'] ?? null);
        if (! $location_information) {
            return [
                'success' => false,
                'message' => 'Location not found.',
                'status_code' => 500,
            ];
        }

        $service_data = Services::find($data['service_id'] ?? null);
        if (! $service_data) {
            return [
                'success' => false,
                'message' => 'Service not found.',
                'status_code' => 500,
            ];
        }

        Log::info('saveServiceForPlan: service found', [
            'service_id' => $service_data->id,
            'service_name' => $service_data->name,
            'service_table' => 'services',
        ]);

        // Check if plan is already settled
        $find_package = Packages::where('random_id', $data['random_id'])->first();
        if ($find_package) {
            $check_is_setteled = PackageAdvances::where([
                ['cash_flow', '=', 'out'],
                ['cash_amount', '>', 0],
                ['is_setteled', '=', '1'],
                ['package_id', '=', $find_package->id],
            ])->first();
            if ($check_is_setteled) {
                return [
                    'success' => false,
                    'message' => 'Plan is already settled. You cannot add further treatment.',
                    'status_code' => 500,
                ];
            }
        }

        $discount_data = ($data['discount_id'] ?? null) ? Discounts::find($data['discount_id']) : null;

        // --- CONFIGURABLE DISCOUNT PATH ---
        if ($discount_data && $discount_data->type === 'Configurable') {
            $baseServices = BaseDiscountService::where('discount_id', $discount_data->id)->get();
            $getServices = GetDiscountService::where('discount_id', $discount_data->id)->get();
            $isCategoryMode = $baseServices->isNotEmpty() && $baseServices->first()->is_category == 1;
            $selectedService = Services::find($data['service_id']);
            // Use concat (not merge): Eloquent Collection::merge dedupes by
            // primary key, and BaseDiscountService / GetDiscountService have
            // independent auto-increment IDs that frequently collide. With
            // merge, any BUY row sharing an id with a GET row got silently
            // clobbered — the save path then emitted GETs only and the
            // operator saw "Add row did nothing for the BUY service".
            $mergedServices = $baseServices->concat($getServices);

            // One config_group_id per Buy/Get save call so the SPA can
            // render the resulting bundles together (parent/child layout)
            // and tear the whole group down on a single delete. Without
            // this tag the rows render as flat siblings and the operator
            // can delete the GET while the BUY stays — which is the
            // exact UX bug we hit. Format mirrors the legacy admin's
            // 'cfg_<random>' convention; the column is varchar(100).
            $configGroupId = 'cfg_' . Str::random(12);

            $myarray = [];
            $running_total = str_replace(',', '', $data['package_total'] ?? '0');
            if ($running_total === '') {
                $running_total = 0;
            }

            foreach ($mergedServices as $ds) {
                $is_buy_row = ($ds instanceof BaseDiscountService || ! isset($ds->discount_type));
                if (($is_buy_row && $isCategoryMode) || (! $is_buy_row && $ds->same_service)) {
                    $svc = $selectedService;
                } else {
                    $svc = Services::find($ds->service_id);
                }
                if (! $svc) {
                    continue;
                }

                if ($is_buy_row) {
                    $row_net_amount = $svc->price;
                    $row_disc_type = '-';
                    $row_disc_price = 0;
                } else {
                    [$row_disc_price_calc, $row_net_amount, $rowLabel] = self::resolveGetDiscountAmounts($svc->price, $ds);
                    if ($ds->discount_type === 'complimentory') {
                        $row_disc_type  = 'Complimentary';
                        $row_disc_price = $svc->price;  // legacy: store full price as the "discount" magnitude on complimentary
                    } elseif ($ds->discount_type === 'fixed') {
                        $row_disc_type  = 'Fixed';
                        $row_disc_price = (float) $ds->discount_amount;
                    } else {
                        $row_disc_type  = 'Percentage';
                        $row_disc_price = $ds->discount_amount;
                    }
                }

                $bundle_data = $data;
                $bundle_data['bundle_id'] = $svc->id;
                $bundle_data['service_price'] = $svc->price;
                $bundle_data['net_amount'] = $row_net_amount;
                $bundle_data['discount_name'] = $discount_data->name;
                $bundle_data['discount_type'] = $row_disc_type;
                $bundle_data['discount_price'] = $row_disc_price;
                $bundle_data['qty'] = '1';

                if (($data['is_exclusive'] ?? '') == '' || ($data['is_exclusive'] ?? null) === null) {
                    $bundle_data['is_exclusive'] = 1;
                }

                $tax_pct = $location_information->tax_percentage ?? 0;
                $orgTaxTreatment = Settings::getOrgTaxTreatment(Auth::user()->account_id);
                if ($orgTaxTreatment == Config::get('constants.tax_is_exclusive') ||
                    ($orgTaxTreatment == Config::get('constants.tax_both') && ($bundle_data['is_exclusive'] ?? 1) == 1)) {
                    $bundle_data['tax_exclusive_net_amount'] = $row_net_amount;
                    $bundle_data['tax_percentage'] = $tax_pct;
                    $bundle_data['tax_price'] = ceil($row_net_amount * ($tax_pct / 100));
                    $bundle_data['tax_including_price'] = ceil($row_net_amount + $bundle_data['tax_price']);
                    $bundle_data['is_exclusive'] = 1;
                } else {
                    $bundle_data['tax_including_price'] = $row_net_amount;
                    $bundle_data['tax_percentage'] = $tax_pct;
                    $bundle_data['tax_exclusive_net_amount'] = $tax_pct > 0 ? ceil((100 * $row_net_amount) / ($tax_pct + 100)) : $row_net_amount;
                    $bundle_data['tax_price'] = ceil($row_net_amount - $bundle_data['tax_exclusive_net_amount']);
                    $bundle_data['is_exclusive'] = 0;
                }

                if (! ($data['discount_id'] ?? null)) {
                    $bundle_data['discount_id'] = null;
                }

                // Tag every bundle in this Buy/Get save with the same
                // config_group_id. Edit-dialog grouping + per-row
                // can_remove gating keys off this column.
                $bundle_data['config_group_id'] = $configGroupId;

                // Tag the overloaded bundle_id as a service id so crm2 and
                // every source_type-aware consumer (invoices, reports, name
                // resolution) reads the right catalog. Matches crm2; bundle
                // rows set 'bundle' elsewhere, memberships stay null.
                $bundle_data['source_type'] = 'service';

                $bundle_data['created_at'] = Filters::getCurrentTimeStamp();
                $bundle_data['updated_at'] = Filters::getCurrentTimeStamp();

                $packagebundle = PackageBundles::createPackagebundle($bundle_data);

                // Tag the row's slot in the configurable group's
                // consumption queue: 1 = paid BUY anchor, 2 = priced
                // GET (Percentage/Fixed/custom — a real discount applied
                // to a paid service), 3 = complimentary GET (free).
                // The legacy admin's `PlanService::validateConsumptionOrder`
                // + `PlanService::getEditFormData`'s `add_locked` use
                // this to refuse new rows when a higher-order session
                // has been consumed before a lower-order sibling
                // (operator gave away the freebie first, then tried to
                // bolt more services onto the plan). Without an order
                // value the comparison `ps2.consumption_order < this`
                // is `0 < 0` for every row, the predicate never fires,
                // and the lock can never trigger — which is why this
                // path silently failed in production after the SPA
                // edit dialog started reading `add_locked`.
                if ($is_buy_row) {
                    $rowConsumptionOrder = 1;
                } else {
                    $rowConsumptionOrder = ($ds->discount_type === 'complimentory') ? 3 : 2;
                }

                $data_service = [
                    'random_id' => $data['random_id'],
                    'package_bundle_id' => $packagebundle->id,
                    'service_id' => $svc->id,
                    // Carry sold_by through to the package_services row.
                    // The non-configurable plan path already does this in
                    // `PlanService::buildServiceDataWithTaxFromService`,
                    // but every BUY/GET row inserted from this method
                    // dropped it on the floor — so configurable discount
                    // groups always landed with sold_by NULL and never
                    // showed up in any upsell report.
                    'sold_by' => $data['sold_by'] ?? null,
                    'consumption_order' => $rowConsumptionOrder,
                    'price' => $row_net_amount,
                    'orignal_price' => $svc->price,
                    'tax_including_price' => $bundle_data['tax_including_price'],
                    'tax_percentage' => $bundle_data['tax_percentage'],
                    'tax_exclusive_price' => $bundle_data['tax_exclusive_net_amount'],
                    'tax_price' => $bundle_data['tax_price'],
                    'is_exclusive' => $bundle_data['is_exclusive'],
                    'created_at' => Filters::getCurrentTimeStamp(),
                    'updated_at' => Filters::getCurrentTimeStamp(),
                ];
                PackageService::createPackageService($data_service);

                $running_total = (float) str_replace(',', '', (string) $running_total) + (float) $packagebundle->tax_including_price;

                $package_service_detail = Services::join('package_services', 'services.id', '=', 'package_services.service_id')
                    ->select('package_services.*', 'services.name')
                    ->where('package_services.package_bundle_id', '=', $packagebundle->id)
                    ->get();

                $myarray[] = [
                    'record' => PackageBundles::find($packagebundle->id),
                    'record_detail' => $package_service_detail,
                    'random_id' => $data['random_id'],
                    'service_name' => $svc->name,
                    'service_price' => $svc->price,
                    'discount_name' => $discount_data->name,
                    'discount_type' => $row_disc_type,
                    'discount_price' => $row_disc_price,
                    'net_amount' => $row_net_amount,
                    'total' => number_format($running_total),
                ];
            }

            $pkg = Packages::where('random_id', $data['random_id'])->first();
            if ($pkg) {
                $grand_total = (float) PackageBundles::where('package_id', $pkg->id)->sum('tax_including_price');
            } else {
                $grand_total = (float) PackageBundles::where('random_id', $data['random_id'])->sum('tax_including_price');
            }
            if (! empty($myarray)) {
                $myarray[0]['grand_total'] = $grand_total;
            }

            return [
                'success' => true,
                'message' => 'Record found',
                'data' => [
                    'is_configurable' => true,
                    'rows' => $myarray,
                    'grand_total' => $grand_total,
                ],
            ];
        }

        // --- SIMPLE DISCOUNT PATH ---
        $total = str_replace(',', '', $data['package_total'] ?? '0');
        if ($total === '') {
            $total = 0;
        }

        $is_exclusive = $data['is_exclusive'] ?? '';
        if ($is_exclusive == '' || $is_exclusive === null) {
            $is_exclusive = 1;
        }

        $bundle_data = $data;
        $bundle_data['bundle_id'] = $service_data->id;
        $bundle_data['service_price'] = $service_data->price;
        $bundle_data['qty'] = '1';
        $bundle_data['is_exclusive'] = $is_exclusive;

        if ($discount_data) {
            $bundle_data['discount_name'] = $discount_data->name;
        }

        $tax_pct = $location_information->tax_percentage ?? 0;
        $net_amount = $data['net_amount'] ?? $service_data->price;

        // === Server-side price authority (business rules confirmed by the
        // product owner 2026-06-10) ===
        // The SPA computes net_amount client-side; this is the server's
        // backstop so a buggy build (e.g. a swallowed catalog lookup that
        // stages 0 — the realistic undercharge) or a crafted payload cannot
        // persist a wrong sold price. `service_data->price` is the pre-tax
        // catalog regular and `$net_amount` is the pre-tax sold price — same
        // basis, so the comparison is apples-to-apples. The configurable
        // discount path above already derives every amount from the catalog,
        // so it needs no equivalent guard.
        $catalogPrice = (float) $service_data->price;
        $netAmount = (float) $net_amount;
        if ($netAmount < 0) {
            return ['success' => false, 'message' => 'Invalid price: amount cannot be negative.', 'status_code' => 422];
        }
        // Sold price is never above catalog (confirmed). 1-rupee tolerance
        // absorbs tax/rounding noise.
        if ($netAmount > $catalogPrice + 1) {
            return ['success' => false, 'message' => 'Sold price cannot exceed the catalog price.', 'status_code' => 422];
        }
        // A zero price is only valid with a discount applied (confirmed: a 0
        // needs a 100%/complimentary discount). A 0 with NO discount is the
        // swallowed-lookup undercharge — reject it before it persists.
        if ($netAmount <= 0 && ! ($data['discount_id'] ?? null)) {
            return ['success' => false, 'message' => 'A zero price requires a discount to be applied.', 'status_code' => 422];
        }

        $orgTaxTreatment = Settings::getOrgTaxTreatment(Auth::user()->account_id);
        if ($orgTaxTreatment == Config::get('constants.tax_is_exclusive') ||
            ($orgTaxTreatment == Config::get('constants.tax_both') && $is_exclusive == '1')) {
            $bundle_data['tax_exclusive_net_amount'] = $net_amount;
            $bundle_data['tax_percentage'] = $tax_pct;
            $bundle_data['tax_price'] = ceil($net_amount * ($tax_pct / 100));
            $bundle_data['tax_including_price'] = ceil($net_amount + $bundle_data['tax_price']);
            $bundle_data['is_exclusive'] = 1;
        } else {
            $bundle_data['tax_including_price'] = $net_amount;
            $bundle_data['tax_percentage'] = $tax_pct;
            $bundle_data['tax_exclusive_net_amount'] = $tax_pct > 0 ? ceil((100 * $net_amount) / ($tax_pct + 100)) : $net_amount;
            $bundle_data['tax_price'] = ceil($net_amount - $bundle_data['tax_exclusive_net_amount']);
            $bundle_data['is_exclusive'] = 0;
        }

        if (! ($data['discount_id'] ?? null)) {
            $bundle_data['discount_id'] = null;
        }

        // Tag the overloaded bundle_id as a service id so crm2 and every
        // source_type-aware consumer (invoices, reports, name resolution)
        // reads the right catalog. Matches crm2; bundle rows set 'bundle'
        // elsewhere, memberships stay null.
        $bundle_data['source_type'] = 'service';

        $bundle_data['created_at'] = Filters::getCurrentTimeStamp();
        $bundle_data['updated_at'] = Filters::getCurrentTimeStamp();

        $packagebundle = PackageBundles::createPackagebundle($bundle_data);

        Log::info('saveServiceForPlan: PackageBundle created', [
            'packagebundle_id' => $packagebundle->id,
            'bundle_id_stored' => $packagebundle->bundle_id,
            'service_data_id' => $service_data->id,
            'service_data_name' => $service_data->name,
        ]);

        // Consume the voucher balance on this plan row if a voucher-type
        // discount was applied. Without this, the `user_vouchers` ledger
        // is never decremented through the "Add service to plan" flow
        // and the same voucher can be re-applied until explicitly
        // reset, distorting patient balance and the usage report.
        $this->consumeVoucherForBundle($discount_data, $data, $service_data->id, $packagebundle, null, $service_data);

        $data_service = [
            'random_id' => $data['random_id'],
            'package_bundle_id' => $packagebundle->id,
            'service_id' => $service_data->id,
            // Carry sold_by — same fix applied to the configurable
            // BUY/GET path above. Without it, simple-discount rows
            // (single service + non-configurable discount) also
            // landed with sold_by NULL.
            'sold_by' => $data['sold_by'] ?? null,
            'price' => $net_amount,
            'orignal_price' => $service_data->price,
            'tax_including_price' => $bundle_data['tax_including_price'],
            'tax_percentage' => $bundle_data['tax_percentage'],
            'tax_exclusive_price' => $bundle_data['tax_exclusive_net_amount'],
            'tax_price' => $bundle_data['tax_price'],
            'is_exclusive' => $bundle_data['is_exclusive'],
            'created_at' => Filters::getCurrentTimeStamp(),
            'updated_at' => Filters::getCurrentTimeStamp(),
        ];
        PackageService::createPackageService($data_service);

        $total = number_format((float) $total + (float) $packagebundle->tax_including_price);

        $discount_name = '-';
        $discount_type = '-';
        $discount_price = '0.00';
        if ($data['discount_id'] ?? null) {
            $discount_name = $packagebundle->discount_name ?? $discount_data->name ?? '-';
            $discount_type = $packagebundle->discount_type ?? '-';
            $discount_price = $packagebundle->discount_price ?? '0.00';
        }

        $package_service = Services::join('package_services', 'services.id', '=', 'package_services.service_id')
            ->select('package_services.*', 'services.name')
            ->where('package_services.package_bundle_id', '=', $packagebundle->id)
            ->get();

        $myarray = [
            'record' => PackageBundles::find($packagebundle->id),
            'record_detail' => $package_service,
            'random_id' => $data['random_id'],
            'service_name' => $service_data->name,
            'service_price' => $service_data->price,
            'discount_name' => $discount_name,
            'discount_type' => $discount_type,
            'discount_price' => $discount_price,
            'net_amount' => $packagebundle->net_amount,
            'total' => $total,
        ];

        return [
            'success' => true,
            'message' => 'Record found',
            'data' => [
                'is_configurable' => false,
                'myarray' => $myarray,
            ],
        ];
    }

    /**
     * Save packages service information (bundle path).
     * Extracted from PackagesController::savepackages_service().
     */
    public function savePackagesService(array $data): array
    {
        Log::info('=== savePackagesService (BUNDLE PATH) CALLED ===', [
            'bundle_id_from_request' => $data['bundle_id'] ?? null,
            'discount_id' => $data['discount_id'] ?? null,
            'random_id' => $data['random_id'] ?? null,
        ]);

        $status = true;
        $service_data = Bundles::find($data['bundle_id'] ?? null);
        Log::info('savePackagesService: Bundles::find result', [
            'found' => $service_data ? true : false,
            'name' => $service_data->name ?? 'NULL',
            'id' => $service_data->id ?? 'NULL',
        ]);
        $find_package = Packages::where('random_id', $data['random_id'])->first();
        if ($find_package) {
            $check_is_setteled = PackageAdvances::where([
                ['cash_flow', '=', 'out'],
                ['cash_amount', '>', 0],
                ['is_setteled', '=', '1'],
                ['package_id', '=', $find_package->id],
            ])->first();
            if ($check_is_setteled) {
                return [
                    'success' => false,
                    'message' => 'Plan is already settled. you can not add further treatment in this plan.',
                    'status_code' => 404,
                    'data' => ['setteled' => 1],
                ];
            }
        }
        $find_discount = Discounts::find($data['discount_id'] ?? null);

        if ($find_discount && $find_discount->type == 'Configurable') {
            if (($data['is_exclusive'] ?? '') == '') {
                $data['is_exclusive'] = 1;
            }
            if ($status == true) {
                $location_information = Locations::find($data['location_id']);
                $discount_info = Discounts::find($data['discount_id']);
                $base_services = BaseDiscountService::where('discount_id', $data['discount_id'])->get();
                $discounted_services = GetDiscountService::where('discount_id', $data['discount_id'])->get();
                $isCategoryMode = $base_services->isNotEmpty() && $base_services->first()->is_category == 1;
                $selectedService = Services::find($data['service_id'] ?? ($data['bundle_id'] ?? null));
                $merged_services = $base_services->merge($discounted_services);
                $myarray = [];
                foreach ($merged_services as $ds) {
                    $isBuyRow = $ds instanceof BaseDiscountService || ! isset($ds->discount_type);
                    if (($isBuyRow && $isCategoryMode) || (! $isBuyRow && $ds->same_service)) {
                        $service_data1 = $selectedService;
                    } else {
                        $service_data1 = Services::find($ds->service_id);
                    }
                    if (! $service_data1) {
                        continue;
                    }

                    $data['qty'] = '1';
                    $data['bundle_id'] = $service_data1->id;
                    $data['service_price'] = $service_data1->price;
                    if ($discount_info) {
                        $data['discount_name'] = $discount_info->name;
                    }
                    $orgTaxTreatment = Settings::getOrgTaxTreatment(Auth::user()->account_id);
                    if ($orgTaxTreatment == Config::get('constants.tax_both')) {
                        if ($data['is_exclusive'] == '1') {
                            $data['tax_exclusive_net_amount'] = $ds->discount_type == 'complimentory' ? 0 : $data['net_amount'];
                            $data['tax_percentage'] = $ds->discount_type == 'complimentory' ? 0 : $location_information->tax_percentage;
                            $data['tax_price'] = $ds->discount_type == 'complimentory' ? 0 : ceil($data['tax_exclusive_net_amount'] * ($location_information->tax_percentage / 100));
                            $data['tax_including_price'] = $ds->discount_type == 'complimentory' ? 0 : ceil($data['tax_exclusive_net_amount'] + (($data['tax_exclusive_net_amount'] * $data['tax_percentage']) / 100));
                            $data['is_exclusive'] = 1;
                        } else {
                            $data['tax_including_price'] = $ds->discount_type == 'complimentory' ? 0 : $data['net_amount'];
                            $data['tax_percentage'] = $ds->discount_type == 'complimentory' ? 0 : $location_information->tax_percentage;
                            $data['tax_exclusive_net_amount'] = $ds->discount_type == 'complimentory' ? 0 : ceil((100 * $data['tax_including_price']) / ($data['tax_percentage'] + 100));
                            $data['tax_price'] = $ds->discount_type == 'complimentory' ? 0 : ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                            $data['is_exclusive'] = 0;
                        }
                    } elseif ($orgTaxTreatment == Config::get('constants.tax_is_exclusive')) {
                        $data['tax_exclusive_net_amount'] = $ds->discount_type == 'complimentory' ? 0 : $data['net_amount'];
                        $data['tax_percentage'] = $ds->discount_type == 'complimentory' ? 0 : $location_information->tax_percentage;
                        $data['tax_price'] = $ds->discount_type == 'complimentory' ? 0 : ceil($data['tax_exclusive_net_amount'] * ($location_information->tax_percentage / 100));
                        $data['tax_including_price'] = $ds->discount_type == 'complimentory' ? 0 : ceil($data['tax_exclusive_net_amount'] + (($data['tax_exclusive_net_amount'] * $data['tax_percentage']) / 100));

                        $data['is_exclusive'] = 1;
                    } else {

                        if ($ds->discount_type == 'complimentory') {
                            $data['tax_including_price'] = $ds->discount_type == 'complimentory' ? 0 : $data['net_amount'];
                            $data['tax_percentage'] = $ds->discount_type == 'complimentory' ? 0 : $location_information?->tax_percentage ?? '00.00';
                            $data['tax_exclusive_net_amount'] = $ds->discount_type == 'complimentory' ? 0 : ceil((100 * $data['tax_including_price']) / ($data['tax_percentage'] + 100));
                            $data['tax_price'] = $ds->discount_type == 'complimentory' ? 0 : ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                            $data['is_exclusive'] = 0;
                        } elseif ($ds->discount_type == 'custom') {
                            $amount_after_discount = ($ds->discount_amount / 100) * $service_data1->price;

                            $data['tax_including_price'] = $service_data1->price - $amount_after_discount;
                            $data['discount_type'] = $ds->discount_type;
                            $data['discount_price'] = $ds->discount_amount;
                            $data['tax_percentage'] = $location_information?->tax_percentage ?? '00.00';
                            $data['tax_exclusive_net_amount'] = ceil((100 * $data['tax_including_price']) / ($data['tax_percentage'] + 100));
                            $data['tax_price'] = ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                            $data['is_exclusive'] = 0;
                        } else {

                            $data['tax_including_price'] = $data['net_amount'];
                            $data['tax_percentage'] = $location_information?->tax_percentage ?? '00.00';
                            $data['tax_exclusive_net_amount'] = ceil((100 * $data['tax_including_price']) / ($data['tax_percentage'] + 100));
                            $data['tax_price'] = ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                            $data['is_exclusive'] = 0;
                        }
                    }
                    if ($data['discount_id'] == '0' || $data['discount_id'] == '') {
                        $data['discount_id'] = null;
                    }
                    $data['created_at'] = Filters::getCurrentTimeStamp();
                    $data['updated_at'] = Filters::getCurrentTimeStamp();
                    $packagesbundly = PackageBundles::createPackagebundle($data);

                    $calculated_services = [[
                        'service_price' => $service_data1->price,
                        'calculated_price' => $data['net_amount'] ?? $service_data1->price,
                        'service_id' => $service_data1->id,
                    ]];

                    foreach ($calculated_services as $detail) {
                        // Common identity fields applied to every branch —
                        // the per-discount-type branches below adjust prices
                        // only, not row attribution.
                        $data_service['random_id'] = $data['random_id'];
                        $data_service['package_bundle_id'] = $packagesbundly->id;
                        $data_service['service_id'] = $detail['service_id'];
                        $data_service['sold_by'] = $data['sold_by'] ?? null;

                        if ($ds->discount_type == 'complimentory') {
                            $data_service['price'] = 0;
                            $data_service['orignal_price'] = 0;
                        } elseif ($ds->discount_type == 'custom') {
                            $amount_after_discount = ($ds->discount_amount / 100) * $service_data1->price;
                            $data_service['price'] = $service_data1->price - $amount_after_discount;
                            $data_service['orignal_price'] = $service_data1->price;
                        } else {
                            $data_service['price'] = $detail['calculated_price'];
                            $data_service['orignal_price'] = $detail['service_price'];
                        }

                        // Org-wide tax treatment already resolved earlier in this scope ($orgTaxTreatment).
                        if ($orgTaxTreatment == Config::get('constants.tax_both')) {
                            if ($data['is_exclusive'] == '1') {
                                $data_service['tax_exclusive_price'] = $ds->discount_type == 'complimentory' ? 0 : $detail['calculated_price'];
                                $data_service['tax_percentage'] = $location_information->tax_percentage;
                                $data_service['tax_price'] = ceil($detail['calculated_price'] * ($location_information->tax_percentage / 100));
                                $data_service['tax_including_price'] = $ds->discount_type == 'complimentory' ? 0 : ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percentage']) / 100));
                                $data_service['is_exclusive'] = 1;
                            } else {
                                $data_service['tax_including_price'] = $ds->discount_type == 'complimentory' ? 0 : $detail['calculated_price'];
                                $data_service['tax_percentage'] = $location_information->tax_percentage;
                                $data_service['tax_exclusive_price'] = $ds->discount_type == 'complimentory' ? 0 : ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percentage'] + 100));
                                $data_service['tax_price'] = $ds->discount_type == 'complimentory' ? 0 : ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);

                                $data_service['is_exclusive'] = 0;
                            }
                        } elseif ($orgTaxTreatment == Config::get('constants.tax_is_exclusive')) {
                            $data_service['tax_exclusive_price'] = $ds->discount_type == 'complimentory' ? 0 : $detail['calculated_price'];
                            $data_service['tax_percentage'] = $location_information->tax_percentage;
                            $data_service['tax_price'] = $ds->discount_type == 'complimentory' ? 0 : ceil($detail['calculated_price'] * ($location_information->tax_percentage / 100));
                            $data_service['tax_including_price'] = $ds->discount_type == 'complimentory' ? 0 : ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percentage']) / 100));

                            $data_service['is_exclusive'] = 1;
                        } else {
                            if ($ds->discount_type == 'complimentory') {
                                $data_service['tax_including_price'] = 0;
                                $data_service['tax_percentage'] = 0;
                                $data_service['tax_exclusive_price'] = 0;
                                $data_service['tax_price'] = 0;

                                $data_service['is_exclusive'] = 0;
                            } elseif ($ds->discount_type == 'custom') {
                                $amount_after_discount = ($ds->discount_amount / 100) * $service_data1->price;
                                $data_service['tax_including_price'] = $service_data1->price - $amount_after_discount;
                                $data_service['tax_percentage'] = $location_information->tax_percentage;
                                $data_service['tax_exclusive_price'] = $ds->discount_type == 'complimentory' ? 0 : ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percentage'] + 100));
                                $data_service['tax_price'] = $ds->discount_type == 'complimentory' ? 0 : ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);
                                $data_service['is_exclusive'] = 0;
                            } else {
                                $data_service['tax_including_price'] = $ds->discount_type == 'complimentory' ? 0 : $detail['calculated_price'];
                                $data_service['tax_percentage'] = $location_information->tax_percentage;
                                $data_service['tax_exclusive_price'] = $ds->discount_type == 'complimentory' ? 0 : ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percentage'] + 100));
                                $data_service['tax_price'] = $ds->discount_type == 'complimentory' ? 0 : ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);
                                $data_service['is_exclusive'] = 0;
                            }
                        }
                        $data_service['created_at'] = Filters::getCurrentTimeStamp();
                        $data_service['updated_at'] = Filters::getCurrentTimeStamp();
                        $packageservice = PackageService::createPackageService($data_service);
                    }
                    $total = str_replace(',', '', $data['package_total'] ?? '');

                    if ($total == '') {
                        $total = 0;
                    }

                    $total = number_format((float) $total + (float) $packagesbundly->tax_including_price);
                    $net_amount = $packagesbundly->net_amount;
                    $service_name = $service_data1->name;
                    $service_price = $packagesbundly->service_price;

                    if ($data['discount_id'] == '0' || $data['discount_id'] == null) {
                        $discount_name = '-';
                        $discount_type = '-';
                        $discount_price = '0.00';
                    } else {
                        $discount_name = $packagesbundly->discount_name;
                        $discount_type = $packagesbundly->discount_type;
                        $discount_price = $packagesbundly->discount_price;
                    }
                    $package_service = Services::join('package_services', 'services.id', '=', 'package_services.service_id')
                        ->select('package_services.*', 'services.name')
                        ->where('package_services.package_bundle_id', '=', $packagesbundly->id)
                        ->get();
                    $package_bundles = PackageBundles::find($packagesbundly->id);
                    $myarray[] = [
                        'record' => $package_bundles,
                        'record_detail' => $package_service,
                        'random_id' => $data['random_id'],
                        'service_name' => $service_name,
                        'service_price' => $service_price,
                        'discount_name' => $discount_name,
                        'discount_type' => $discount_type,
                        'discount_price' => $discount_price,
                        'net_amount' => $net_amount,
                        'total' => str_replace(',', '', $total),
                    ];
                }

                $grand_total = str_replace(',', '', $data['package_total'] ?? '');
                if ($grand_total == '') {
                    $grand_total = 0;
                }
                $package_id = Packages::where('random_id', $data['random_id'])->first();
                if ($package_id) {
                    $sum_services_price = PackageBundles::where('package_id', $package_id->id)->sum('tax_including_price');

                    $grand_total = (float) $sum_services_price;
                    $myarray[0]['grand_total'] = $grand_total;
                } else {
                    $sum_services_price = PackageBundles::where('random_id', $data['random_id'])->sum('tax_including_price');
                    $grand_total = (float) $sum_services_price;
                    $myarray[0]['grand_total'] = $grand_total;
                }

                return [
                    'success' => true,
                    'message' => 'Record found',
                    'data' => [
                        'myarray' => $myarray[0] ?? $myarray,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'No Record found',
                'status_code' => 404,
            ];
        } else {

            $total = str_replace(',', '', $data['package_total'] ?? '');
            if ($total == '') {
                $total = 0;
            }
            if (($data['is_exclusive'] ?? '') == '') {
                $data['is_exclusive'] = 1;
            }
            if ($status == true) {
                $location_information = Locations::find($data['location_id']);

                $discount_info = Discounts::find($data['discount_id'] ?? null);

                $data['qty'] = '1';
                $data['bundle_id'] = $service_data->id;
                $data['service_price'] = $service_data->price;

                if ($discount_info) {
                    $data['discount_name'] = $discount_info->name;
                }
                $orgTaxTreatment = Settings::getOrgTaxTreatment(Auth::user()->account_id);
                if ($orgTaxTreatment == Config::get('constants.tax_both')) {
                    if ($data['is_exclusive'] == '1') {
                        $data['tax_exclusive_net_amount'] = $data['net_amount'];
                        $data['tax_percentage'] = $location_information->tax_percentage;
                        $data['tax_price'] = ceil($data['tax_exclusive_net_amount'] * ($location_information->tax_percentage / 100));
                        $data['tax_including_price'] = ceil($data['tax_exclusive_net_amount'] + (($data['tax_exclusive_net_amount'] * $data['tax_percentage']) / 100));

                        $data['is_exclusive'] = 1;
                    } else {
                        $data['tax_including_price'] = $data['net_amount'];
                        $data['tax_percentage'] = $location_information->tax_percentage;
                        $data['tax_exclusive_net_amount'] = ceil((100 * $data['tax_including_price']) / ($data['tax_percentage'] + 100));
                        $data['tax_price'] = ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                        $data['is_exclusive'] = 0;
                    }
                } elseif ($orgTaxTreatment == Config::get('constants.tax_is_exclusive')) {
                    $data['tax_exclusive_net_amount'] = $data['net_amount'];
                    $data['tax_percentage'] = $location_information->tax_percentage;
                    $data['tax_price'] = ceil($data['tax_exclusive_net_amount'] * ($location_information->tax_percentage / 100));
                    $data['tax_including_price'] = ceil($data['tax_exclusive_net_amount'] + (($data['tax_exclusive_net_amount'] * $data['tax_percentage']) / 100));

                    $data['is_exclusive'] = 1;
                } else {
                    $data['tax_including_price'] = $data['net_amount'];
                    $data['tax_percentage'] = $location_information?->tax_percentage ?? '00.00';
                    $data['tax_exclusive_net_amount'] = ceil((100 * $data['tax_including_price']) / ($data['tax_percentage'] + 100));
                    $data['tax_price'] = ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                    $data['is_exclusive'] = 0;
                }
                if (($data['discount_id'] ?? '') == '0' || ($data['discount_id'] ?? '') == '') {
                    $data['discount_id'] = null;
                }
                $data['created_at'] = Filters::getCurrentTimeStamp();
                $data['updated_at'] = Filters::getCurrentTimeStamp();

                $packagesbundly = PackageBundles::createPackagebundle($data);

                // Decrement the patient's UserVouchers balance and write a
                // PackageVouchers journal row when a voucher discount was
                // applied on this bundle. The sibling `handleVoucherConsumption`
                // in PlanService only fires on the per-service path
                // (`addServiceToPackage`) — the bundle-path (this method)
                // previously skipped consumption entirely, leaving the
                // voucher balance at its original value so the same voucher
                // could be re-applied indefinitely.
                $this->consumeVoucherForBundle($discount_info, $data, $service_data?->id, $packagesbundly, null, null);

                $bundle_details = BundleHasServices::where('bundle_id', '=', $packagesbundly->bundle_id)->get();
                $calculable_servcies = [];

                foreach ($bundle_details as $detail) {
                    // `service_price` MUST be the catalog regular per-row
                    // price, not the pre-distributed sold-share. Same
                    // root cause as the addBundleService paths in
                    // PlanService — see those comments. Without this
                    // fix, the per-row split here lands asymmetric on
                    // identical sessions.
                    $calculable_servcies[] = [
                        'service_price' => $detail->service_price,
                        'calculated_price' => $detail->calculated_price,
                        'service_id' => $detail->service_id,
                    ];
                }
                $calculated_services = Bundles::calculatePrices($calculable_servcies, $data['service_price'], $data['net_amount']);

                foreach ($calculated_services as $detail) {

                    $data_service['random_id'] = $data['random_id'];
                    $data_service['package_bundle_id'] = $packagesbundly->id;
                    $data_service['service_id'] = $detail['service_id'];
                    $data_service['sold_by'] = $data['sold_by'] ?? null;
                    $data_service['price'] = $detail['calculated_price'];
                    $data_service['orignal_price'] = $detail['service_price'];

                    // Org-wide tax treatment already resolved earlier in this scope ($orgTaxTreatment).
                    if ($orgTaxTreatment == Config::get('constants.tax_both')) {
                        if ($data['is_exclusive'] == '1') {
                            $data_service['tax_exclusive_price'] = $detail['calculated_price'];
                            $data_service['tax_percentage'] = $location_information->tax_percentage;
                            $data_service['tax_price'] = ceil($detail['calculated_price'] * ($location_information->tax_percentage / 100));
                            $data_service['tax_including_price'] = ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percentage']) / 100));

                            $data_service['is_exclusive'] = 1;
                        } else {
                            $data_service['tax_including_price'] = $detail['calculated_price'];
                            $data_service['tax_percentage'] = $location_information->tax_percentage;
                            $data_service['tax_exclusive_price'] = ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percentage'] + 100));
                            $data_service['tax_price'] = ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);

                            $data_service['is_exclusive'] = 0;
                        }
                    } elseif ($orgTaxTreatment == Config::get('constants.tax_is_exclusive')) {
                        $data_service['tax_exclusive_price'] = $detail['calculated_price'];
                        $data_service['tax_percentage'] = $location_information->tax_percentage;
                        $data_service['tax_price'] = ceil($detail['calculated_price'] * ($location_information->tax_percentage / 100));
                        $data_service['tax_including_price'] = ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percentage']) / 100));

                        $data_service['is_exclusive'] = 1;
                    } else {
                        $data_service['tax_including_price'] = $detail['calculated_price'];
                        $data_service['tax_percentage'] = $location_information->tax_percentage;
                        $data_service['tax_exclusive_price'] = ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percentage'] + 100));
                        $data_service['tax_price'] = ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);

                        $data_service['is_exclusive'] = 0;
                    }
                    $data_service['created_at'] = Filters::getCurrentTimeStamp();
                    $data_service['updated_at'] = Filters::getCurrentTimeStamp();

                    $packageservice = PackageService::createPackageService($data_service);
                }
                $total = number_format((float) $total + (float) $packagesbundly->tax_including_price);

                $net_amount = $packagesbundly->net_amount;
                $service_name = $packagesbundly->bundle->name;
                $service_price = $packagesbundly->service_price;

                if (($data['discount_id'] ?? '') == '0' || ($data['discount_id'] ?? null) == null) {
                    $discount_name = '-';
                    $discount_type = '-';
                    $discount_price = '0.00';
                } else {
                    $discount_name = $packagesbundly->discount_name;
                    $discount_type = $packagesbundly->discount_type;
                    $discount_price = $packagesbundly->discount_price;
                }
                $package_service = Services::join('package_services', 'services.id', '=', 'package_services.service_id')
                    ->select('package_services.*', 'services.name')
                    ->where('package_services.package_bundle_id', '=', $packagesbundly->id)
                    ->get();
                $package_bundles = PackageBundles::find($packagesbundly->id);
                $myarray = [
                    'record' => $package_bundles,
                    'record_detail' => $package_service,
                    'random_id' => $data['random_id'],
                    'service_name' => $service_name,
                    'service_price' => $service_price,
                    'discount_name' => $discount_name,
                    'discount_type' => $discount_type,
                    'discount_price' => $discount_price,
                    'net_amount' => $net_amount,
                    'total' => $total,
                ];

                return [
                    'success' => true,
                    'message' => 'Record found',
                    'data' => [
                        'myarray' => $myarray,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'No Record found',
                'status_code' => 404,
            ];
        }
    }

    /**
     * Consume voucher balance for a bundle just created via
     * `savePackagesService`. No-op unless the discount attached to the
     * bundle is of `discount_type = 'voucher'` and a matching
     * `user_vouchers` row exists for the patient.
     *
     * Mirrors the semantics of `PlanService::handleVoucherConsumption`:
     *   - locks the user_vouchers row FOR UPDATE inside a transaction
     *   - deducts `discount_price` from `user_vouchers.amount`
     *     (floored at zero so an over-sized bundle can't push the
     *     balance negative)
     *   - writes a `package_vouchers` journal row per bundle for the
     *     voucher-usage report
     *   - logs the consumed amount via ActivityLogger when a real
     *     deduction happened
     *
     * `$data` comes from the controller request; `$bundleServiceModel`
     * is the Bundles row the user selected; `$packageBundle` is the row
     * just persisted to `package_bundles`.
     */
    /**
     * Decrement the patient's voucher balance by `$amount` at the moment
     * the user clicks the "Add" button on the plan form. No journal row
     * is written — that happens at final save once the PackageBundles
     * record exists. Locks the row FOR UPDATE to serialise concurrent
     * reservations against the same voucher.
     *
     * Returns the fresh user_vouchers.amount so the caller can echo
     * the new balance back to the browser.
     */
    public function reserveVoucherAmount(int $voucherId, int $patientId, float $amount): array
    {
        if ($amount <= 0) {
            return [
                'success' => false,
                'message' => 'Invalid reservation amount.',
                'status_code' => 422,
            ];
        }

        return DB::transaction(function () use ($voucherId, $patientId, $amount): array {
            $userVoucher = UserVouchers::where('voucher_id', $voucherId)
                ->where('user_id', $patientId)
                ->lockForUpdate()
                ->first();

            if (! $userVoucher) {
                return [
                    'success' => false,
                    'message' => 'Voucher is not assigned to this patient.',
                    'status_code' => 404,
                ];
            }

            $originalAmount = (float) $userVoucher->amount;

            if ($originalAmount <= 0) {
                return [
                    'success' => false,
                    'message' => 'Voucher has no remaining balance.',
                    'code' => 'VOUCHER_EXHAUSTED',
                    'status_code' => 422,
                    'data' => ['remaining_amount' => 0],
                ];
            }

            $consumed = min($originalAmount, $amount);
            $amountLeft = max(0.0, $originalAmount - $consumed);

            $userVoucher->update(['amount' => $amountLeft]);

            Log::info('reserveVoucherAmount: balance reserved', [
                'user_voucher_id' => $userVoucher->id,
                'voucher_id' => $voucherId,
                'user_id' => $patientId,
                'requested' => $amount,
                'consumed' => $consumed,
                'remaining' => $amountLeft,
            ]);

            return [
                'success' => true,
                'message' => 'Voucher amount reserved.',
                'data' => [
                    'consumed_amount' => $consumed,
                    'remaining_amount' => $amountLeft,
                    'original_amount' => $originalAmount,
                ],
            ];
        });
    }

    /**
     * Reverse a prior `reserveVoucherAmount` — called when the user
     * removes a plan row before saving, or closes the modal without
     * committing.
     */
    public function refundVoucherAmount(int $voucherId, int $patientId, float $amount): array
    {
        if ($amount <= 0) {
            return [
                'success' => true,
                'message' => 'Nothing to refund.',
                'data' => ['remaining_amount' => null],
            ];
        }

        return DB::transaction(function () use ($voucherId, $patientId, $amount): array {
            $userVoucher = UserVouchers::where('voucher_id', $voucherId)
                ->where('user_id', $patientId)
                ->lockForUpdate()
                ->first();

            if (! $userVoucher) {
                return [
                    'success' => false,
                    'message' => 'Voucher is not assigned to this patient.',
                    'status_code' => 404,
                ];
            }

            $capped = min((float) $userVoucher->total_amount, (float) $userVoucher->amount + $amount);
            $userVoucher->update(['amount' => $capped]);

            Log::info('refundVoucherAmount: balance refunded', [
                'user_voucher_id' => $userVoucher->id,
                'voucher_id' => $voucherId,
                'user_id' => $patientId,
                'refunded' => $amount,
                'remaining' => $capped,
            ]);

            return [
                'success' => true,
                'message' => 'Voucher amount refunded.',
                'data' => ['remaining_amount' => $capped],
            ];
        });
    }

    /**
     * Write the `package_vouchers` journal row for a bundle that was
     * persisted after the voucher balance had already been reserved
     * via `reserveVoucherAmount`. Does NOT touch `user_vouchers.amount`
     * — that decrement happened at Add time.
     *
     * `service_id` must be a `services.id` (production carries a FK
     * `fk_pkg_vouchers_service` on this column). `main_service_id` is
     * also a services.id — in the plan/bundle-save path they're
     * usually the same row (the selected service).
     */
    public function writeVoucherJournalRow(int $voucherId, int $patientId, float $amount, PackageBundles $packageBundle, int|string|null $mainServiceId): void
    {
        if ($amount <= 0) {
            return;
        }

        PackageVouchers::create([
            'package_random_id' => $packageBundle->random_id,
            'voucher_id' => $voucherId,
            'user_id' => $patientId,
            'amount' => $amount,
            'service_id' => $mainServiceId,
            'main_service_id' => $mainServiceId,
        ]);
    }

    /**
     * Public so the final-save path (`PlanService::storePlanTypeServices`)
     * can call it on the same row it just persisted. Callers must pass
     * the patient id explicitly — it's not always present in `$data`.
     */
    public function consumeVoucherForBundle(?Discounts $discount, array $data, int|string|null $mainServiceId, PackageBundles $packageBundle, int|string|null $patientId = null, ?Services $serviceModel = null): void
    {
        Log::info('=== consumeVoucherForBundle ENTERED ===', [
            'discount_id' => $discount?->id,
            'discount_type' => $discount?->discount_type,
            'data_keys' => array_keys($data),
            'patient_id_passed' => $patientId,
            'patient_id_in_data' => $data['patient_id'] ?? null,
            'user_id_in_data' => $data['user_id'] ?? null,
            'service_price' => $serviceModel?->price,
            'package_bundle_id' => $packageBundle->id,
        ]);

        if (! $discount || $discount->discount_type !== 'voucher') {
            Log::info('consumeVoucherForBundle: skipped — not a voucher discount');

            return;
        }

        // The plan-form POSTs the patient id under `user_id`, but
        // sibling save paths (`addServiceToPackage`, other entry
        // points) pass it as `patient_id`. The final-save path passes
        // it explicitly. Try the explicit arg first, then both keys.
        $patientId = $patientId ?? $data['patient_id'] ?? $data['user_id'] ?? null;

        if (! $patientId) {
            Log::warning('consumeVoucherForBundle: skipped — no patient_id / user_id in request data');

            return;
        }

        // Resolve the per-row amount the voucher should pay for. The
        // plan-form does NOT populate `discount_price` for voucher
        // discounts (it only fills `net_amount`), so falling back to
        // discount_price would always read 0 and skip consumption.
        // Mirror `PlanService::handleVoucherConsumption`: deduct the
        // service price (capped at the voucher balance) and write the
        // journal row for the actually-applied portion.
        $servicePrice = (float) ($serviceModel?->price
            ?? $data['service_price']
            ?? $packageBundle->service_price
            ?? 0);

        if ($servicePrice <= 0) {
            Log::warning('consumeVoucherForBundle: skipped — service price resolved to 0', [
                'service_model_price' => $serviceModel?->price,
                'data_service_price' => $data['service_price'] ?? null,
                'bundle_service_price' => $packageBundle->service_price ?? null,
            ]);

            return;
        }

        // SPA pre-reservation path. When `voucher_pre_reserved` is set
        // in the request payload, the SPA has already called
        // `reserveVoucherAmount` to decrement `user_vouchers.amount`
        // BEFORE hitting this save endpoint. Decrementing a second
        // time here would double-charge the voucher — observed on
        // production plan #47275 where balance 30,000 dropped to 0
        // on a single 24,995 service (over-consumed by 5,005).
        //
        // In that case we still need the PackageVouchers journal row
        // so the row-delete path (`deletePackageService`) can refund
        // the right amount when the operator removes the row. We
        // write the journal using the amount the SPA reserved
        // (`$data['discount_price']`) — the cap is already applied at
        // `getDiscountInfo`/`getDiscountInfoForPlan` so
        // discount_price ≤ service_price.
        $preReserved = ! empty($data['voucher_pre_reserved']);
        $preReservedAmount = $preReserved ? (float) ($data['discount_price'] ?? 0) : 0.0;

        if ($preReserved && $preReservedAmount > 0) {
            // Journal-only path. No mutation of user_vouchers.amount.
            // No activity-log entry (the reserve endpoint already logged
            // its decrement to the audit trail).
            PackageVouchers::create([
                'package_random_id' => $data['random_id'] ?? null,
                'voucher_id' => $discount->id,
                'user_id' => $patientId,
                'amount' => $preReservedAmount,
                'service_id' => $packageBundle->id,
                'main_service_id' => $mainServiceId,
            ]);

            Log::info('consumeVoucherForBundle: journal-only (SPA pre-reserved)', [
                'voucher_id' => $discount->id,
                'patient_id' => $patientId,
                'journal_amount' => $preReservedAmount,
                'package_bundle_id' => $packageBundle->id,
            ]);

            return;
        }

        DB::transaction(function () use ($discount, $patientId, $data, $servicePrice, $mainServiceId, $packageBundle): void {
            $userVoucher = UserVouchers::where('voucher_id', $discount->id)
                ->where('user_id', $patientId)
                ->lockForUpdate()
                ->first();

            if (! $userVoucher) {
                Log::warning('consumeVoucherForBundle: skipped — no UserVouchers row for this patient/voucher', [
                    'voucher_id' => $discount->id,
                    'user_id' => $patientId,
                ]);

                return;
            }

            $originalAmount = (float) $userVoucher->amount;
            $amountLeft = max(0.0, $originalAmount - $servicePrice);
            $actualConsumed = $originalAmount - $amountLeft;

            $userVoucher->update(['amount' => $amountLeft]);

            Log::info('consumeVoucherForBundle: balance updated', [
                'user_voucher_id' => $userVoucher->id,
                'original_amount' => $originalAmount,
                'service_price' => $servicePrice,
                'amount_left' => $amountLeft,
                'actual_consumed' => $actualConsumed,
            ]);

            if ($actualConsumed <= 0) {
                // Voucher was already depleted before this row was
                // saved — nothing got deducted from the ledger. The
                // previous code wrote a journal row with
                // `amount = $servicePrice` as a "sentinel", which then
                // showed up in the voucher history dialog as a real
                // consumption of (e.g.) Rs 24,995 against a voucher
                // that had a 0 balance. That is the −Rs 24,995 entry
                // the user observed; it was also unsafe because the
                // row-delete refund path read it as a real refund and
                // could inflate the ledger above `total_amount`
                // (mitigated separately by the cap in
                // `PlanService::deletePackageService`). Skip the
                // journal write entirely when nothing was consumed —
                // the bundle row still carries the voucher's
                // `discount_id` so anyone walking the bundles can see
                // the voucher was attempted; the journal stays clean.
                Log::info('consumeVoucherForBundle: skipped journal — voucher already exhausted', [
                    'user_voucher_id' => $userVoucher->id,
                    'package_bundle_id' => $packageBundle->id,
                ]);

                return;
            }

            PackageVouchers::create([
                'package_random_id' => $data['random_id'] ?? null,
                'voucher_id' => $discount->id,
                'user_id' => $patientId,
                'amount' => $actualConsumed,
                'service_id' => $packageBundle->id,
                'main_service_id' => $mainServiceId,
            ]);

            // Patients model extends BaseModel (not User); the logger
            // enforces that type. Same trap seen in PlanService and
            // UserVoucherService — fetch via Patients to pass the
            // strict parameter type check.
            $patient = Patients::find($patientId);
            if ($patient) {
                DB::afterCommit(static function () use ($actualConsumed, $patient, $discount, $amountLeft): void {
                    ActivityLogger::logVoucherConsumed($actualConsumed, $patient, $discount, $amountLeft);
                });
            }
        });
    }

    /**
     * Resolve the GET-side discount amounts for a configurable discount row.
     *
     * Returns [discount_price, net_amount, label] from a service price + a
     * `GetDiscountService` row. Branches on `discount_type`:
     *
     *   - complimentory: net=0, full price recorded as discount magnitude
     *   - fixed:         net = price − amount (clamped to ≥0); amount is PKR
     *   - percentage:    net = price × (1 − amount/100); amount is %
     *   - anything else: legacy fallback — treats amount as percentage
     *
     * Pre-fix, every non-complimentary row went through the percentage path —
     * a `fixed` discount of `500` was therefore interpreted as 500% off and
     * produced a negative net. Validation now blocks bad input at write
     * time (StoreConfigurableDiscountRequest / UpdateDiscountRequest), but
     * this helper also clamps net to ≥0 so any legacy dirty rows already
     * in the DB render sanely instead of breaking plan totals.
     *
     * @param  GetDiscountService  $gs
     * @return array{0: float, 1: float, 2: string}  [discount_price, net_amount, label]
     */
    private static function resolveGetDiscountAmounts(float|string $servicePrice, $gs): array
    {
        $price  = (float) $servicePrice;
        $type   = $gs->discount_type ?? 'percentage';
        $amount = (float) ($gs->discount_amount ?? 0);

        if ($type === 'complimentory') {
            return [$price, 0.0, 'Complimentary'];
        }

        if ($type === 'fixed') {
            $disc = max(0.0, min($price, $amount));
            $net  = max(0.0, $price - $disc);
            return [$disc, $net, 'PKR ' . number_format($amount, 0) . ' Off'];
        }

        // percentage (and any unrecognised legacy value, which prior to
        // validation tightening would also have hit this path)
        $pct  = max(0.0, min(100.0, $amount));
        $disc = round($price * ($pct / 100), 2);
        $net  = max(0.0, $price - $disc);

        return [$disc, $net, $amount . '% Off'];
    }
}
