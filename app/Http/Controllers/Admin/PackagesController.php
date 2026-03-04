<?php

namespace App\Http\Controllers\Admin;

use Validator;
use Carbon\Carbon;
use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Models\User;
use App\Models\Bundles;
use App\Models\SMSLogs;
use App\Helpers\Filters;
use App\Models\Accounts;
use App\Models\Activity;
use App\Models\Invoices;
use App\Models\Packages;
use App\Models\Services;
use App\Models\Settings;
use App\Models\PackageVouchers;
use App\Models\Discounts;
use App\Models\Locations;
use App\Helpers\Financelog;
use App\Helpers\JazzSMSAPI;
use App\Models\AuditTrails;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\PaymentModes;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Composer\Package\Package;
use App\Helpers\TelenorSMSAPI;
use App\Models\InvoiceDetails;
use App\Models\PackageBundles;
use App\Models\PackageService;
use App\HelperModule\ApiHelper;
use App\Models\PackageAdvances;
use App\Models\PlanInvoice;
use App\Models\UserVouchers;
use App\Models\UserHasLocations;
use App\Helpers\GeneralFunctions;
use App\Models\AuditTrailChanges;
use App\Models\BundleHasServices;
use App\Models\GetDiscountService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\BaseDiscountService;
use App\Models\ServiceHasLocations;
use App\Models\DiscountHasLocations;
use Illuminate\Support\Facades\App;
use App\Http\Controllers\Controller;
use App\Models\UserOperatorSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Helpers\Widgets\ServiceWidget;
use Illuminate\Support\Facades\Config;
use App\Helpers\Widgets\DiscountWidget;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Helpers\Invoice_Plan_Refund_Sms_Functions;
use App\Helpers\Widgets\PlanAppointmentCalculation;
use App\Exceptions\PlanException;
use App\Models\DoctorHasLocations;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\RoleHasUsers;
use App\Models\Leads;
use App\Services\MetaConversionApiService;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\Log;


class PackagesController extends Controller
{
    public $success;

    public $error;

    public $unauthorized;

    protected $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Display a listing of the package.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Gate::allows('plans_manage')) {
            return abort(401);
        }

        return view('admin.packages.index');
    }

    /**
     * Show the form for creating a new package.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        if (!Gate::allows('plans_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            // Get patient ID from route parameter
            $patientId = $request->route('id');
            
            // Use patient-specific method if patient ID is provided
            if ($patientId) {
                \Log::info('Loading patient-specific plan data for patient: ' . $patientId);
                $data = $this->planService->getCreateFormDataForPatient(ACL::getUserCentres(), (int)$patientId);
            } else {
                \Log::info('Loading general plan data (no patient ID)');
                $data = $this->planService->getCreateFormData(ACL::getUserCentres());
            }
            
            return ApiHelper::apiResponse($this->success, 'Record found.', true, $data);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Plans Create Form Data Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return ApiHelper::apiResponse($this->error, 'Failed to load form data.', false);
        }
    }

    /**
     * Return an array of location base service.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getservices(Request $request)
    {
        try {
            if (!$request->has('location_id') || !$request->location_id) {
                return ApiHelper::apiResponse($this->error, 'Location ID is required.', false);
            }

            $services = $this->planService->getServicesByLocation(
                (int) $request->location_id,
                Auth::user()->account_id
            );

            if (!empty($services)) {
                return ApiHelper::apiResponse($this->success, 'Record found', true, [
                    'service' => $services,
                ]);
            }

            return ApiHelper::apiResponse($this->success, 'Record not found', false);
        } catch (\Exception $e) {
            \Log::error('Get Services Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, 'Failed to load services.', false);
        }
    }

    /**
     * Save bundle service for bundle plan creation
     * Uses the same logic as plan creation but for bundles
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function savebundle_service(Request $request)
    {
        try {
            

            $bundleId = $request->bundle_id;
            $bundle = Bundles::find($bundleId);
            
            if (!$bundle) {
                return ApiHelper::apiResponse($this->error, 'Bundle not found', false);
            }

            $locationInfo = Locations::find($request->location_id);
            
            // Build bundle data structure
            $bundleData = [
                'qty' => '1',
                'bundle_id' => $bundle->id,
                'service_price' => $bundle->price,
                'service_name' => $bundle->name,
                'net_amount' => $request->net_amount,
                'discount_name' => '-',
                'discount_type' => '-',
                'discount_price' => '0',
                'tax_percenatage' => $locationInfo->tax_percentage ?? 0,
            ];

            // Calculate tax based on bundle's tax treatment type
            if ($bundle->tax_treatment_type_id == Config::get('constants.tax_both')) {
                $bundleData['tax_exclusive_net_amount'] = $request->net_amount;
                $bundleData['tax_price'] = ceil($request->net_amount * ($locationInfo->tax_percentage / 100));
                $bundleData['tax_including_price'] = ceil($request->net_amount + $bundleData['tax_price']);
            } else {
                $bundleData['tax_including_price'] = $request->net_amount;
                $bundleData['tax_exclusive_net_amount'] = ceil((100 * $bundleData['tax_including_price']) / ($bundleData['tax_percenatage'] + 100));
                $bundleData['tax_price'] = ceil($bundleData['tax_including_price'] - $bundleData['tax_exclusive_net_amount']);
            }

            // Get bundle services for display with proper tax calculation
            $bundleServices = BundleHasServices::with('service')
                ->where('bundle_id', $bundle->id)
                ->get();

            // Prepare services for price calculation
            $calculableServices = [];
            foreach ($bundleServices as $bundleService) {
                $calculableServices[] = [
                    'service_price' => $bundleService->calculated_price,
                    'calculated_price' => $bundleService->calculated_price,
                    'service_id' => $bundleService->service_id,
                ];
            }

            // Calculate proportional prices for each service
            $calculatedServicesPrices = Bundles::calculatePrices(
                $calculableServices,
                $bundleData['tax_exclusive_net_amount'],
                $bundleData['tax_including_price']
            );

            // Get service details with tax treatment types
            $serviceIds = array_column($calculatedServicesPrices, 'service_id');
            $servicesInfo = Services::whereIn('id', $serviceIds)->get()->keyBy('id');

            $packageServicesData = [];
            foreach ($calculatedServicesPrices as $calculatedService) {
                $serviceInfo = $servicesInfo->get($calculatedService['service_id']);
                
                if ($serviceInfo) {
                    // Determine if service price is tax-inclusive or tax-exclusive
                    $serviceTaxType = $serviceInfo->tax_treatment_type_id;
                    $isExclusive = ($serviceTaxType == Config::get('constants.tax_is_exclusive'));
                    
                    // Calculate tax for this service
                    if ($serviceTaxType == Config::get('constants.tax_both')) {
                        if ($isExclusive) {
                            $taxExclusivePrice = $calculatedService['calculated_price'];
                            $taxPrice = ceil($taxExclusivePrice * ($locationInfo->tax_percentage / 100));
                            $taxIncludingPrice = ceil($taxExclusivePrice + $taxPrice);
                        } else {
                            $taxIncludingPrice = $calculatedService['calculated_price'];
                            $taxExclusivePrice = ceil((100 * $taxIncludingPrice) / ($locationInfo->tax_percentage + 100));
                            $taxPrice = ceil($taxIncludingPrice - $taxExclusivePrice);
                        }
                    } elseif ($serviceTaxType == Config::get('constants.tax_is_exclusive')) {
                        $taxExclusivePrice = $calculatedService['calculated_price'];
                        $taxPrice = ceil($taxExclusivePrice * ($locationInfo->tax_percentage / 100));
                        $taxIncludingPrice = ceil($taxExclusivePrice + $taxPrice);
                    } else {
                        // tax_is_inclusive
                        $taxIncludingPrice = $calculatedService['calculated_price'];
                        $taxExclusivePrice = ceil((100 * $taxIncludingPrice) / ($locationInfo->tax_percentage + 100));
                        $taxPrice = ceil($taxIncludingPrice - $taxExclusivePrice);
                    }
                    
                    $packageServicesData[] = [
                        'name' => $serviceInfo->name,
                        'service_id' => $calculatedService['service_id'],
                        'tax_exclusive_price' => $taxExclusivePrice,
                        'tax_price' => $taxPrice,
                        'tax_including_price' => $taxIncludingPrice,
                        'is_consumed' => 0,
                    ];
                }
            }

            return ApiHelper::apiResponse($this->success, 'Bundle service added successfully', true, [
                'servicesData' => [
                    'service_name' => $bundle->name,
                    'service_price' => $bundle->price,
                    'discount_name' => '-',
                    'discount_type' => '-',
                    'discount_price' => '0',
                    'sold_by' => $request->sold_by ?? null,
                    'bundlesData' => array_merge($bundleData, [
                        'id' => $bundle->id, // Return original bundle ID
                    ]),
                    'packageServicesData' => $packageServicesData,
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Save Bundle Service Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, 'Failed to add bundle service: ' . $e->getMessage(), false);
        }
    }

    /**
     * Save membership service (Add button in membership creation)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function savemembership_service(Request $request)
    {
        try {
            $membershipTypeId = $request->membership_id;
            $membershipType = MembershipType::find($membershipTypeId);

            if (!$membershipType) {
                return ApiHelper::apiResponse($this->error, 'Membership type not found', false);
            }

            $locationInfo = Locations::find($request->location_id);
            $taxPercentage = $locationInfo->tax_percentage ?? 0;
            $netAmount = (float) $request->net_amount;

            // Calculate tax (tax-inclusive by default for memberships)
            $taxIncludingPrice = $netAmount;
            $taxExclusivePrice = ceil((100 * $taxIncludingPrice) / ($taxPercentage + 100));
            $taxPrice = ceil($taxIncludingPrice - $taxExclusivePrice);

            $membershipsData = [
                'id' => $membershipType->id,
                'qty' => '1',
                'membership_type_id' => $membershipType->id,
                'service_price' => $membershipType->amount,
                'service_name' => $membershipType->name,
                'net_amount' => $netAmount,
                'tax_percenatage' => $taxPercentage,
                'tax_exclusive_net_amount' => $taxExclusivePrice,
                'tax_price' => $taxPrice,
                'tax_including_price' => $taxIncludingPrice,
            ];

            return ApiHelper::apiResponse($this->success, 'Membership service added successfully', true, [
                'servicesData' => [
                    'service_name' => $membershipType->name,
                    'service_price' => $membershipType->amount,
                    'discount_name' => '-',
                    'discount_type' => '-',
                    'discount_price' => '0',
                    'sold_by' => $request->sold_by ?? null,
                    'membershipsData' => $membershipsData,
                    'packageServicesData' => [],
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Save Membership Service Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, 'Failed to add membership service: ' . $e->getMessage(), false);
        }
    }

    /**
     * Update membership plan - add payment to existing membership package
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMembershipPlan(Request $request)
    {
        try {
            $packageId = $request->package_id;
            $patientId = $request->patient_id;
            $locationId = $request->location_id;
            $appointmentId = $request->appointment_id;
            $paymentModeId = $request->payment_mode_id;
            $cashAmount = floatval($request->cash_amount ?? 0);
            $grandTotal = floatval($request->grand_total ?? 0);
            $isStudentMembership = $request->is_student_membership === '1';
            $membershipTypeId = $request->membership_type_id;

            if (!$packageId) {
                return ApiHelper::apiResponse($this->error, 'Package ID is required', false);
            }

            $package = Packages::find($packageId);
            if (!$package) {
                return ApiHelper::apiResponse($this->error, 'Package not found', false);
            }

            // Check if service is already consumed
            $isAlreadyConsumed = PackageService::where('package_id', $packageId)
                ->where('is_consumed', 1)
                ->exists();

            // Get package bundle info
            $packageBundle = PackageBundles::where('package_id', $packageId)
                ->whereNotNull('membership_code_id')
                ->first();

            // Track changes
            $paymentAdded = false;
            $documentsUploaded = false;
            $membershipConsumed = false;
            $messages = [];

            // ========================================
            // STEP 1: Handle document uploads and check student membership
            // ========================================
            $hasNewDocuments = false;
            $hasStudentDocuments = false;
            
            // Also check from database if this is a student membership (in case frontend doesn't pass it)
            $studentVerificationService = app(\App\Services\Membership\StudentVerificationService::class);
            if (!$isStudentMembership && $packageBundle && $packageBundle->membership_type_id) {
                $isStudentMembership = $studentVerificationService->isStudentMembership($packageBundle->membership_type_id);
            }
            
            \Log::info('Edit membership - document check', [
                'is_student_membership_param' => $request->is_student_membership,
                'is_student_membership' => $isStudentMembership,
                'has_files' => $request->hasFile('student_documents'),
                'membership_type_id_from_bundle' => $packageBundle ? $packageBundle->membership_type_id : null
            ]);
            
            if ($isStudentMembership) {
                // Get existing verification record
                $existingVerification = \App\Models\StudentVerification::where('package_id', $packageId)->first();
                $existingDocPaths = $existingVerification ? ($existingVerification->document_paths ?? []) : [];
                
                // Handle document removal
                $documentsToRemove = $request->documents_to_remove ? json_decode($request->documents_to_remove, true) : [];
                if (!empty($documentsToRemove)) {
                    foreach ($documentsToRemove as $docPath) {
                        // Remove from existing paths array
                        $existingDocPaths = array_filter($existingDocPaths, function($path) use ($docPath) {
                            return $path !== $docPath;
                        });
                        // Delete file from storage
                        $fullPath = storage_path('app/public/' . $docPath);
                        if (file_exists($fullPath)) {
                            unlink($fullPath);
                        }
                    }
                    $existingDocPaths = array_values($existingDocPaths); // Re-index array
                    $messages[] = count($documentsToRemove) . ' document(s) removed';
                    
                    \Log::info('Documents removed', [
                        'package_id' => $packageId,
                        'removed_count' => count($documentsToRemove),
                        'remaining_count' => count($existingDocPaths)
                    ]);
                }
                
                // Store new documents IMMEDIATELY
                $documents = $request->file('student_documents', []);
                $newStoredPaths = $this->storeStudentDocumentsImmediately($documents);
                $hasNewDocuments = !empty($newStoredPaths);
                
                // Merge existing (after removal) with new documents
                $allDocumentPaths = array_merge($existingDocPaths, $newStoredPaths);
                
                \Log::info('Student membership - document processing', [
                    'existing_after_removal' => count($existingDocPaths),
                    'new_uploaded' => count($newStoredPaths),
                    'total_documents' => count($allDocumentPaths)
                ]);
                
                // Update or create verification record
                if (!empty($allDocumentPaths)) {
                    $membershipCodeId = $packageBundle ? $packageBundle->membership_code_id : null;
                    
                    if ($existingVerification) {
                        // Update existing record
                        $existingVerification->update([
                            'document_paths' => $allDocumentPaths,
                        ]);
                    } else {
                        // Create new record
                        $studentVerificationService->createVerificationRecord([
                            'patient_id' => $patientId,
                            'membership_id' => $membershipCodeId,
                            'membership_type_id' => $membershipTypeId ?: ($packageBundle ? $packageBundle->membership_type_id : null),
                            'package_id' => $packageId,
                            'document_paths' => $allDocumentPaths,
                        ]);
                    }
                    
                    if ($hasNewDocuments) {
                        $documentsUploaded = true;
                        $messages[] = count($newStoredPaths) . ' document(s) uploaded';
                    }
                } elseif ($existingVerification && empty($allDocumentPaths)) {
                    // All documents removed - delete the verification record
                    $existingVerification->delete();
                    \Log::info('Verification record deleted - no documents remaining', ['package_id' => $packageId]);
                }
                
                // Check if student membership has documents
                $hasStudentDocuments = !empty($allDocumentPaths);
                
                \Log::info('Student membership - final document status', [
                    'has_documents' => $hasStudentDocuments,
                    'document_count' => count($allDocumentPaths)
                ]);
            }

            // ========================================
            // STEP 2: Handle payment (if provided)
            // ========================================
            if ($paymentModeId && $cashAmount > 0) {
                // Update package's updated_at
                Packages::where('id', $packageId)->update(['updated_at' => Filters::getCurrentTimeStamp()]);
                
                $packageAdvanceData = [
                    'cash_flow' => 'in',
                    'cash_amount' => $cashAmount,
                    'account_id' => Auth::user()->account_id,
                    'patient_id' => $patientId,
                    'payment_mode_id' => $paymentModeId,
                    'created_by' => Auth::user()->id,
                    'updated_by' => Auth::user()->id,
                    'package_id' => $packageId,
                    'location_id' => $locationId,
                    'appointment_id' => $appointmentId,
                    'created_at' => Filters::getCurrentTimeStamp(),
                    'updated_at' => Filters::getCurrentTimeStamp(),
                ];

                PackageAdvances::createRecord($packageAdvanceData, $package);
                $paymentAdded = true;
                $messages[] = 'Payment recorded';
                
                \Log::info('Payment added in edit', [
                    'package_id' => $packageId,
                    'cash_amount' => $cashAmount,
                    'grand_total_after' => $grandTotal
                ]);
            }

            // ========================================
            // STEP 3: Calculate if fully paid (after this payment)
            // ========================================
            $packageTotal = PackageBundles::where('package_id', $packageId)->sum('tax_including_price');
            $totalCashIn = PackageAdvances::where('package_id', $packageId)
                ->where('cash_flow', 'in')
                ->where('is_cancel', 0)
                ->sum('cash_amount');
            $isFullyPaid = $totalCashIn >= $packageTotal;

            \Log::info('Edit membership - payment status', [
                'package_id' => $packageId,
                'package_total' => $packageTotal,
                'total_cash_in' => $totalCashIn,
                'is_fully_paid' => $isFullyPaid,
                'is_student' => $isStudentMembership,
                'has_documents' => $hasStudentDocuments,
                'is_already_consumed' => $isAlreadyConsumed
            ]);

            // ========================================
            // STEP 4: Determine if membership should be consumed
            // ========================================
            // For student membership: consume only if fully paid AND has documents
            // For non-student membership: consume if fully paid
            $shouldConsume = false;
            
            if (!$isAlreadyConsumed && $isFullyPaid) {
                if ($isStudentMembership) {
                    $shouldConsume = $hasStudentDocuments;
                } else {
                    $shouldConsume = true;
                }
            }

            // ========================================
            // STEP 5: Consume membership if conditions met
            // ========================================
            if ($shouldConsume && $packageBundle) {
                // Update package_services to mark as consumed
                PackageService::where('package_id', $packageId)
                    ->where('package_bundle_id', $packageBundle->id)
                    ->update([
                        'is_consumed' => 1,
                        'consumed_at' => Filters::getCurrentTimeStamp(),
                    ]);

                // Update membership record with patient and dates
                $membershipCodeId = $packageBundle->membership_code_id;
                if ($membershipCodeId) {
                    $membershipRecord = Membership::find($membershipCodeId);
                    if ($membershipRecord) {
                        $membershipType = MembershipType::find($packageBundle->membership_type_id);
                        $durationDays = $membershipType->period ?? 365;

                        $startDate = now()->toDateString();
                        $endDate = now()->addDays($durationDays)->toDateString();

                        $membershipRecord->update([
                            'patient_id' => $patientId,
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'assigned_at' => now()->toDateString(),
                            'updated_by' => Auth::id(),
                        ]);
                        
                        \Log::info('Membership consumed in edit', [
                            'membership_code_id' => $membershipCodeId,
                            'patient_id' => $patientId
                        ]);
                    }
                }

                // Create 'out' payment entries for settled amount
                $taxExclusiveTotal = $packageBundle->tax_exclusive_net_amount;
                $taxTotal = $packageBundle->tax_price;

                $settlePaymentMode = PaymentModes::where('name', 'Settle Amount')->first();
                $settlePaymentModeId = $settlePaymentMode ? $settlePaymentMode->id : null;

                PackageAdvances::create([
                    'cash_flow' => 'out',
                    'cash_amount' => $taxExclusiveTotal,
                    'account_id' => Auth::user()->account_id,
                    'patient_id' => $patientId,
                    'payment_mode_id' => $settlePaymentModeId,
                    'created_by' => Auth::user()->id,
                    'updated_by' => Auth::user()->id,
                    'package_id' => $packageId,
                    'location_id' => $locationId,
                    'is_setteled' => 0,
                    'is_tax' => 0,
                    'created_at' => Filters::getCurrentTimeStamp(),
                    'updated_at' => Filters::getCurrentTimeStamp(),
                ]);

                if ($taxTotal > 0) {
                    PackageAdvances::create([
                        'cash_flow' => 'out',
                        'cash_amount' => $taxTotal,
                        'account_id' => Auth::user()->account_id,
                        'patient_id' => $patientId,
                        'payment_mode_id' => $settlePaymentModeId,
                        'created_by' => Auth::user()->id,
                        'updated_by' => Auth::user()->id,
                        'package_id' => $packageId,
                        'location_id' => $locationId,
                        'is_setteled' => 0,
                        'is_tax' => 1,
                        'created_at' => Filters::getCurrentTimeStamp(),
                        'updated_at' => Filters::getCurrentTimeStamp(),
                    ]);
                }

                $membershipConsumed = true;
                $messages[] = 'Membership activated';
            }

            // ========================================
            // STEP 6: Return appropriate response
            // ========================================
            if ($paymentAdded || $documentsUploaded || $membershipConsumed) {
                $message = implode(', ', $messages);
                return ApiHelper::apiResponse($this->success, $message, true);
            }

            return ApiHelper::apiResponse($this->success, 'No changes made', true);

        } catch (\Exception $e) {
            \Log::error('Update Membership Plan Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return ApiHelper::apiResponse($this->error, 'Failed to update membership: ' . $e->getMessage(), false);
        }
    }

    /**
     * Get bundles by location for bundle creation
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getbundles(Request $request)
    {
        try {
            if (!$request->has('location_id') || !$request->location_id) {
                return ApiHelper::apiResponse($this->error, 'Location ID is required.', false);
            }

            $location_id = (int) $request->location_id;
            $account_id = Auth::user()->account_id;

            // Get active bundles for the account
            $bundles = Bundles::where('account_id', $account_id)
                ->where('active', 1)
                ->whereDate('start', '<=', now())
                ->whereDate('end', '>=', now())
                ->select('id', 'name', 'price')
                ->orderBy('name', 'asc')
                ->get();

            if ($bundles->isNotEmpty()) {
                return ApiHelper::apiResponse($this->success, 'Record found', true, [
                    'bundles' => $bundles,
                ]);
            }

            return ApiHelper::apiResponse($this->success, 'No bundles found', false);
        } catch (\Exception $e) {
            \Log::error('Get Bundles Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, 'Failed to load bundles.', false);
        }
    }

    /**
     * Get membership types for membership creation
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getmemberships(Request $request)
    {
        try {
            if (!$request->has('location_id') || !$request->location_id) {
                return ApiHelper::apiResponse($this->error, 'Location ID is required.', false);
            }

            $patientId = $request->patient_id;
            $expiredMembershipTypeId = null;
            
            // Check if patient's latest membership is expired and get its type
            if ($patientId) {
                $latestMembership = Membership::where('patient_id', $patientId)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                // Get the expired membership type ID (only if expired)
                if ($latestMembership && $latestMembership->end_date < now()->format('Y-m-d')) {
                    // Get the parent membership type ID (in case the expired one was already a renewal)
                    $expiredType = MembershipType::find($latestMembership->membership_type_id);
                    if ($expiredType) {
                        // If it's a renewal, get the parent ID; otherwise use its own ID
                        $expiredMembershipTypeId = $expiredType->parent_id ?? $expiredType->id;
                    }
                }
            }

            // Get all parent membership types (always show these)
            $parentMemberships = MembershipType::where('active', 1)
                ->whereNull('parent_id')
                ->select('id', 'name', 'amount as price', 'parent_id')
                ->orderBy('name', 'asc')
                ->get();

            $memberships = $parentMemberships;

            // If patient has an expired membership, add ONLY the renewal for that specific type
            if ($expiredMembershipTypeId) {
                $renewalMembership = MembershipType::where('active', 1)
                    ->where('parent_id', $expiredMembershipTypeId)
                    ->select('id', 'name', 'amount as price', 'parent_id')
                    ->first();

                if ($renewalMembership) {
                    // Merge parent memberships with the specific renewal
                    $memberships = $parentMemberships->push($renewalMembership)->sortBy('name')->values();
                }
            }

            if ($memberships->isNotEmpty()) {
                return ApiHelper::apiResponse($this->success, 'Record found', true, [
                    'memberships' => $memberships,
                    'expired_membership_type_id' => $expiredMembershipTypeId
                ]);
            }

            return ApiHelper::apiResponse($this->success, 'No memberships found', false);
        } catch (\Exception $e) {
            \Log::error('Get Memberships Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, 'Failed to load memberships.', false);
        }
    }

    /**
     * Get membership type info (price) for membership creation
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getmembershipinfo(Request $request)
    {
        try {
            $membership_id = $request->membership_id;

            if (!$membership_id) {
                return ApiHelper::apiResponse($this->error, 'Membership ID is required.', false);
            }

            $membership = MembershipType::where('id', $membership_id)
                ->where('active', 1)
                ->first();

            if ($membership) {
                return ApiHelper::apiResponse($this->success, 'Record found', true, [
                    'net_amount' => (float) $membership->amount,
                    'membership_name' => $membership->name,
                ]);
            }

            return ApiHelper::apiResponse($this->error, 'Membership not found', false);
        } catch (\Exception $e) {
            \Log::error('Get Membership Info Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, 'Failed to load membership info.', false);
        }
    }

    /**
     * Search membership codes by keyword and check if assigned
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchMembershipCodes(Request $request)
    {
        try {
            $search = $request->search;
            $membershipTypeId = $request->membership_type_id;

            if (!$search || strlen($search) < 2) {
                return response()->json(['status' => true, 'data' => ['codes' => []]]);
            }

            $query = Membership::where('code', 'like', '%' . $search . '%')
                ->where('active', 1)
                ->whereNull('patient_id'); // Exclude codes already assigned/reserved to a patient

            // Filter by membership_type_id if provided
            // Also include codes from parent membership type if this is a renewal (child) type
            if ($membershipTypeId) {
                $membershipType = MembershipType::find($membershipTypeId);
                
                if ($membershipType && $membershipType->parent_id) {
                    // This is a renewal type, include codes from both parent and this type
                    $query->where(function($q) use ($membershipTypeId, $membershipType) {
                        $q->where('membership_type_id', $membershipTypeId)
                          ->orWhere('membership_type_id', $membershipType->parent_id);
                    });
                } else {
                    // This is a parent type, only show codes for this type
                    $query->where('membership_type_id', $membershipTypeId);
                }
            }

            $codes = $query->select('id', 'code', 'patient_id', 'membership_type_id')
                ->limit(20)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'code' => $item->code,
                        'is_assigned' => !empty($item->patient_id),
                        'patient_id' => $item->patient_id,
                        'membership_type_id' => $item->membership_type_id,
                    ];
                });

            return response()->json(['status' => true, 'data' => ['codes' => $codes]]);
        } catch (\Exception $e) {
            \Log::error('Search Membership Codes Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to search codes.']);
        }
    }

    /**
     * get discount information.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getdiscountinfo(Request $request)
    {

        if ($request->discount_id) {
            $discount_is_voucher = false;
            $service_id = $request->service_id;
            $patient_id = $request->patient_id;
            $service_data = Bundles::find($service_id);

            $discount_id = $request->discount_id;

            $discount_data = Discounts::find($discount_id);
           
            if ($discount_data->slug == 'custom') {


                return ApiHelper::apiResponse($this->success, 'custom', true, [
                    'custom_checked' => 1,
                ]);
            } else {

                if ($discount_data->type == Config::get('constants.Fixed') && $discount_data->discount_type !="voucher") {

                    $discount_type = Config::get('constants.Fixed');
                    $discount_price = $discount_data->amount;
                    $net_amount = ($service_data->price) - ($discount_data->amount);
                } else if ($discount_data->type == Config::get('constants.Percentage') && $discount_data->discount_type !="voucher") {

                    $discount_type = Config::get('constants.Percentage');
                    $discount_price = $discount_data->amount;
                    $discount_price_cal = $service_data->price * (($discount_price) / 100);
                    $net_amount = ($service_data->price) - ($discount_price_cal);
                } else if ($discount_data->type == "Configurable" && $discount_data->discount_type !="voucher") {

                    $discount_type = "Configurable";
                    $discount_price = $discount_data->amount;
                    $discount_price_cal = $service_data->price * (($discount_price) / 100);
                    $net_amount = ($service_data->price) - ($discount_price_cal);
                }else if ($discount_data->discount_type == "voucher") {
                    $patientVoucher = UserVouchers::where("user_id", $patient_id)->where("voucher_id", $discount_id)->first();
                    if ($patientVoucher) {
                        $discount_type = Config::get('constants.Fixed');
                        $discount_price = $patientVoucher->amount;
                        $discount_is_voucher = true;
                        $net_amount = ($service_data->price) - ($discount_price);
                        if($net_amount < 0){
                            $net_amount =0;
                        }
                      
                    }else{
                        $discount_type = "";
                        $discount_price = 0;
                        $discount_is_voucher = false;
                        $net_amount = $service_data->price;
                    }
                }
                return ApiHelper::apiResponse($this->success, 'Record Found', true, [
                    'discount_type' => $net_amount < 0 ? '' : $discount_type,
                    'discount_price' => $discount_price,
                    'net_amount' => $net_amount < 0 ? $service_data->price : $net_amount,
                    'custom_checked' => 0,
                    'discount_is_voucher' => $discount_is_voucher,
                ]);
            }
        }

        return ApiHelper::apiResponse($this->success, 'No Record Found', false);
    }

    /**
     * save packages services information.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function savepackages_service(Request $request)
    {
        \Log::info('=== savepackages_service (BUNDLE PATH) CALLED ===', [
            'bundle_id_from_request' => $request->bundle_id,
            'discount_id' => $request->discount_id,
            'random_id' => $request->random_id,
        ]);

        $status = true;
        $service_data = Bundles::find($request->bundle_id);
        \Log::info('savepackages_service: Bundles::find result', [
            'found' => $service_data ? true : false,
            'name' => $service_data->name ?? 'NULL',
            'id' => $service_data->id ?? 'NULL',
        ]);
        $find_package = Packages::where('random_id', $request->random_id)->first();
        if ($find_package) {
            $check_is_setteled = PackageAdvances::where([
                ['cash_flow', '=', 'out'],
                ['cash_amount', '>', 0],
                ['is_setteled', '=', '1'],
                ['package_id', '=', $find_package->id],
            ])->first();
            if ($check_is_setteled) {
                return ApiHelper::apiResponse($this->success, 'Plan is already settled. you can not add further treatment in this plan.', false, ['setteled' => 1]);
            }
        }
        $find_discount = Discounts::find($request->discount_id);

        if ($find_discount && $find_discount->type == "Configurable") {
            if ($request->is_exclusive == '') {
                $request->merge(['is_exclusive' => 1]);
            }
            // Removed duplicate service check - allow adding same service multiple times
            if ($status == true) {
                /*First we need to make the data to save in package bundle*/
                $data = $request->all();
                $location_information = Locations::find($request->location_id);
                $discount_info = Discounts::find($request->discount_id);
                $base_services = BaseDiscountService::where('discount_id', $request->discount_id)->get();
                $discounted_services = GetDiscountService::where('discount_id', $request->discount_id)->get();
                $isCategoryMode = $base_services->isNotEmpty() && $base_services->first()->is_category == 1;
                $selectedService = Services::find($request->service_id);
                $merged_services = $base_services->merge($discounted_services);
                foreach ($merged_services as $ds) {
                    // For category-mode BUY rows or same_service GET rows, use the actually selected service
                    $isBuyRow = $ds instanceof BaseDiscountService || !isset($ds->discount_type);
                    if (($isBuyRow && $isCategoryMode) || (!$isBuyRow && $ds->same_service)) {
                        $service_data1 = $selectedService;
                    } else {
                        $service_data1 = Services::find($ds->service_id);
                    }
                    if (!$service_data1) continue;

                    $data['qty'] = '1';
                    $data['bundle_id'] = $service_data1->id; // Store service_id in bundle_id column
                    $data['service_price'] = $service_data1->price;
                    if ($discount_info) {
                        $data['discount_name'] = $discount_info->name;
                    }
                    if ($service_data1->tax_treatment_type_id == Config::get('constants.tax_both')) {
                        if ($request->is_exclusive == '1') {
                            $data['tax_exclusive_net_amount'] = $ds->discount_type == "complimentory" ? 0 : $request->net_amount;
                            $data['tax_percenatage'] = $ds->discount_type == "complimentory" ? 0 : $location_information->tax_percentage;
                            $data['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data['tax_exclusive_net_amount'] * ($location_information->tax_percentage / 100));
                            $data['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data['tax_exclusive_net_amount'] + (($data['tax_exclusive_net_amount'] * $data['tax_percenatage']) / 100));
                            $data['is_exclusive'] = 1;
                        } else {
                            $data['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : $request->net_amount;
                            $data['tax_percenatage'] = $ds->discount_type == "complimentory" ? 0 : $location_information->tax_percentage;
                            $data['tax_exclusive_net_amount'] = $ds->discount_type == "complimentory" ? 0 : ceil((100 * $data['tax_including_price']) / ($data['tax_percenatage'] + 100));
                            $data['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                            $data['is_exclusive'] = 0;
                        }
                    } elseif ($service_data1->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                        $data['tax_exclusive_net_amount'] = $ds->discount_type == "complimentory" ? 0 : $request->net_amount;
                        $data['tax_percenatage'] = $ds->discount_type == "complimentory" ? 0 : $location_information->tax_percentage;
                        $data['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data['tax_exclusive_net_amount'] * ($location_information->tax_percentage / 100));
                        $data['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data['tax_exclusive_net_amount'] + (($data['tax_exclusive_net_amount'] * $data['tax_percenatage']) / 100));

                        $data['is_exclusive'] = 1;
                    } else {

                        if ($ds->discount_type == "complimentory") {
                            $data['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : $request->net_amount;
                            $data['tax_percenatage'] = $ds->discount_type == "complimentory" ? 0 : $location_information?->tax_percentage ?? '00.00';
                            $data['tax_exclusive_net_amount'] = $ds->discount_type == "complimentory" ? 0 : ceil((100 * $data['tax_including_price']) / ($data['tax_percenatage'] + 100));
                            $data['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                            $data['is_exclusive'] = 0;
                        } elseif ($ds->discount_type == "custom") {
                            $amount_after_discount = ($ds->discount_amount / 100) * $service_data1->price;

                            $data['tax_including_price'] = $service_data1->price - $amount_after_discount;
                            $data['discount_type'] = $ds->discount_type;
                            $data['discount_price'] = $ds->discount_amount;
                            $data['tax_percenatage'] = $location_information?->tax_percentage ?? '00.00';
                            $data['tax_exclusive_net_amount'] = ceil((100 * $data['tax_including_price']) / ($data['tax_percenatage'] + 100));
                            $data['tax_price'] = ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                            $data['is_exclusive'] = 0;
                        } else {

                            $data['tax_including_price'] = $request->net_amount;
                            $data['tax_percenatage'] = $location_information?->tax_percentage ?? '00.00';
                            $data['tax_exclusive_net_amount'] = ceil((100 * $data['tax_including_price']) / ($data['tax_percenatage'] + 100));
                            $data['tax_price'] = ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                            $data['is_exclusive'] = 0;
                        }
                    }
                    if ($request->discount_id == '0' || $request->discount_id == '') {
                        $data['discount_id'] = null;
                    }
                    $data['created_at'] = Filters::getCurrentTimeStamp();
                    $data['updated_at'] = Filters::getCurrentTimeStamp();
                    $packagesbundly = PackageBundles::createPackagebundle($data);
                    
                    // For plan type 'plan', create one PackageService record directly from the service
                    $calculated_services = [[
                        'service_price' => $service_data1->price,
                        'calculated_price' => $data['net_amount'] ?? $service_data1->price,
                        'service_id' => $service_data1->id,
                    ]];
                    
                    foreach ($calculated_services as $detail) {
                        if ($ds->discount_type == "complimentory") {
                            $data_service['random_id'] = $request->random_id;
                            $data_service['package_bundle_id'] = $packagesbundly->id;
                            $data_service['service_id'] = $detail['service_id'];
                            $data_service['price'] =  0;
                            $data_service['orignal_price'] = 0;
                        } elseif ($ds->discount_type == "custom") {

                            $amount_after_discount = ($ds->discount_amount / 100) * $service_data1->price;
                            $data_service['random_id'] = $request->random_id;
                            $data_service['package_bundle_id'] = $packagesbundly->id;
                            $data_service['service_id'] = $detail['service_id'];
                            $data_service['price'] = $service_data1->price - $amount_after_discount;
                            $data_service['orignal_price'] = $service_data1->price;
                        } else {
                            $data_service['random_id'] = $request->random_id;
                            $data_service['package_bundle_id'] = $packagesbundly->id;
                            $data_service['service_id'] = $detail['service_id'];
                            $data_service['price'] = $detail['calculated_price'];
                            $data_service['orignal_price'] = $detail['service_price'];
                        }

                        /*Checked it exclusive or not*/
                        if ($service_data1->tax_treatment_type_id == Config::get('constants.tax_both')) {
                            if ($request->is_exclusive == '1') {
                                $data_service['tax_exclusive_price'] = $ds->discount_type == "complimentory" ? 0 : $detail['calculated_price'];
                                $data_service['tax_percenatage'] = $location_information->tax_percentage;
                                $data_service['tax_price'] = ceil($detail['calculated_price'] * ($location_information->tax_percentage / 100));
                                $data_service['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percenatage']) / 100));
                                $data_service['is_exclusive'] = 1;
                            } else {
                                $data_service['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : $detail['calculated_price'];
                                $data_service['tax_percenatage'] = $location_information->tax_percentage;
                                $data_service['tax_exclusive_price'] = $ds->discount_type == "complimentory" ? 0 : ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percenatage'] + 100));
                                $data_service['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);

                                $data_service['is_exclusive'] = 0;
                            }
                        } elseif ($service_data1->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                            $data_service['tax_exclusive_price'] = $ds->discount_type == "complimentory" ? 0 : $detail['calculated_price'];
                            $data_service['tax_percenatage'] = $location_information->tax_percentage;
                            $data_service['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($detail['calculated_price'] * ($location_information->tax_percentage / 100));
                            $data_service['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percenatage']) / 100));

                            $data_service['is_exclusive'] = 1;
                        } else {
                            if ($ds->discount_type == "complimentory") {
                                $data_service['tax_including_price'] = 0;
                                $data_service['tax_percenatage'] = 0;
                                $data_service['tax_exclusive_price'] = 0;
                                $data_service['tax_price'] = 0;

                                $data_service['is_exclusive'] = 0;
                            } else if ($ds->discount_type == "custom") {
                                $amount_after_discount = ($ds->discount_amount / 100) * $service_data1->price;
                                $data_service['tax_including_price'] = $service_data1->price - $amount_after_discount;
                                $data_service['tax_percenatage'] = $location_information->tax_percentage;
                                $data_service['tax_exclusive_price'] = $ds->discount_type == "complimentory" ? 0 : ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percenatage'] + 100));
                                $data_service['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);
                                $data_service['is_exclusive'] = 0;
                            } else {
                                $data_service['tax_including_price'] = $ds->discount_type == "complimentory" ? 0 : $detail['calculated_price'];
                                $data_service['tax_percenatage'] = $location_information->tax_percentage;
                                $data_service['tax_exclusive_price'] = $ds->discount_type == "complimentory" ? 0 : ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percenatage'] + 100));
                                $data_service['tax_price'] = $ds->discount_type == "complimentory" ? 0 : ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);
                                $data_service['is_exclusive'] = 0;
                            }
                        }
                        $data_service['created_at'] = Filters::getCurrentTimeStamp();
                        $data_service['updated_at'] = Filters::getCurrentTimeStamp();
                        $packageservice = PackageService::createPackageService($data_service);
                    }
                    $total = str_replace(',', '', $request->package_total);


                    if ($total == '') {
                        $total = 0;
                    }

                    $total = number_format((float) $total + (float) $packagesbundly->tax_including_price);
                    /*Set variables for return to show information*/
                    $net_amount = $packagesbundly->net_amount;
                    // For configurable discounts, bundle_id contains service_id, so read from Services table
                    $service_name = $service_data1->name;
                    $service_price = $packagesbundly->service_price;

                    /*use user giving attributes for custom package*/

                    if ($request->discount_id == '0' || $request->discount_id == null) {
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
                        'random_id' => $request->random_id,
                        'service_name' => $service_name,
                        'service_price' => $service_price,
                        'discount_name' => $discount_name,
                        'discount_type' => $discount_type,
                        'discount_price' => $discount_price,
                        'net_amount' => $net_amount,
                        'total' =>  str_replace(',', '', $total),
                    ];
                }

                $grand_total = str_replace(',', '', $request->package_total);
                if ($grand_total == '') {
                    $grand_total = 0;
                }
                $package_id = Packages::where('random_id', $request->random_id)->first();
                if ($package_id) {
                    $sum_services_price = PackageBundles::where('package_id', $package_id->id)->sum('tax_including_price');

                    $grand_total =  (float) $sum_services_price;
                    $myarray[0]['grand_total'] =  $grand_total;
                } else {
                    $sum_services_price = PackageBundles::where('random_id', $request->random_id)->sum('tax_including_price');
                    $grand_total =  (float) $sum_services_price;
                    $myarray[0]['grand_total'] =  $grand_total;
                }

                // Return first element to match frontend expectation
                return ApiHelper::apiResponse($this->success, 'Record found', true, [
                    'myarray' => $myarray[0] ?? $myarray,
                ]);
            }

            return ApiHelper::apiResponse($this->success, 'No Record found', false);
        } else {


            /*Total belongs to total Amount that increase when we enter new bundle*/
            $total = str_replace(',', '', $request->package_total); //filter_var($request->package_total, FILTER_SANITIZE_NUMBER_INT);
            if ($total == '') {
                $total = 0;
            }
            if ($request->is_exclusive == '') {
                $request->merge(['is_exclusive' => 1]);
            }
            // Removed duplicate service check - allow adding same service multiple times
            if ($status == true) {
                /*First we need to make the data to save in package bundle*/
                $data = $request->all();
                $location_information = Locations::find($request->location_id);

                $discount_info = Discounts::find($request->discount_id);

                $data['qty'] = '1';
                $data['bundle_id'] = $service_data->id;
                $data['service_price'] = $service_data->price;

                if ($discount_info) {
                    $data['discount_name'] = $discount_info->name;
                }
                /*Checked it exclusive or not*/
                if ($service_data->tax_treatment_type_id == Config::get('constants.tax_both')) {
                    if ($request->is_exclusive == '1') {
                        $data['tax_exclusive_net_amount'] = $request->net_amount;
                        $data['tax_percenatage'] = $location_information->tax_percentage;
                        $data['tax_price'] = ceil($data['tax_exclusive_net_amount'] * ($location_information->tax_percentage / 100));
                        $data['tax_including_price'] = ceil($data['tax_exclusive_net_amount'] + (($data['tax_exclusive_net_amount'] * $data['tax_percenatage']) / 100));

                        $data['is_exclusive'] = 1;
                    } else {
                        $data['tax_including_price'] = $request->net_amount;
                        $data['tax_percenatage'] = $location_information->tax_percentage;
                        $data['tax_exclusive_net_amount'] = ceil((100 * $data['tax_including_price']) / ($data['tax_percenatage'] + 100));
                        $data['tax_price'] = ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                        $data['is_exclusive'] = 0;
                    }
                } elseif ($service_data->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                    $data['tax_exclusive_net_amount'] = $request->net_amount;
                    $data['tax_percenatage'] = $location_information->tax_percentage;
                    $data['tax_price'] = ceil($data['tax_exclusive_net_amount'] * ($location_information->tax_percentage / 100));
                    $data['tax_including_price'] = ceil($data['tax_exclusive_net_amount'] + (($data['tax_exclusive_net_amount'] * $data['tax_percenatage']) / 100));

                    $data['is_exclusive'] = 1;
                } else {
                    $data['tax_including_price'] = $request->net_amount;
                    $data['tax_percenatage'] = $location_information?->tax_percentage ?? '00.00';
                    $data['tax_exclusive_net_amount'] = ceil((100 * $data['tax_including_price']) / ($data['tax_percenatage'] + 100));
                    $data['tax_price'] = ceil($data['tax_including_price'] - $data['tax_exclusive_net_amount']);

                    $data['is_exclusive'] = 0;
                }
                /*In case If you not select any discount*/
                if ($request->discount_id == '0' || $request->discount_id == '') {
                    $data['discount_id'] = null;
                }
                $data['created_at'] = Filters::getCurrentTimeStamp();
                $data['updated_at'] = Filters::getCurrentTimeStamp();
                /*date is develop to save package bundle*/

                /*Save package bundle information*/
                $packagesbundly = PackageBundles::createPackagebundle($data);

                /*Get the package service information*/
                $bundle_details = BundleHasServices::where('bundle_id', '=', $packagesbundly->bundle_id)->get();
                $calculable_servcies = [];

                foreach ($bundle_details as $detail) {
                    $calculable_servcies[] = [
                        'service_price' => $detail->calculated_price,
                        'calculated_price' => $detail->calculated_price,
                        'service_id' => $detail->service_id,
                    ];
                }
                /*calculate price of services according to their prices*/
                $calculated_services = Bundles::calculatePrices($calculable_servcies, $data['service_price'], $data['net_amount']);

                /*Second we need to make the data to save in package services*/
                foreach ($calculated_services as $detail) {

                    $data_service['random_id'] = $request->random_id;
                    $data_service['package_bundle_id'] = $packagesbundly->id;
                    $data_service['service_id'] = $detail['service_id'];
                    $data_service['price'] = $detail['calculated_price'];
                    $data_service['orignal_price'] = $detail['service_price'];

                    /*Checked it exclusive or not*/
                    if ($service_data->tax_treatment_type_id == Config::get('constants.tax_both')) {
                        if ($request->is_exclusive == '1') {
                            $data_service['tax_exclusive_price'] = $detail['calculated_price'];
                            $data_service['tax_percenatage'] = $location_information->tax_percentage;
                            $data_service['tax_price'] = ceil($detail['calculated_price'] * ($location_information->tax_percentage / 100));
                            $data_service['tax_including_price'] = ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percenatage']) / 100));

                            $data_service['is_exclusive'] = 1;
                        } else {
                            $data_service['tax_including_price'] = $detail['calculated_price'];
                            $data_service['tax_percenatage'] = $location_information->tax_percentage;
                            $data_service['tax_exclusive_price'] = ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percenatage'] + 100));
                            $data_service['tax_price'] = ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);

                            $data_service['is_exclusive'] = 0;
                        }
                    } elseif ($service_data->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                        $data_service['tax_exclusive_price'] = $detail['calculated_price'];
                        $data_service['tax_percenatage'] = $location_information->tax_percentage;
                        $data_service['tax_price'] = ceil($detail['calculated_price'] * ($location_information->tax_percentage / 100));
                        $data_service['tax_including_price'] = ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percenatage']) / 100));

                        $data_service['is_exclusive'] = 1;
                    } else {
                        $data_service['tax_including_price'] = $detail['calculated_price'];
                        $data_service['tax_percenatage'] = $location_information->tax_percentage;
                        $data_service['tax_exclusive_price'] = ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percenatage'] + 100));
                        $data_service['tax_price'] = ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);

                        $data_service['is_exclusive'] = 0;
                    }
                    $data_service['created_at'] = Filters::getCurrentTimeStamp();
                    $data_service['updated_at'] = Filters::getCurrentTimeStamp();

                    $packageservice = PackageService::createPackageService($data_service);
                }
                /*calculate package value to return*/
                $total = number_format((float) $total + (float) $packagesbundly->tax_including_price);

                /*Set variables for return to show information*/
                $net_amount = $packagesbundly->net_amount;
                $service_name = $packagesbundly->bundle->name;
                $service_price = $packagesbundly->service_price;

                /*use user giving attributes for custom package*/

                if ($request->discount_id == '0' || $request->discount_id == null) {
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
                    'random_id' => $request->random_id,
                    'service_name' => $service_name,
                    'service_price' => $service_price,
                    'discount_name' => $discount_name,
                    'discount_type' => $discount_type,
                    'discount_price' => $discount_price,
                    'net_amount' => $net_amount,
                    'total' => $total,
                ];

                return ApiHelper::apiResponse($this->success, 'Record found', true, [
                    'myarray' => $myarray,
                ]);
            }

            return ApiHelper::apiResponse($this->success, 'No Record found', false);
        }
    }
    /**
     * Add service/bundle to package (optimized)
     */
    public function makePackagesServicesData(Request $request)
    {
        \Log::info('=== makePackagesServicesData (POST BUNDLE PATH) CALLED ===', [
            'bundle_id_from_request' => $request->bundle_id,
            'discount_id' => $request->discount_id,
            'random_id' => $request->random_id,
        ]);

        // Validate required fields
        $validator = Validator::make($request->all(), [
            'bundle_id' => 'required|integer|exists:services,id',
            'location_id' => 'required|integer|exists:locations,id',
            'user_id' => 'required|integer|exists:users,id',
            'random_id' => 'required|string',
            'net_amount' => 'required|numeric|min:0',
            'sold_by' => 'nullable|integer|exists:users,id',
            'discount_id' => 'nullable|integer|exists:discounts,id',
            'discount_price' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|string',
            'is_exclusive' => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->error, 'Validation failed', false, [
                'errors' => $validator->errors()
            ]);
        }

        try {
            $servicesData = $this->planService->addServiceToPackage($request->all());

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'servicesData' => $servicesData,
            ]);
        } catch (PlanException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        } catch (\Exception $e) {
            \Log::error('Make Packages Services Data Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, 'Failed to add service to package.', false);
        }
    }
    /**
     * get discount information for custom package.
     *
     * @return Response
     */
    public function getdiscountinfocustom(Request $request)
    {
        $status = true;
        $service_id = $request->service_id;
        $service_data = Bundles::find($service_id);
        $discount_id = $request->discount_id;
        $discount_data = Discounts::find($discount_id);
        if ($discount_data->slug == 'custom') {
            $discount_id = $request->discount_id;
        } else {
            if($discount_data->discount_type == "voucher"){
                $discountValue = UserVouchers::where("user_id", $request->patient_id)->where("voucher_id", $discount_id)->first();
                if ($discountValue) {
                    $request->discount_value = $discountValue->amount;
                }else{
                    $request->discount_value = 0;
                }
            }else{
                $request->discount_value = $discount_data->amount;
            }
        }
        if ($discount_data->type == 'Fixed' && $discount_data->discount_type != 'voucher') {
            if ($request->discount_type == Config::get('constants.Fixed')) {
                if ($request->discount_value > $discount_data->amount || $request->discount_value > $service_data->price) {
                    return false;
                }
                $discount_type = Config::get('constants.Fixed');
                $discount_price = $request->discount_value;
                $discount_price_in_percentage = ($discount_price / $service_data->price) * 100;
                $net_amount = ($service_data->price) - ($discount_price);
            } else {
                $discount_type = Config::get('constants.Percentage');
                $discount_price = $request->discount_value;
                $discount_price_cal = ($discount_data->amount / $service_data->price) * 100;
                if ($request->discount_value > $discount_price_cal) {
                    $status = false;
                }
                $amount_after_per = ($request->discount_value / 100) * $service_data->price;
                $net_amount = $service_data->price - $amount_after_per;
            }
        }else if($discount_data->type == 'Fixed' && $discount_data->discount_type == 'voucher'){
            $discountValue = UserVouchers::where("user_id", $request->patient_id)->where("voucher_id", $discount_id)->first();
            if($discountValue){
                $discount_type = Config::get('constants.Fixed');
                $discount_price = $discountValue->amount;
                $discount_price_in_percentage = ($discount_price / $service_data->price) * 100;
                $net_amount = ($service_data->price) - ($discount_price);
                if($net_amount < 0){
                    $net_amount =0;
                }
            }else{
                $discount_price=0;
                $net_amount = ($service_data->price) - ($discount_price);
            }
            
        } else if ($discount_data->type == 'Percentage' && $discount_data->discount_type == 'voucher') {
            // For percentage vouchers, skip limit check
            $discountValue = UserVouchers::where("user_id", $request->patient_id)->where("voucher_id", $discount_id)->first();
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
            if ($request->discount_type == Config::get('constants.Fixed')) {
                $discount_price = $request->discount_value;
                $discount_price_in_percentage = ($discount_price / $service_data->price) * 100;
                // Skip limit check for vouchers
                if ($discount_data->discount_type != 'voucher' && $discount_price_in_percentage > $discount_data->amount) {
                    return false;
                }
                $net_amount = ($service_data->price) - ($request->discount_value);
            } else {
                // Skip limit check for vouchers
                if ($discount_data->discount_type != 'voucher' && $request->discount_value > $discount_data->amount) {
                    return false;
                }
                $discount_price = $request->discount_value;
                $discount_price_in_percentage = ($request->discount_value / 100) * $service_data->price;
                $net_amount = ($service_data->price) - ($discount_price_in_percentage);
            }
        }

        if ($status == true) {

            return ApiHelper::apiResponse($this->success, 'Net Amount', true, [
                'net_amount' => $net_amount < 0 ? 0 : $net_amount,
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'Net Amount', false);
    }

    /**
     * delete serive from packages
     *
     * @param request
     */
    public function deletepackagesservice(Request $request)
    {
        $packageBundle = PackageBundles::find($request->id);

        // Block deletion if this bundle's own services are consumed
        $status = PackageService::where([
            ['package_bundle_id', '=', $request->id],
            ['is_consumed', '=', '1'],
        ])->first();

        if ($status) {
            return ApiHelper::apiResponse($this->success, 'Unable to delete consumed service.', false, ['del' => 1]);
        }

        // If this bundle belongs to a config group, block if ANY service in the group is consumed
        if ($packageBundle && $packageBundle->config_group_id) {
            $groupHasConsumed = PackageService::join('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
                ->where('package_bundles.config_group_id', $packageBundle->config_group_id)
                ->where('package_services.is_consumed', '1')
                ->exists();

            if ($groupHasConsumed) {
                return ApiHelper::apiResponse($this->success, 'Cannot delete. A service in this configurable discount group has been consumed.', false, ['del' => 1]);
            }
        }

        // All checks passed — proceed with deletion
        $packageService = PackageBundles::find($request->id);
        $findPackage = Packages::find($packageService->package_id);
        if ($findPackage) {
            $packageVoucher = PackageVouchers::where('package_random_id',$packageService->random_id)->where('main_service_id',$packageService->bundle_id)->first();
            if($packageVoucher){
               
                $packageVoucherAmount = $packageVoucher->amount;
                $findUserVoucher = UserVouchers::where('voucher_id',$packageVoucher->voucher_id)->where('user_id',$findPackage->patient_id)->first();
                if($findUserVoucher){
                    $findUserVoucher->update(['amount' => $findUserVoucher->amount + $packageVoucherAmount]);
                }
                $packageVoucher->delete();
            }

        }
        
        if ($request->package_total == '') {
            $request->merge(['package_total' => 0]);
        }
        $package_total = str_replace(',', '', $request->package_total); //filter_var($request->package_total, FILTER_SANITIZE_NUMBER_INT);

        $total = number_format(round(($package_total - $packageService->tax_including_price)));

        PackageService::where('package_bundle_id', '=', $request->id)->delete();

        PackageBundles::find($request->id)->forcedelete();
        // $checkPackageVoucher = PackageVouchers::where('package_random_id',$packageService->random_id)->first();
        // if($checkPackageVoucher){
        //     $checkPackageVoucher->delete();
        // }
        $old_total = PackageService::where('random_id', $packageService->random_id)->sum('tax_including_price');
        if ($request->update_status == 1) {
            if ($packageService->package_id) {
                // Update only total_price without touching updated_at
                Packages::where('id', $packageService->package_id)->update(['total_price' => $total]);
            }
        }

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'total' => $total,
            'id' => $request->id,
            'old_total' => $old_total
        ]);
    }
    public function deleteconfpackagesservice(Request $request)
    {
        // Block if ANY service in the config group is consumed (atomic group deletion)
        $hasConsumedInGroup = PackageService::where([
            ['base_service_id', '=', $request->id],
            ['is_consumed', '=', '1'],
        ])->exists();

        if ($hasConsumedInGroup) {
            return ApiHelper::apiResponse($this->success, 'Cannot delete. A service in this configurable discount group has been consumed.', false, ['del' => 1]);
        }

        $packageService = PackageBundles::where('base_service_id', $request->id)->first();

        if ($request->package_total == '') {
            $request->merge(['package_total' => 0]);
        }
        $package_total = str_replace(',', '', $request->package_total); //filter_var($request->package_total, FILTER_SANITIZE_NUMBER_INT);

        $total = $package_total - $packageService->tax_including_price;

        PackageService::where('base_service_id', '=', $request->id)->delete();

        PackageBundles::where('base_service_id', $request->id)->forcedelete();

        if ($request->update_status == 1) {
            if ($packageService->package_id) {
                // Only update total_price without touching updated_at (deleting service should not update timestamp)
                Packages::where('id', $packageService->package_id)->update(['total_price' => $total]);
            }
        }

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'total' => $total,
            'id' => $request->id,
        ]);
    }
    /**
     * delete serive from packages
     *
     * @param request
     */
    public function deletepackagesexclusive(Request $request)
    {
        $data = $request->all();
        if (isset($data['random_id']) && $data['random_id']) {
            PackageService::where('random_id', '=', $request->random_id)->forcedelete();
            PackageBundles::where('random_id', '=', $request->random_id)->forcedelete();

            return response()->json([
                'status' => true,
            ]);
        }

        return response()->json([
            'status' => false,
        ]);
    }

    /**
     * save package
     *
     * @param request
     */
    /**
     * Save plan package (optimized)
     */
    public function savepackages(Request $request)
    {
        try {
            // IMPORTANT: Store student documents IMMEDIATELY at the start of the request
            // before any other processing can consume/delete the temp files
            $storedDocumentPaths = [];
            if ($request->hasFile('student_documents')) {
                $storedDocumentPaths = $this->storeStudentDocumentsImmediately($request->file('student_documents'));
                \Log::info('Documents stored at controller entry', [
                    'count' => count($storedDocumentPaths),
                    'paths' => $storedDocumentPaths
                ]);
            }
            
            // Pass the full request object and pre-stored document paths
            $data = $request->all();
            $data['pre_stored_document_paths'] = $storedDocumentPaths;
            
            $result = $this->planService->savePlanPackage($data, $request);
            
            return response()->json($result);
        } catch (PlanException $e) {
            \Log::error('Save Packages Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\Exception $e) {
            \Log::error('Save Packages Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while saving the package'
            ]);
        }
    }
    
    /**
     * Store student documents immediately to prevent temp file loss
     */
    private function storeStudentDocumentsImmediately($documents): array
    {
        $storedPaths = [];
        
        if (empty($documents)) {
            return $storedPaths;
        }
        
        // Ensure the directory exists
        $storagePath = storage_path('app/public/student_verifications');
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        
        foreach ($documents as $index => $document) {
            if ($document instanceof \Illuminate\Http\UploadedFile && $document->isValid()) {
                try {
                    $extension = $document->getClientOriginalExtension() ?: 'jpg';
                    $filename = 'student_doc_' . time() . '_' . $index . '_' . uniqid() . '.' . $extension;
                    
                    // Move the file immediately
                    $document->move($storagePath, $filename);
                    
                    $path = 'student_verifications/' . $filename;
                    $storedPaths[] = $path;
                    
                    \Log::info('Document stored immediately', [
                        'path' => $path,
                        'original_name' => $document->getClientOriginalName()
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to store document immediately', [
                        'index' => $index,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        return $storedPaths;
    }

    /**
     * Mark appointment status as converted
     * Conversion Logic:
     * 1. Find the latest arrived consultation for the patient (appointment_type_id=1, base_appointment_status_id=arrived)
     * 2. Get the invoice creation date of this consultation
     * 3. Check if a service is added on/after invoice creation date in any package for this patient
     * 4. Check if this is the FIRST payment after invoice creation date (no prior payments exist)
     * 5. If all conditions met, mark the consultation as converted and send Meta event
     * 
     * NOTE: If consultation is already converted OR this is 2nd/3rd payment OR no new service added,
     *       do NOT mark as converted and do NOT send Meta event
     * 
     * @param int $appointment_id - The appointment being processed (used to get account_id and patient context)
     * @param int $package_id - The package where service/payment was added
     * @param float $payment_amount - The payment amount for Meta event
     */
    private static function markAppointmentAsConverted($appointment_id, $package_id = null, $payment_amount = null)
    {
        if (!$appointment_id || !$package_id) {
            \Log::info('markAppointmentAsConverted: Missing appointment_id or package_id');
            return;
        }
        
        $appointment = Appointments::find($appointment_id);
        if (!$appointment) {
            \Log::info('markAppointmentAsConverted: Appointment not found');
            return;
        }
        
        $package = Packages::find($package_id);
        if (!$package) {
            \Log::info('markAppointmentAsConverted: Package not found');
            return;
        }
        
        // Get the arrived and converted appointment statuses
        $arrivedStatus = AppointmentStatuses::where([
            'account_id' => $appointment->account_id,
            'is_arrived' => 1
        ])->first();
        
        $convertedStatus = AppointmentStatuses::where([
            'account_id' => $appointment->account_id,
            'is_converted' => 1
        ])->first();
        
        if (!$arrivedStatus || !$convertedStatus) {
            \Log::info('markAppointmentAsConverted: Arrived or Converted status not found');
            return;
        }
        
        // Step 1: Find the latest arrived consultation for this patient
        // Only look for consultations that are still in "arrived" status (not already converted)
        $latestArrivedConsultation = Appointments::where([
                'patient_id' => $package->patient_id,
                'appointment_type_id' => 1, // Consultation
                'base_appointment_status_id' => $arrivedStatus->id
            ])
            ->whereNull('deleted_at')
            ->orderBy('scheduled_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();
        
        if (!$latestArrivedConsultation) {
            \Log::info('markAppointmentAsConverted: No arrived consultation found for patient (may already be converted)', [
                'patient_id' => $package->patient_id
            ]);
            return;
        }
        
        \Log::info('markAppointmentAsConverted: Found latest arrived consultation', [
            'appointment_id' => $latestArrivedConsultation->id,
            'patient_id' => $package->patient_id
        ]);
        
        // Step 2: Get the invoice creation date of this consultation
        $consultationInvoice = \App\Models\Invoices::where('appointment_id', $latestArrivedConsultation->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'asc')
            ->first();
        
        if (!$consultationInvoice) {
            \Log::info('markAppointmentAsConverted: No invoice found for consultation', [
                'appointment_id' => $latestArrivedConsultation->id
            ]);
            return;
        }
        
        $invoiceCreatedAt = $consultationInvoice->created_at;
        $invoiceDate = \Carbon\Carbon::parse($invoiceCreatedAt)->format('Y-m-d');
        
        \Log::info('markAppointmentAsConverted: Invoice found', [
            'invoice_id' => $consultationInvoice->id,
            'invoice_date' => $invoiceDate
        ]);
        
        // Step 3: Check if a service is added on/after invoice creation date in any package for this patient
        $patientPackageIds = Packages::where('patient_id', $package->patient_id)
            ->whereNull('deleted_at')
            ->pluck('id');
        
        $packageBundleIds = PackageBundles::whereIn('package_id', $patientPackageIds)->pluck('id');
        
        $serviceAfterInvoice = PackageService::whereIn('package_bundle_id', $packageBundleIds)
            ->whereDate('created_at', '>=', $invoiceDate)
            ->exists();
        
        if (!$serviceAfterInvoice) {
            \Log::info('markAppointmentAsConverted: No service found on/after invoice date - not converting', [
                'invoice_date' => $invoiceDate
            ]);
            return;
        }
        
        // Step 4: Check if this is the FIRST payment after invoice creation date
        // Count how many payments exist on/after invoice date (excluding the current one being added)
        $existingPaymentsCount = PackageAdvances::whereIn('package_id', $patientPackageIds)
            ->where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->whereNull('deleted_at')
            ->whereDate('created_at', '>=', $invoiceDate)
            ->count();
        
        // If more than 1 payment exists (current + previous), this is not the first payment
        // Note: The current payment is already saved when this function is called, so count > 1 means duplicate
        if ($existingPaymentsCount > 1) {
            \Log::info('markAppointmentAsConverted: This is not the first payment after invoice date - not converting', [
                'invoice_date' => $invoiceDate,
                'existing_payments_count' => $existingPaymentsCount
            ]);
            return;
        }
        
        \Log::info('markAppointmentAsConverted: Conversion criteria met (first payment + service after invoice), marking as converted', [
            'appointment_id' => $latestArrivedConsultation->id,
            'invoice_date' => $invoiceDate
        ]);
        
        // Step 5: Mark the consultation as converted
        $latestArrivedConsultation->update([
            'base_appointment_status_id' => $convertedStatus->id,
            'appointment_status_id' => $convertedStatus->id,
            'converted_at' => now()
        ]);
        
        // Log activity for conversion
        $patient = \App\Models\Patients::find($package->patient_id);
        $location = Locations::with('city')->find($latestArrivedConsultation->location_id);
        $service = Services::find($latestArrivedConsultation->service_id);
        
        // Log appointment converted activity
        \App\Helpers\ActivityLogger::logAppointmentConverted($latestArrivedConsultation, $patient, $location, $service, $payment_amount, $package_id);
        
        // Also update lead status to converted and log it
        if ($latestArrivedConsultation->lead_id) {
            $lead = Leads::find($latestArrivedConsultation->lead_id);
            if ($lead) {
                $convertedLeadStatus = \App\Models\LeadStatuses::where([
                    'account_id' => $latestArrivedConsultation->account_id,
                    'is_converted' => 1
                ])->first();
                
                if ($convertedLeadStatus) {
                    $lead->update(['lead_status_id' => $convertedLeadStatus->id]);
                    \App\Helpers\ActivityLogger::logLeadConverted($lead, $latestArrivedConsultation, $location, $service, $payment_amount);
                }
            }
        }
        
        // Send Meta CAPI event
        self::sendMetaConvertedEvent($latestArrivedConsultation, $package_id, $payment_amount);
    }
    
    /**
     * Send Meta CAPI event for converted status
     * 
     * @param Appointments $appointment
     * @param int $package_id
     * @param float $payment_amount
     */
    private static function sendMetaConvertedEvent($appointment, $package_id, $payment_amount)
    {
        if (!$appointment || !$appointment->lead_id) {
            return;
        }
        
        $lead = Leads::find($appointment->lead_id);
        if (!$lead) {
            return;
        }
        
        // Check if Meta event was already sent for this lead (to prevent duplicates)
        // We check if any appointment for this lead already has meta_purchase_sent flag
        $alreadySent = Appointments::where('lead_id', $lead->id)
            ->where('meta_purchase_sent', 1)
            ->exists();
        
        if ($alreadySent) {
            \Log::info('Meta CAPI converted event already sent for this lead, skipping', [
                'lead_id' => $lead->id,
                'appointment_id' => $appointment->id
            ]);
            return;
        }
        
        try {
            $metaService = new MetaConversionApiService();
            // Use appointment_id as lead_id for event_id if meta_lead_id is null
            $eventLeadId = $lead->meta_lead_id ?? 'apt_' . $appointment->id;
            $metaService->sendLeadStatus(
                $lead->phone,
                'converted',
                $eventLeadId,
                $lead->email,
                'PKR',
                $payment_amount ?? 0
            );
            
            // Mark this appointment as having sent the Meta purchase event
            $appointment->update(['meta_purchase_sent' => 1]);
            
            \Log::info('Meta CAPI converted event sent', [
                'lead_id' => $lead->id,
                'appointment_id' => $appointment->id,
                'event_lead_id' => $eventLeadId
            ]);
        } catch (\Exception $e) {
            \Log::error('Meta CAPI converted event failed: ' . $e->getMessage());
        }
    }

    /**
     * Get service info
     *
     * @param request
     * @return mixed
     */
    public function getserviceinfo(Request $request)
    {
        /*because now we not give any discount to package if package have no permission to use. for this we introduce that empty collection */
        $discounts = Collection::make();
        /*end*/
        $today = Carbon::now()->toDateString();

        // Get logged-in user's role IDs
        $userRoleIds = Auth::user()->user_roles()->pluck('role_id')->toArray();
        $isSuperAdmin = Auth::user()->hasRole('Super-Admin');

        // Check if patient has an active membership
        $patientActiveMembership = null;
        $patientMembershipTypeId = null;
        
        if ($request->patient_id) {
            // Always pick the latest assigned membership (by assigned_at timestamp)
            $patientActiveMembership = Membership::where('patient_id', $request->patient_id)
                ->where('active', 1)
                ->whereDate('end_date', '>=', $today)
                ->orderBy('assigned_at', 'desc')
                ->first();
            
            if ($patientActiveMembership) {
                $patientMembershipTypeId = $patientActiveMembership->membership_type_id;
            }
        }

        $bundle = Bundles::find($request->bundle_id);

        if ($bundle && $bundle->type == 'single') {

            
            $bundleService = BundleHasServices::where([
                'bundle_id' => $bundle->id,
            ])->first();

            $service_id = $bundleService->service_id;

            $location_id = $request->location_id;

            $discountIds = DiscountWidget::loadPlanDsicountByLocationService($location_id, $service_id, Auth::User()->account_id);
           
            $generalDiscountsQuery = Discounts::whereIn('id', $discountIds)
            ->where('discount_type', '!=', 'voucher')
            ->where('active', '=', '1')
            ->whereDate('start', '<=', $today)
            ->whereDate('end', '>=', $today);

            // Apply role filter only if not super admin
            if (!$isSuperAdmin) {
                $generalDiscountsQuery->whereHas('roles', function($query) use ($userRoleIds) {
                    $query->whereIn('role_id', $userRoleIds);
                });
            }

            // Apply membership-based discount filtering using customer_type_id column
            if ($patientActiveMembership && $patientMembershipTypeId) {
                // Patient has active membership - show:
                // 1. Discounts where customer_type_id matches patient's membership_type_id
                // 2. Regular discounts with no customer_type_id (null)
                $generalDiscountsQuery->where(function($query) use ($patientMembershipTypeId) {
                    $query->where('customer_type_id', $patientMembershipTypeId)
                          ->orWhereNull('customer_type_id');
                });
            } else {
                // Patient has no membership - only show discounts with no customer_type_id
                $generalDiscountsQuery->whereNull('customer_type_id');
            }

            $generalDiscounts = $generalDiscountsQuery->get();

        // Fetch VOUCHER discounts (user-specific)
        $voucherDiscounts = Collection::make();
        $checkUserVouchers = UserVouchers::where('user_id', $request->patient_id)
            ->pluck('voucher_id')
            ->toArray();
         
        
        if ($checkUserVouchers) {
            // Get voucher discounts that match BOTH location/service AND user assignment
            $voucherDiscountsQuery = Discounts::whereIn('id', $discountIds)
                ->whereIn('id', $checkUserVouchers)
                ->where('discount_type', '=', 'voucher');

            // Apply role filter only if not super admin
            

            $voucherDiscounts = $voucherDiscountsQuery->get();
        }

        // Merge both collections
        $discounts = $generalDiscounts->merge($voucherDiscounts);
           
            
        } else {
           
            if ($bundle && $bundle->apply_discount == '1') {
                $bundleServices = BundleHasServices::where([
                    'bundle_id' => $bundle->id,
                ])->get();
                foreach ($bundleServices as $bundleService) {
                    $service_id = $bundleService->service_id;
                    $location_id = $request->location_id;
                    $discountIds[] = DiscountWidget::loadPlanDsicountByLocationService($location_id, $service_id, Auth::User()->account_id);
                    
          
                }
                $uniq_array = [];
                foreach ($discountIds as $discountId) {
                    foreach ($discountId as $singledata) {
                        if (!in_array($singledata, $uniq_array)) {
                            $uniq_array[] = $singledata;
                        }
                    }
                }
               // Fetch NON-VOUCHER discounts
            $generalDiscountsQuery = Discounts::whereIn('id', $uniq_array)
                ->where('discount_type', '!=', 'voucher')
                ->where('active', '=', '1')
                ->whereDate('start', '<=', $today)
                ->whereDate('end', '>=', $today);

            // Apply role filter only if not super admin
            if (!$isSuperAdmin) {
                $generalDiscountsQuery->whereHas('roles', function($query) use ($userRoleIds) {
                    $query->whereIn('role_id', $userRoleIds);
                });
            }

            // Apply membership-based discount filtering
            if ($patientActiveMembership && !empty($membershipDiscountIds)) {
                // Patient has active membership - show:
                // 1. Discounts linked to their membership type
                // 2. Regular discounts NOT linked to any membership type
                $generalDiscountsQuery->where(function($query) use ($membershipDiscountIds, $allMembershipLinkedDiscountIds) {
                    $query->whereIn('id', $membershipDiscountIds)
                          ->orWhereNotIn('id', $allMembershipLinkedDiscountIds);
                });
            } elseif (!empty($allMembershipLinkedDiscountIds)) {
                // Patient has no membership - exclude discounts linked to any membership type
                $generalDiscountsQuery->whereNotIn('id', $allMembershipLinkedDiscountIds);
            }

            $generalDiscounts = $generalDiscountsQuery->get();

            // Fetch VOUCHER discounts
            $voucherDiscounts = Collection::make();
            $checkUserVouchers = UserVouchers::where('user_id', $request->patient_id)
                ->pluck('voucher_id')
                ->toArray();
            
            if ($checkUserVouchers) {
                $voucherDiscountsQuery = Discounts::whereIn('id', $uniq_array)
                    ->whereIn('id', $checkUserVouchers)
                    ->where('discount_type', '=', 'voucher');

                // Apply role filter only if not super admin
               

                $voucherDiscounts = $voucherDiscountsQuery->get();
            }

                // Merge both collections
                $discounts = $generalDiscounts->merge($voucherDiscounts);
                
            }
        }

        $temp_discounts = [];
       

        /*Now Checked Brithday promotion valid or not*/
        foreach ($discounts as $key => $discount) {

            if ($discount->slug == 'birthday') {
                /*first get the pre and post days*/
                $pre_days = $discount->pre_days;
                $post_days = $discount->post_days;
                /*end*/

                $today_1 = Carbon::today();
                $today_2 = Carbon::today();
                $today_3 = Carbon::today();

                /*get the date range to checked patient birthday exist between or not*/
                $predate = $today_1->subDay($pre_days)->format('Y-m-d');
                $postdate = $today_2->addDay($post_days)->format('Y-m-d');

                $patient_info = User::find($request->patient_id);

                /*Now checked birthday valid or not*/
                if ($patient_info->dob) {

                    $patientbirthday = Carbon::parse($patient_info->dob)->format($today_3->year . '-' . 'm-d');

                    if (($patientbirthday >= $predate) && ($patientbirthday <= $postdate)) {
                    } else {
                        $discounts->forget($key);
                    }
                } else {
                    $discounts->forget($key);
                }
            }
        }
        
        /*end*/
        $Discount_array = [];
       
        if (count($discounts) > 0) {
            $service_data = Bundles::where('id', '=', $request->bundle_id)->first();
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
                if (count($Discount_array) > 0) {
                    foreach ($Discount_array as $value) {
                        if ($lowest === false || $value['net_amount'] < $lowest) {
                            $lowest = $value['net_amount'];
                            $select_discount = $value;
                        }
                    }
                    $discounts = $discounts->toArray();
                    // $select_discount = ["discount_type" => "Percentage","discount_price" => 0.0,"id" => 0,"net_amount" => 0.0];
                    // return response()->json(array(
                    //     'status' => true,
                    //     'discounts' => $discounts,
                    //     'checked_custom' => '0',
                    //     'dis_price_info' => $select_discount,
                    // ));
                    $service_data = Bundles::where('id', '=', $request->bundle_id)->first();
                    
                    // For single type bundles (individual services), get the actual service price
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

                    return ApiHelper::apiResponse($this->success, 'Records found.', true, [
                        'discounts' => $discounts,
                        'checked_custom' => '0',
                        'dis_price_info' => $select_discount,
                        'net_amount' => $net_amount,
                    ]);
                } else {
                    $discounts = $discounts->toArray();
                    $service_data = Bundles::where('id', '=', $request->bundle_id)->first();
                    
                    // For single type bundles (individual services), get the actual service price
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

                    return ApiHelper::apiResponse($this->success, 'Records found.', true, [
                        'discounts' => $discounts,
                        'checked_custom' => '1',
                        'net_amount' => $net_amount,
                    ]);
                }
            }
            
        }
        
        // For single type bundles (individual services), get the actual service price
        $net_amount = isset($bundle) ? $bundle->price : 0;
        if ($bundle && $bundle->type == 'single') {
            $bundleService = BundleHasServices::where('bundle_id', $bundle->id)->first();
            if ($bundleService) {
                $actualService = Services::find($bundleService->service_id);
                if ($actualService) {
                    $net_amount = $actualService->price;
                }
            }
        }
        
        return ApiHelper::apiResponse($this->success, 'Records found.', false, [
            'net_amount' => $net_amount,
        ]);
    }
   
    /**
     * Get service info for simple plans (non-bundle)
     * Directly queries services table instead of bundles
     *
     * @param request
     * @return mixed
     */
    public function getserviceinfo_for_plan(Request $request)
    {
        $discounts = Collection::make();
        $today = Carbon::now()->toDateString();
        $location_information = Locations::find($request->location_id);

        // Get logged-in user's role IDs
        $userRoleIds = Auth::user()->user_roles()->pluck('role_id')->toArray();
        $isSuperAdmin = Auth::user()->hasRole('Super-Admin');

        // Check if patient has an active membership
        $patientActiveMembership = null;
        $patientMembershipTypeId = null;
        
        if ($request->patient_id) {
            // Always pick the latest assigned membership (by assigned_at timestamp)
            $patientActiveMembership = Membership::where('patient_id', $request->patient_id)
                ->where('active', 1)
                ->whereDate('end_date', '>=', $today)
                ->orderBy('assigned_at', 'desc')
                ->first();
            
            if ($patientActiveMembership) {
                $patientMembershipTypeId = $patientActiveMembership->membership_type_id;
            }
        }

        $service = Services::find($request->service_id);
        
        if (!$service) {
            return ApiHelper::apiResponse($this->error, 'Service not found.', false);
        }

        $service_id = $service->id;
        $location_id = $request->location_id;

        // Get allocations with type/amount for hybrid approach
        $allocations = DiscountWidget::loadPlanDiscountAllocationsByLocationService($location_id, $service_id, Auth::User()->account_id);
        $discountIds = array_keys($allocations);
       
        $generalDiscountsQuery = Discounts::whereIn('id', $discountIds)
            ->where('discount_type', '!=', 'voucher')
            ->where('active', '=', '1')
            ->whereDate('start', '<=', $today)
            ->whereDate('end', '>=', $today);

        // Apply role filter only if not super admin
        if (!$isSuperAdmin) {
            $generalDiscountsQuery->whereHas('roles', function($query) use ($userRoleIds) {
                $query->whereIn('role_id', $userRoleIds);
            });
        }

        // Apply membership-based discount filtering using customer_type_id column
        if ($patientActiveMembership && $patientMembershipTypeId) {
            // Patient has active membership - show:
            // 1. Discounts where customer_type_id matches patient's membership_type_id
            // 2. Regular discounts with no customer_type_id (null)
            $generalDiscountsQuery->where(function($query) use ($patientMembershipTypeId) {
                $query->where('customer_type_id', $patientMembershipTypeId)
                      ->orWhereNull('customer_type_id');
            });
        } else {
            // Patient has no membership - only show discounts with no customer_type_id
            $generalDiscountsQuery->whereNull('customer_type_id');
        }

        $generalDiscounts = $generalDiscountsQuery->get();

        // Fetch VOUCHER discounts (user-specific)
        $voucherDiscounts = Collection::make();
        $checkUserVouchers = UserVouchers::where('user_id', $request->patient_id)
            ->pluck('voucher_id')
            ->toArray();
        
        if ($checkUserVouchers) {
            $voucherDiscountsQuery = Discounts::whereIn('id', $discountIds)
                ->whereIn('id', $checkUserVouchers)
                ->where('discount_type', '=', 'voucher');

            $voucherDiscounts = $voucherDiscountsQuery->get();
        }

        // Merge both collections
        $discounts = $generalDiscounts->merge($voucherDiscounts);

        // Check birthday promotion validity
        foreach ($discounts as $key => $discount) {
            if ($discount->slug == 'birthday') {
                $pre_days = $discount->pre_days;
                $post_days = $discount->post_days;

                $today_1 = Carbon::today();
                $today_2 = Carbon::today();
                $today_3 = Carbon::today();

                $predate = $today_1->subDay($pre_days)->format('Y-m-d');
                $postdate = $today_2->addDay($post_days)->format('Y-m-d');

                $patient_info = User::find($request->patient_id);

                if ($patient_info->dob) {
                    $patientbirthday = Carbon::parse($patient_info->dob)->format($today_3->year . '-' . 'm-d');

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
        $configurableDiscountIds = \App\Models\DiscountHasLocations::where('location_id', $location_id)
            ->where('slug', 'configurable')
            ->pluck('discount_id')
            ->toArray();

        $configurableDiscounts = Collection::make();
        if (!empty($configurableDiscountIds)) {
            $configurableQuery = Discounts::whereIn('id', $configurableDiscountIds)
                ->where('type', 'Configurable')
                ->where('active', '=', '1')
                ->whereDate('start', '<=', $today)
                ->whereDate('end', '>=', $today);

            if (!$isSuperAdmin) {
                $configurableQuery->whereHas('roles', function($query) use ($userRoleIds) {
                    $query->whereIn('role_id', $userRoleIds);
                });
            }

            if ($patientActiveMembership && $patientMembershipTypeId) {
                $configurableQuery->where(function($query) use ($patientMembershipTypeId) {
                    $query->where('customer_type_id', $patientMembershipTypeId)
                          ->orWhereNull('customer_type_id');
                });
            } else {
                $configurableQuery->whereNull('customer_type_id');
            }

            // Only include configurable discounts whose base_service matches the selected service
            // For category-mode discounts (is_category=1), check if the selected service falls under any of the discount's categories
            $searchServices = Services::where('account_id', Auth::User()->account_id)
                ->select('id', 'parent_id', 'slug', 'end_node')->get()->keyBy('id')->toArray();
            $serviceParentIds = \App\Helpers\Widgets\LocationsWidget::findServiceParents($service_id, $searchServices);
            $serviceParentIds = array_merge($serviceParentIds ?? [], [$service_id]);

            $configurableDiscounts = $configurableQuery->get()->filter(function($discount) use ($service_id, $serviceParentIds) {
                // Check direct service match (is_category=0)
                $directMatch = \App\Models\BaseDiscountService::where('discount_id', $discount->id)
                    ->where('service_id', $service_id)
                    ->where(function($q) { $q->where('is_category', 0)->orWhereNull('is_category'); })
                    ->first();
                if ($directMatch) return true;

                // Check category match (is_category=1) — does any category in the discount contain the selected service?
                $categoryMatch = \App\Models\BaseDiscountService::where('discount_id', $discount->id)
                    ->where('is_category', 1)
                    ->whereIn('service_id', $serviceParentIds)
                    ->first();
                return $categoryMatch !== null;
            });
        }

        // Merge configurable discounts into the main discounts collection
        $discounts = $discounts->merge($configurableDiscounts);

        $Discount_array = [];
       
        if (count($discounts) > 0) {
            foreach ($discounts as $discount) {
                // Handle configurable discounts specially
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

                // Get allocation for this discount - type/amount are now stored in allocation table
                $allocation = $allocations[$discount->id] ?? null;
                
                // Skip if no allocation found or allocation has no type/amount set
                if (!$allocation || !$allocation->type || $allocation->amount === null) {
                    continue;
                }
                
                // Skip custom slug discounts (they have special handling)
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
            if (count($Discount_array) > 0) {
                foreach ($Discount_array as $value) {
                    // Don't auto-select configurable discounts as the "best" discount
                    if ($value['discount_type'] === 'Configurable') {
                        continue;
                    }
                    if ($lowest === false || $value['net_amount'] < $lowest) {
                        $lowest = $value['net_amount'];
                        $select_discount = $value;
                    }
                }
                $discounts = $discounts->toArray();

                return ApiHelper::apiResponse($this->success, 'Records found.', true, [
                    'discounts' => $discounts,
                    'checked_custom' => '0',
                    'dis_price_info' => $select_discount,
                    'net_amount' => $service->price,
                    'tax_treatment_type_id' => $service->tax_treatment_type_id,
                    'location_tax_percentage' => $location_information->tax_percentage ?? 0,
                    'service_name' => $service->name,
                ]);
            } else {
                $discounts = $discounts->toArray();

                return ApiHelper::apiResponse($this->success, 'Records found.', true, [
                    'discounts' => $discounts,
                    'checked_custom' => '1',
                    'net_amount' => $service->price,
                    'tax_treatment_type_id' => $service->tax_treatment_type_id,
                    'location_tax_percentage' => $location_information->tax_percentage ?? 0,
                    'service_name' => $service->name,
                ]);
            }
        }
        
        $location_information = Locations::find($request->location_id);
        return ApiHelper::apiResponse($this->success, 'Records found.', false, [
            'net_amount' => $service->price,
            'tax_treatment_type_id' => $service->tax_treatment_type_id,
            'location_tax_percentage' => $location_information->tax_percentage ?? 0,
            'service_name' => $service->name,
        ]);
    }

    /**
     * Get discount info for simple plans (non-bundle)
     * Directly queries services table instead of bundles
     *
     * @param request
     * @return mixed
     */
    public function getdiscountinfo_for_plan(Request $request)
    {
        if ($request->discount_id) {
            $discount_is_voucher = false;
            $service_id = $request->service_id;
            $patient_id = $request->patient_id;
            $location_id = $request->location_id;
            $service_data = Services::find($service_id);

            if (!$service_data) {
                return ApiHelper::apiResponse($this->error, 'Service not found', false);
            }

            $discount_id = $request->discount_id;
            $discount_data = Discounts::find($discount_id);
            
            // Get allocation-level type/amount (now stored in allocation table only)
            $allocations = DiscountWidget::loadPlanDiscountAllocationsByLocationService($location_id, $service_id, Auth::User()->account_id);
            $allocation = $allocations[$discount_id] ?? null;
            
            // Type/amount are now stored in allocation table
            $effective_type = $allocation ? $allocation->type : null;
            $effective_amount = $allocation ? $allocation->amount : null;
            $allocation_slug = $allocation ? $allocation->slug : 'default';
           
            if ($allocation_slug == 'custom') {
                // Calculate max allowed amount based on allocation type
                $max_percentage = $effective_type == 'Percentage' ? $effective_amount : 100;
                $max_fixed_amount = $service_data->price * ($effective_amount / 100);
                
                return ApiHelper::apiResponse($this->success, 'custom', true, [
                    'custom_checked' => 1,
                    'allocation_type' => $effective_type,
                    'allocation_amount' => $effective_amount,
                    'service_price' => $service_data->price,
                    'max_percentage' => $max_percentage,
                    'max_fixed_amount' => round($max_fixed_amount, 2),
                ]);
            } else {
                // Handle configurable discounts - return preview of all services to be added
                if ($discount_data->type === 'Configurable') {
                    $baseServices = BaseDiscountService::where('discount_id', $discount_id)->get();
                    $getServices = GetDiscountService::where('discount_id', $discount_id)->get();
                    
                    // Check if this is a category-mode discount
                    $isCategoryMode = $baseServices->isNotEmpty() && $baseServices->first()->is_category == 1;

                    $preview_rows = [];
                    $loc = Locations::find($location_id);
                    $locTaxPct = $loc->tax_percentage ?? 0;

                    // BUY rows - full price
                    if ($isCategoryMode) {
                        // Category mode: the user selected a specific service from one of the categories.
                        // Sessions count is stored on each record - just use that number of BUY rows
                        // for the selected service (not one row per DB record).
                        $sessionCount = (int) ($baseServices->first()->sessions ?? $baseServices->count());
                        for ($i = 0; $i < $sessionCount; $i++) {
                            $preview_rows[] = [
                                'service_id'    => $service_data->id,
                                'service_name'  => $service_data->name,
                                'service_price' => $service_data->price,
                                'net_amount'    => $service_data->price,
                                'discount_type' => '-',
                                'discount_price'=> 0,
                                'row_type'      => 'buy',
                                'tax_treatment_type_id' => $service_data->tax_treatment_type_id ?? null,
                                'location_tax_percentage' => $locTaxPct,
                            ];
                        }
                    } else {
                        // Service mode: one BUY row per base_discount_services record
                        $serviceCache = [];
                        foreach ($baseServices as $bs) {
                            if (!isset($serviceCache[$bs->service_id])) {
                                $serviceCache[$bs->service_id] = Services::find($bs->service_id);
                            }
                            $svc = $serviceCache[$bs->service_id];
                            if (!$svc) continue;
                            $preview_rows[] = [
                                'service_id'    => $svc->id,
                                'service_name'  => $svc->name,
                                'service_price' => $svc->price,
                                'net_amount'    => $svc->price,
                                'discount_type' => '-',
                                'discount_price'=> 0,
                                'row_type'      => 'buy',
                                'tax_treatment_type_id' => $svc->tax_treatment_type_id ?? null,
                                'location_tax_percentage' => $locTaxPct,
                            ];
                        }
                    }

                    // GET rows - discounted
                    // For category-mode with same_service, deduplicate: group by discount_type+discount_amount
                    // and use count as session count, then create rows for selected service
                    if ($isCategoryMode) {
                        // Group GET rows by unique discount configuration
                        $getGroups = [];
                        foreach ($getServices as $gs) {
                            $key = $gs->discount_type . '_' . $gs->discount_amount . '_' . ($gs->same_service ? 'same' : $gs->service_id);
                            if (!isset($getGroups[$key])) {
                                $getGroups[$key] = ['record' => $gs, 'count' => 0];
                            }
                            $getGroups[$key]['count']++;
                        }
                        foreach ($getGroups as $group) {
                            $gs = $group['record'];
                            $svc = $gs->same_service ? $service_data : Services::find($gs->service_id);
                            if (!$svc) continue;
                            for ($i = 0; $i < $group['count']; $i++) {
                                if ($gs->discount_type === 'complimentory') {
                                    $net = 0;
                                    $disc_label = 'Complimentary';
                                    $disc_price = $svc->price;
                                } else {
                                    $disc_price = round($svc->price * ($gs->discount_amount / 100), 2);
                                    $net = $svc->price - $disc_price;
                                    $disc_label = $gs->discount_amount . '% Off';
                                }
                                $preview_rows[] = [
                                    'service_id'    => $svc->id,
                                    'service_name'  => $svc->name,
                                    'service_price' => $svc->price,
                                    'net_amount'    => $net,
                                    'discount_type' => $disc_label,
                                    'discount_price'=> $disc_price,
                                    'row_type'      => 'get',
                                    'gs_discount_type'   => $gs->discount_type,
                                    'gs_discount_amount' => $gs->discount_amount,
                                    'tax_treatment_type_id' => $svc->tax_treatment_type_id ?? null,
                                    'location_tax_percentage' => $locTaxPct,
                                ];
                            }
                        }
                    } else {
                        // Service mode: one GET row per get_discount_services record
                        foreach ($getServices as $gs) {
                            $svc = $gs->same_service ? $service_data : Services::find($gs->service_id);
                            if (!$svc) continue;
                            if ($gs->discount_type === 'complimentory') {
                                $net = 0;
                                $disc_label = 'Complimentary';
                                $disc_price = $svc->price;
                            } else {
                                $disc_price = round($svc->price * ($gs->discount_amount / 100), 2);
                                $net = $svc->price - $disc_price;
                                $disc_label = $gs->discount_amount . '% Off';
                            }
                            $preview_rows[] = [
                                'service_id'    => $svc->id,
                                'service_name'  => $svc->name,
                                'service_price' => $svc->price,
                                'net_amount'    => $net,
                                'discount_type' => $disc_label,
                                'discount_price'=> $disc_price,
                                'row_type'      => 'get',
                                'gs_discount_type'   => $gs->discount_type,
                                'gs_discount_amount' => $gs->discount_amount,
                                'tax_treatment_type_id' => $svc->tax_treatment_type_id ?? null,
                                'location_tax_percentage' => $locTaxPct,
                            ];
                        }
                    }

                    $total_net = array_sum(array_column($preview_rows, 'net_amount'));

                    return ApiHelper::apiResponse($this->success, 'Configurable', true, [
                        'is_configurable'  => true,
                        'discount_type'    => 'Configurable',
                        'discount_price'   => 0,
                        'net_amount'       => $service_data->price,
                        'total_net_amount' => $total_net,
                        'custom_checked'   => 0,
                        'slug'             => 'configurable',
                        'preview_rows'     => $preview_rows,
                        'service_name'     => $service_data->name,
                        'tax_treatment_type_id' => $service_data->tax_treatment_type_id,
                        'location_tax_percentage' => $locTaxPct,
                    ]);
                }

                // Initialize default values
                $discount_type = '';
                $discount_price = 0;
                $net_amount = $service_data->price;
                
                if ($effective_type == Config::get('constants.Fixed') && $discount_data->discount_type !="voucher") {
                    $discount_type = Config::get('constants.Fixed');
                    $discount_price = $effective_amount;
                    $net_amount = ($service_data->price) - ($effective_amount);
                } else if ($effective_type == Config::get('constants.Percentage') && $discount_data->discount_type !="voucher") {
                    $discount_type = Config::get('constants.Percentage');
                    $discount_price = $effective_amount;
                    $discount_price_cal = $service_data->price * (($discount_price) / 100);
                    $net_amount = ($service_data->price) - ($discount_price_cal);
                } else if ($discount_data->discount_type == "voucher") {
                    $patientVoucher = UserVouchers::where("user_id", $patient_id)->where("voucher_id", $discount_id)->first();
                    if ($patientVoucher) {
                        $discount_type = Config::get('constants.Fixed');
                        $discount_price = $patientVoucher->amount;
                        $discount_is_voucher = true;
                        $net_amount = ($service_data->price) - ($discount_price);
                        if($net_amount < 0){
                            $net_amount = 0;
                        }
                    } else {
                        $discount_type = "";
                        $discount_price = 0;
                        $discount_is_voucher = false;
                        $net_amount = $service_data->price;
                    }
                }
                $loc = Locations::find($location_id);
                return ApiHelper::apiResponse($this->success, 'Record Found', true, [
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
                ]);
            }
        }

        return ApiHelper::apiResponse($this->success, 'No Record Found', false);
    }

    /**
     * Get custom discount info for simple plans (non-bundle)
     * Directly queries services table instead of bundles
     *
     * @param request
     * @return mixed
     */
    public function getdiscountinfocustom_for_plan(Request $request)
    {
        $status = true;
        $service_id = $request->service_id;
        $location_id = $request->location_id;
        $service_data = Services::find($service_id);
        
        if (!$service_data) {
            return ApiHelper::apiResponse($this->error, 'Service not found', false);
        }

        $discount_id = $request->discount_id;
        $discount_data = Discounts::find($discount_id);
        
        // Get allocation-level type/amount (now stored in allocation table only)
        $allocations = DiscountWidget::loadPlanDiscountAllocationsByLocationService($location_id, $service_id, Auth::User()->account_id);
        $allocation = $allocations[$discount_id] ?? null;
        
        // Type/amount are now stored in allocation table
        $effective_type = $allocation ? $allocation->type : null;
        $effective_amount = $allocation ? $allocation->amount : null;
        $allocation_slug = $allocation ? $allocation->slug : 'default';
        
        if ($allocation_slug == 'custom') {
            $discount_id = $request->discount_id;
        } else {
            if($discount_data->discount_type == "voucher"){
                $discountValue = UserVouchers::where("user_id", $request->patient_id)->where("voucher_id", $discount_id)->first();
                if ($discountValue) {
                    $request->discount_value = $discountValue->amount;
                } else {
                    $request->discount_value = 0;
                }
            } else {
                $request->discount_value = $effective_amount;
            }
        }
        
        // Initialize default values
        $net_amount = $service_data->price;
        $discount_price = 0;
        
        if ($effective_type == 'Fixed' && $discount_data->discount_type != 'voucher') {
            if ($request->discount_type == Config::get('constants.Fixed')) {
                if ($request->discount_value > $effective_amount || $request->discount_value > $service_data->price) {
                    $status = false;
                }
                $discount_type = Config::get('constants.Fixed');
                $discount_price = $request->discount_value;
                $discount_price_in_percentage = ($discount_price / $service_data->price) * 100;
                $net_amount = ($service_data->price) - ($discount_price);
            } else {
                $discount_type = Config::get('constants.Percentage');
                $discount_price = $request->discount_value;
                $discount_price_cal = ($effective_amount / $service_data->price) * 100;
                if ($request->discount_value > $discount_price_cal) {
                    $status = false;
                }
                $amount_after_per = ($request->discount_value / 100) * $service_data->price;
                $net_amount = $service_data->price - $amount_after_per;
            }
        } else if($effective_type == 'Fixed' && $discount_data->discount_type == 'voucher'){
            $discountValue = UserVouchers::where("user_id", $request->patient_id)->where("voucher_id", $discount_id)->first();
            if($discountValue){
                $discount_type = Config::get('constants.Fixed');
                $discount_price = $discountValue->amount;
                $discount_price_in_percentage = ($discount_price / $service_data->price) * 100;
                $net_amount = ($service_data->price) - ($discount_price);
                if($net_amount < 0){
                    $net_amount = 0;
                }
            } else {
                $discount_price = 0;
                $net_amount = ($service_data->price) - ($discount_price);
            }
        } else if ($effective_type == 'Percentage' && $discount_data->discount_type == 'voucher') {
            // For percentage vouchers, skip limit check
            $discountValue = UserVouchers::where("user_id", $request->patient_id)->where("voucher_id", $discount_id)->first();
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
            if ($request->discount_type == Config::get('constants.Fixed')) {
                $discount_price = $request->discount_value;
                if ($service_data->price > 0) {
                    $discount_price_in_percentage = ($discount_price / $service_data->price) * 100;
                    // Skip limit check for vouchers
                    if ($discount_data->discount_type != 'voucher' && $discount_price_in_percentage > $effective_amount) {
                        $status = false;
                    }
                }
                $net_amount = ($service_data->price) - ($request->discount_value);
            } else {
                // Skip limit check for vouchers
                if ($discount_data->discount_type != 'voucher' && $request->discount_value > $effective_amount) {
                    $status = false;
                }
                $discount_price = $request->discount_value;
                $discount_price_in_percentage = ($request->discount_value / 100) * $service_data->price;
                $net_amount = ($service_data->price) - ($discount_price_in_percentage);
            }
        }

        if ($status == true) {
            return ApiHelper::apiResponse($this->success, 'Net Amount', true, [
                'net_amount' => $net_amount < 0 ? 0 : $net_amount,
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'Invalid discount value', false);
    }

    /**
     * Save service to plan - handles both simple and configurable discounts.
     * For plans, services are stored directly (not via bundles).
     *
     * @param request
     * @return mixed
     */
    public function savepackages_service_for_plan(Request $request)
    {
        \Log::info('=== savepackages_service_for_plan CALLED ===', [
            'service_id_from_request' => $request->service_id,
            'discount_id' => $request->discount_id,
            'random_id' => $request->random_id,
            'location_id' => $request->location_id,
        ]);

        $location_information = Locations::find($request->location_id);
        if (!$location_information) {
            return ApiHelper::apiResponse($this->error, 'Location not found.', false);
        }

        $service_data = Services::find($request->service_id);
        if (!$service_data) {
            return ApiHelper::apiResponse($this->error, 'Service not found.', false);
        }

        \Log::info('savepackages_service_for_plan: service found', [
            'service_id' => $service_data->id,
            'service_name' => $service_data->name,
            'service_table' => 'services',
        ]);

        // Check if plan is already settled
        $find_package = Packages::where('random_id', $request->random_id)->first();
        if ($find_package) {
            $check_is_setteled = PackageAdvances::where([
                ['cash_flow', '=', 'out'],
                ['cash_amount', '>', 0],
                ['is_setteled', '=', '1'],
                ['package_id', '=', $find_package->id],
            ])->first();
            if ($check_is_setteled) {
                return ApiHelper::apiResponse($this->error, 'Plan is already settled. You cannot add further treatment.', false);
            }
        }

        $discount_data = $request->discount_id ? Discounts::find($request->discount_id) : null;

        // --- CONFIGURABLE DISCOUNT PATH ---
        if ($discount_data && $discount_data->type === 'Configurable') {
            $baseServices  = BaseDiscountService::where('discount_id', $discount_data->id)->get();
            $getServices   = GetDiscountService::where('discount_id', $discount_data->id)->get();
            $isCategoryMode = $baseServices->isNotEmpty() && $baseServices->first()->is_category == 1;
            $selectedService = Services::find($request->service_id);
            $mergedServices = $baseServices->merge($getServices);

            $myarray = [];
            $running_total = str_replace(',', '', $request->package_total ?? 0);
            if ($running_total === '') $running_total = 0;

            foreach ($mergedServices as $ds) {
                // For category-mode BUY rows or same_service GET rows, use the actually selected service
                $is_buy_row = ($ds instanceof BaseDiscountService || !isset($ds->discount_type));
                if (($is_buy_row && $isCategoryMode) || (!$is_buy_row && $ds->same_service)) {
                    $svc = $selectedService;
                } else {
                    $svc = Services::find($ds->service_id);
                }
                if (!$svc) continue;

                // Determine net amount for this row
                if ($is_buy_row) {
                    $row_net_amount  = $svc->price;
                    $row_disc_type   = '-';
                    $row_disc_price  = 0;
                } elseif ($ds->discount_type === 'complimentory') {
                    $row_net_amount  = 0;
                    $row_disc_type   = 'Complimentary';
                    $row_disc_price  = $svc->price;
                } else {
                    // custom = percentage off
                    $disc_amt        = round($svc->price * ($ds->discount_amount / 100), 2);
                    $row_net_amount  = $svc->price - $disc_amt;
                    $row_disc_type   = 'Percentage';
                    $row_disc_price  = $ds->discount_amount;
                }

                // Build tax data
                $data = $request->all();
                $data['bundle_id']     = $svc->id;
                $data['service_price'] = $svc->price;
                $data['net_amount']    = $row_net_amount;
                $data['discount_name'] = $discount_data->name;
                $data['discount_type'] = $row_disc_type;
                $data['discount_price']= $row_disc_price;
                $data['qty']           = '1';

                if ($request->is_exclusive == '' || $request->is_exclusive === null) {
                    $data['is_exclusive'] = 1;
                }

                // Tax calculation
                $tax_pct = $location_information->tax_percentage ?? 0;
                if ($svc->tax_treatment_type_id == Config::get('constants.tax_is_exclusive') ||
                    ($svc->tax_treatment_type_id == Config::get('constants.tax_both') && ($data['is_exclusive'] ?? 1) == 1)) {
                    $data['tax_exclusive_net_amount'] = $row_net_amount;
                    $data['tax_percenatage']          = $tax_pct;
                    $data['tax_price']                = ceil($row_net_amount * ($tax_pct / 100));
                    $data['tax_including_price']      = ceil($row_net_amount + $data['tax_price']);
                    $data['is_exclusive']             = 1;
                } else {
                    $data['tax_including_price']      = $row_net_amount;
                    $data['tax_percenatage']          = $tax_pct;
                    $data['tax_exclusive_net_amount'] = $tax_pct > 0 ? ceil((100 * $row_net_amount) / ($tax_pct + 100)) : $row_net_amount;
                    $data['tax_price']                = ceil($row_net_amount - $data['tax_exclusive_net_amount']);
                    $data['is_exclusive']             = 0;
                }

                if (!$request->discount_id) {
                    $data['discount_id'] = null;
                }

                $data['created_at'] = Filters::getCurrentTimeStamp();
                $data['updated_at'] = Filters::getCurrentTimeStamp();

                $packagebundle = PackageBundles::createPackagebundle($data);

                // Create package service record (sub-service)
                $data_service = [
                    'random_id'          => $request->random_id,
                    'package_bundle_id'  => $packagebundle->id,
                    'service_id'         => $svc->id,
                    'price'              => $row_net_amount,
                    'orignal_price'      => $svc->price,
                    'tax_including_price'=> $data['tax_including_price'],
                    'tax_percenatage'    => $data['tax_percenatage'],
                    'tax_exclusive_price'=> $data['tax_exclusive_net_amount'],
                    'tax_price'          => $data['tax_price'],
                    'is_exclusive'       => $data['is_exclusive'],
                    'created_at'         => Filters::getCurrentTimeStamp(),
                    'updated_at'         => Filters::getCurrentTimeStamp(),
                ];
                PackageService::createPackageService($data_service);

                $running_total = (float) str_replace(',', '', $running_total) + (float) $packagebundle->tax_including_price;

                $package_service_detail = Services::join('package_services', 'services.id', '=', 'package_services.service_id')
                    ->select('package_services.*', 'services.name')
                    ->where('package_services.package_bundle_id', '=', $packagebundle->id)
                    ->get();

                $myarray[] = [
                    'record'        => PackageBundles::find($packagebundle->id),
                    'record_detail' => $package_service_detail,
                    'random_id'     => $request->random_id,
                    'service_name'  => $svc->name,
                    'service_price' => $svc->price,
                    'discount_name' => $discount_data->name,
                    'discount_type' => $row_disc_type,
                    'discount_price'=> $row_disc_price,
                    'net_amount'    => $row_net_amount,
                    'total'         => number_format($running_total),
                ];
            }

            // Recalculate grand total from DB for accuracy
            $pkg = Packages::where('random_id', $request->random_id)->first();
            if ($pkg) {
                $grand_total = (float) PackageBundles::where('package_id', $pkg->id)->sum('tax_including_price');
            } else {
                $grand_total = (float) PackageBundles::where('random_id', $request->random_id)->sum('tax_including_price');
            }
            if (!empty($myarray)) {
                $myarray[0]['grand_total'] = $grand_total;
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'is_configurable' => true,
                'rows'            => $myarray,
                'grand_total'     => $grand_total,
            ]);
        }

        // --- SIMPLE DISCOUNT PATH ---
        $total = str_replace(',', '', $request->package_total ?? 0);
        if ($total === '') $total = 0;

        if ($request->is_exclusive == '' || $request->is_exclusive === null) {
            $request->merge(['is_exclusive' => 1]);
        }

        $data = $request->all();
        $data['bundle_id']     = $service_data->id;
        $data['service_price'] = $service_data->price;
        $data['qty']           = '1';

        if ($discount_data) {
            $data['discount_name'] = $discount_data->name;
        }

        $tax_pct = $location_information->tax_percentage ?? 0;
        if ($service_data->tax_treatment_type_id == Config::get('constants.tax_is_exclusive') ||
            ($service_data->tax_treatment_type_id == Config::get('constants.tax_both') && $request->is_exclusive == '1')) {
            $data['tax_exclusive_net_amount'] = $request->net_amount;
            $data['tax_percenatage']          = $tax_pct;
            $data['tax_price']                = ceil($request->net_amount * ($tax_pct / 100));
            $data['tax_including_price']      = ceil($request->net_amount + $data['tax_price']);
            $data['is_exclusive']             = 1;
        } else {
            $data['tax_including_price']      = $request->net_amount;
            $data['tax_percenatage']          = $tax_pct;
            $data['tax_exclusive_net_amount'] = $tax_pct > 0 ? ceil((100 * $request->net_amount) / ($tax_pct + 100)) : $request->net_amount;
            $data['tax_price']                = ceil($request->net_amount - $data['tax_exclusive_net_amount']);
            $data['is_exclusive']             = 0;
        }

        if (!$request->discount_id) {
            $data['discount_id'] = null;
        }

        $data['created_at'] = Filters::getCurrentTimeStamp();
        $data['updated_at'] = Filters::getCurrentTimeStamp();

        $packagebundle = PackageBundles::createPackagebundle($data);

        \Log::info('savepackages_service_for_plan: PackageBundle created', [
            'packagebundle_id' => $packagebundle->id,
            'bundle_id_stored' => $packagebundle->bundle_id,
            'service_data_id' => $service_data->id,
            'service_data_name' => $service_data->name,
        ]);

        // Create package service record
        $data_service = [
            'random_id'          => $request->random_id,
            'package_bundle_id'  => $packagebundle->id,
            'service_id'         => $service_data->id,
            'price'              => $request->net_amount,
            'orignal_price'      => $service_data->price,
            'tax_including_price'=> $data['tax_including_price'],
            'tax_percenatage'    => $data['tax_percenatage'],
            'tax_exclusive_price'=> $data['tax_exclusive_net_amount'],
            'tax_price'          => $data['tax_price'],
            'is_exclusive'       => $data['is_exclusive'],
            'created_at'         => Filters::getCurrentTimeStamp(),
            'updated_at'         => Filters::getCurrentTimeStamp(),
        ];
        PackageService::createPackageService($data_service);

        $total = number_format((float) $total + (float) $packagebundle->tax_including_price);

        $discount_name  = '-';
        $discount_type  = '-';
        $discount_price = '0.00';
        if ($request->discount_id) {
            $discount_name  = $packagebundle->discount_name ?? $discount_data->name ?? '-';
            $discount_type  = $packagebundle->discount_type ?? '-';
            $discount_price = $packagebundle->discount_price ?? '0.00';
        }

        $package_service = Services::join('package_services', 'services.id', '=', 'package_services.service_id')
            ->select('package_services.*', 'services.name')
            ->where('package_services.package_bundle_id', '=', $packagebundle->id)
            ->get();

        $myarray = [
            'record'        => PackageBundles::find($packagebundle->id),
            'record_detail' => $package_service,
            'random_id'     => $request->random_id,
            'service_name'  => $service_data->name,
            'service_price' => $service_data->price,
            'discount_name' => $discount_name,
            'discount_type' => $discount_type,
            'discount_price'=> $discount_price,
            'net_amount'    => $packagebundle->net_amount,
            'total'         => $total,
        ];

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'is_configurable' => false,
            'myarray'         => $myarray,
        ]);
    }

    /**
     * Get service info whan discount not selected
     *
     * @param request
     * @return mixed
     */
    public function getservices_for_zero(Request $request)
    {

        $service_data = Bundles::where('id', '=', $request->bundle_id)->first();
        if ($service_data) {

            return ApiHelper::apiResponse($this->success, 'Records found', true, [
                'net_amount' => $service_data->price,
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'No record found', false);
    }

    /**
     * calculate the grand total
     *
     * @param request
     * @return mixed
     */
    public function getgrandtotal(Request $request)
    {

        $package_total = str_replace(',', '', $request->total); //filter_var($request->total, FILTER_SANITIZE_NUMBER_INT);
        $grand_total = number_format($package_total - $request->cash_amount);

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'grand_total' => $grand_total,
        ]);
    }

    /**
     * Display a User As package in datatables.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        if (!Gate::allows('plans_inactive')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        if ($request->status == 1) {
            $response = Packages::activeRecord($request->id);
        } else {
            $response = Packages::inactiveRecord($request->id);
        }

        return ApiHelper::apiResponse($this->success, $response['message'], $response['status']);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Get edit form data for package (optimized)
     */
    public function edit($id)
    {
        if (!Gate::allows('plans_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        try {
            $data = $this->planService->getEditFormData($id);
            
            return ApiHelper::apiResponse($this->success, 'Record found.', true, $data);
        } catch (PlanException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * calculate the grand total
     *
     * @param request
     * @return mixed
     */
    public function getgrandtotal_update(Request $request)
    {

        $package = Packages::where('random_id', '=', $request->random_id)->first();

        $packageadvances_cash_amount = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
            ['is_setteled', '=', '0'],
        ])->sum('cash_amount');

        $refunded = PackageAdvances::where([
            'package_id' => $package->id,
            'cash_flow' => 'out',
            'is_refund' => 1,
        ])->sum('cash_amount');
        $setteled = PackageAdvances::where([
            'package_id' => $package->id,
            'cash_flow' => 'out',
            'is_setteled' => 1,
        ])->sum('cash_amount');
        $package_advances_cash_amount = $packageadvances_cash_amount;
        $package_total = str_replace(',', '', $request->total);

        $total_with_refunded = $package_total + $refunded + $setteled;
        $grand_total = number_format(round(($total_with_refunded - $package_advances_cash_amount)) - $request->cash_amount);
        $package_id = Packages::whereId($package->id)->first();

        // Only update total_price without touching updated_at (this is just a calculation, not a real change)
        Packages::where('id', $package->id)->update(['total_price' => $request->total]);

        return ApiHelper::apiResponse($this->success, 'Record Updated', true, [
            'grand_total' => $grand_total,

        ]);
    }

    /*
     * Update package
     * @param $request
     * @return mixed
     * */
    /**
     * Update bundle plan
     */
    public function updatebundle(Request $request)
    {
        try {
            $request->validate([
                'package_id' => 'required|exists:packages,id',
                'appointment_id' => 'required|exists:appointments,id',
                'payment_mode_id' => 'nullable|exists:payment_modes,id',
                'cash_amount' => 'nullable|numeric|min:0',
                'grand_total' => 'nullable|numeric'
            ]);

            $package = Packages::findOrFail($request->package_id);
            
            // Handle payment if provided
            if ($request->payment_mode_id && $request->cash_amount > 0) {
                // Only update appointment_id and updated_at when payment is added
                $package->appointment_id = $request->appointment_id;
                $package->save();
                $packageAdvance = new PackageAdvances();
                $packageAdvance->package_id = $package->id;
                $packageAdvance->payment_mode_id = $request->payment_mode_id;
                $packageAdvance->cash_amount = $request->cash_amount;
                $packageAdvance->cash_flow = 'in';
                $packageAdvance->account_id = Auth::user()->account_id;
                $packageAdvance->created_by = Auth::id();
                $packageAdvance->save();
                
                // Always regenerate plan_name from services/bundles
                $this->updatePlanNameForPackage($package);
            }
            
            return ApiHelper::apiResponse($this->success, 'Bundle plan updated successfully', true);
            
        } catch (\Exception $e) {
            \Log::error('Update Bundle Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, 'Failed to update bundle plan: ' . $e->getMessage(), false);
        }
    }
    
    /**
     * Update plan name for a package based on its bundles/memberships
     */
    protected function updatePlanNameForPackage(Packages $package): void
    {
        if ($package->plan_type === 'membership') {
            // For membership plans, get name from membership_types table
            $membershipNames = PackageBundles::where('package_bundles.package_id', $package->id)
                ->join('membership_types', 'package_bundles.membership_type_id', '=', 'membership_types.id')
                ->orderBy('package_bundles.id', 'asc')
                ->limit(2)
                ->pluck('membership_types.name')
                ->toArray();

            if (!empty($membershipNames)) {
                $planName = implode(', ', $membershipNames);
                Packages::where('id', $package->id)->update(['plan_name' => $planName]);
            }
            return;
        }

        // Get total count of bundles for this package
        $totalBundleCount = PackageBundles::where('package_id', $package->id)->count();
        
        // For plan type 'plan': bundle_id contains service_id, join with services table
        // For plan type 'bundle': bundle_id contains bundle_id, join with bundles table
        if ($package->plan_type === 'plan') {
            $names = PackageBundles::where('package_bundles.package_id', $package->id)
                ->join('services', 'package_bundles.bundle_id', '=', 'services.id')
                ->orderBy('package_bundles.id', 'asc')
                ->limit(2)
                ->pluck('services.name')
                ->toArray();
        } else {
            $names = PackageBundles::where('package_bundles.package_id', $package->id)
                ->join('bundles', 'package_bundles.bundle_id', '=', 'bundles.id')
                ->orderBy('package_bundles.id', 'asc')
                ->limit(2)
                ->pluck('bundles.name')
                ->toArray();
        }

        $planName = !empty($names) ? implode(', ', $names) : '-';
        
        if ($package->plan_type === 'plan' && $totalBundleCount > 2) {
            $planName .= '...';
        }

        // Update only plan_name
        Packages::where('id', $package->id)->update(['plan_name' => $planName]);
    }

    /**
     * Update plan package (optimized)
     */
    public function updatepackages(Request $request)
    {
        $request->validate([
            'appointment_id' => ['required', 'exists:appointments,id']
        ], [
            'appointment_id.required' => 'Please select appointment',
            'appointment_id.exists' => 'Appointment not found',
        ]);

        try {
            $result = $this->planService->updatePlanPackage($request->all());
            
            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (PlanException $e) {
            \Log::error('Update Packages Error: ' . $e->getMessage());
            
            // Check if it's a settled package error
            if ($e->getCode() == 400 && strpos($e->getMessage(), 'settled') !== false) {
                return ApiHelper::apiResponse($this->success, $e->getMessage(), false, ['setteled' => 1]);
            }
            
            return ApiHelper::apiResponse($this->success, $e->getMessage(), false);
        } catch (\Exception $e) {
            \Log::error('Update Packages Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->success, $e->getMessage(), false);
        }
    }
    protected function verifyRefundsFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'refund_amount' => 'required',
            'refund_note' => 'required',
            'payment_mode_id' => 'required',


        ]);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Delete plan package (optimized)
     */
    public function destroy($id)
    {
        if (!Gate::allows('plans_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $result = $this->planService->deletePlan($id);
            
            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (PlanException $e) {
            // Return clean error message without file path
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        } catch (\Exception $e) {
            \Log::error('Delete Package Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, 'An error occurred while deleting the package.', false);
        }
    }

    /**
     * display the package.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Display package details (optimized)
     */
    public function display($id)
    {
        if (!Gate::allows('plans_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        try {
            $data = $this->planService->getDisplayData($id);
            
            return ApiHelper::apiResponse($this->success, 'Record found.', true, $data);
        } catch (PlanException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    private function appointmentPackage($packageadvances)
    {

        if ($packageadvances->count() > 0) {

            $packageAdvancesCollection = [];
            foreach ($packageadvances as $packageadvance) {
                if ($packageadvance->cash_flow == 'out' && $packageadvance->is_tax == 0) {
                    if (!is_null($packageadvance->refund_note)) {
                        $packageadvance->package_refund_price = number_format(PackageAdvances::getAppointmentPackage($packageadvance->appointment_id, $packageadvance->patient_id, $packageadvance->id));
                    } else {
                        $packageadvance->package_refund_price = number_format(PackageAdvances::getAppointmentPackage($packageadvance->appointment_id, $packageadvance->patient_id));
                    }
                } elseif ($packageadvance->is_tax == 0) {
                    $packageadvance->package_refund_price = number_format($packageadvance->cash_amount);
                } else {
                    $packageadvance->package_refund_price = '00.00';
                }
                $packageadvance->created_at_formated = Carbon::parse($packageadvance->created_at)->format('F j,Y H:i A');

                $packageAdvancesCollection[] = $packageadvance;
            }

            return $packageAdvancesCollection;
        }

        return $packageadvances;
    }

    /**
     * Print the package.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function package_pdf($id)
    {

        if (!Gate::allows('plans_manage')) {
            return abort(401);
        }
        $package = Packages::find($id);

        $location_info = Locations::find($package->location_id);

        $account_info = Accounts::find($package->account_id);

        // Include service, bundle and membershipType relationships
        $packagebundles = PackageBundles::with(['bundle', 'service', 'membershipType'])->where('package_id', '=', $package->id)->get();

        $packageservices = PackageService::where('package_id', '=', $package->id)->get();

        $packageadvances = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['is_cancel', '=', '0'],
            ['is_adjustment', '=', '0'],
            ['is_setteled', '=', '0'],
        ])->get();

        $cash_amount_in = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['cash_flow', '=', 'in'],
            ['is_setteled', '=', '0'],
        ])->sum('cash_amount');

        $cash_amount_out = PackageAdvances::where([
            ['package_id', '=', $package->id],
            ['cash_flow', '=', 'out'],
        ])->sum('cash_amount');
        
        // For membership plans, use PackageBundles sum; for others use PackageService sum
        if ($package->plan_type === 'membership') {
            $packageservices_price = PackageBundles::where('package_id', '=', $package->id)->sum('tax_including_price');
        } else {
            $packageservices_price = PackageService::with('service')->where('package_id', '=', $package->id)->sum('package_services.price');
        }
        $cash_amount = $cash_amount_in - $cash_amount_out;
        /*We discuss it in future what happen next*/
        //$grand_total = number_format($package->total_price - $cash_amount_in);
        $grand_total = number_format($packageservices_price);
        $services = Services::getServices();
        $discount = Discounts::getDiscount(Auth::User()->account_id);

        $paymentmodes = PaymentModes::get()->pluck('name', 'id');
        $paymentmodes->prepend('Select Payment Mode', '');

        $company_phone_number = Settings::where('slug', '=', 'sys-headoffice')->first();

        $content = view('admin.packages.packagepdf', compact('package', 'packagebundles', 'packageservices', 'packageadvances', 'services', 'discount', 'paymentmodes', 'grand_total', 'location_info', 'account_info', 'company_phone_number'));
        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($content);

        return $pdf->stream('treatment-plans-invoice-C-' . $package->patient_id . '.pdf');
    }

    /*
     * $edit the cash that enter in package advances
     */
    public function editpackageadvancescashindex($id, $package_id)
    {
        $pack_adv_info = PackageAdvances::find($id);

        $paymentmodes = PaymentModes::where('type', '=', 'application')->get();

        return ApiHelper::apiResponse($this->success, 'data found', true, [
            'pack_adv_info' => $pack_adv_info,
            'package_id' => $package_id,
            'paymentmodes' => $paymentmodes,
        ]);
        //  return view('admin.packages.finance_edit.create', compact('pack_adv_info', 'package_id', 'paymentmodes'));
    }

    /*
     * Store the cash that is request to change
     */

    public function storepackageadvancescash(Request $request)
    {
        $package_total_price = PackageBundles::where('package_id', '=', $request->package_id)->sum('tax_including_price');
        $get_package_use_amount = PackageAdvances::where([
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'out'],
        ])->sum('cash_amount');
        $get_package_unused_amount_except_edit = PackageAdvances::where([
            ['id', '!=', $request->package_advances_id],
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
        ])->sum('cash_amount');
        $get_package_unused_amount_with_edit = $request->cash_amount;
        $get_package_unuse_amount = $get_package_unused_amount_except_edit + $get_package_unused_amount_with_edit;
        $amount_status = true;
        // Get old values before update
        $packageAdvanceBefore = PackageAdvances::find($request->package_advances_id);
        $oldAmount = $packageAdvanceBefore ? $packageAdvanceBefore->cash_amount : 0;
        $oldDate = $packageAdvanceBefore ? $packageAdvanceBefore->created_at : null;
        
        $record = PackageAdvances::updateRecordFinanceedit($request, Auth::User()->account_id, $amount_status);
        if ($record) {
            // Sync plan_invoices table
            $planInvoice = PlanInvoice::where('package_advance_id', $request->package_advances_id)->first();
            if ($planInvoice) {
                // Update existing plan_invoice
                $planInvoice->update([
                    'total_price' => $request->cash_amount,
                    'payment_mode_id' => $request->payment_mode_id,
                    'created_at' => $request->created_at.' '.Carbon::now()->toTimeString(),
                    'updated_at' => now(),
                ]);
            }
            
            // Log payment updated activity
            $package = Packages::find($request->package_id);
            $patient = $package ? User::find($package->patient_id) : null;
            $location = $package ? Locations::with('city')->find($package->location_id) : null;
            $newAmount = $request->cash_amount;
            $newDate = $request->created_at;
            
            // Check what changed
            $amountChanged = $oldAmount != $newAmount;
            $oldDateFormatted = $oldDate ? Carbon::parse($oldDate)->format('Y-m-d') : null;
            $dateChanged = $oldDateFormatted && $newDate && $oldDateFormatted != $newDate;
            
            if ($package && $patient && ($amountChanged || $dateChanged)) {
                ActivityLogger::logPaymentUpdated($oldAmount, $newAmount, $oldDateFormatted, $newDate, $amountChanged, $dateChanged, $package, $patient, $location);
            }
            
            return ApiHelper::apiResponse($this->success, 'Data Updated successfully.', true, [
                'amount_status' => $amount_status,
            ]);
        }
    }

    /*
     * Delete the cash that reqquire to delete
     */
    public function deletepackageadvancescash(Request $request)
    {
        $packageadvanceinfo = PackageAdvances::withTrashed()->find($request->package_advance_id);

        $get_package_use_amount = PackageAdvances::where([
            ['package_id', '=', $packageadvanceinfo->package_id],
            ['cash_flow', '=', 'out'],
        ])->sum('cash_amount');
        $get_package_unused_amount_except_edit = PackageAdvances::where([
            ['id', '!=', $request->package_advance_id],
            ['package_id', '=', $packageadvanceinfo->package_id],
            ['cash_flow', '=', 'in'],
        ])->sum('cash_amount');
        if ($get_package_use_amount <= $get_package_unused_amount_except_edit) {

            $record = PackageAdvances::deletefinaceRecord($request);
            $cash_receveive_remain = number_format(filter_var($request->cash_receveive_remain, FILTER_SANITIZE_NUMBER_INT) + $packageadvanceinfo->cash_amount);

            // Sync plan_invoices table - soft delete the corresponding plan_invoice
            $planInvoice = PlanInvoice::where('package_advance_id', $request->package_advance_id)->first();
            if ($planInvoice) {
                $planInvoice->delete();
            }

            // Log payment deleted activity
            $package = Packages::find($packageadvanceinfo->package_id);
            $patient = $package ? User::find($package->patient_id) : null;
            $location = $package ? Locations::with('city')->find($package->location_id) : null;
            if ($package && $patient) {
                ActivityLogger::logPaymentDeleted($packageadvanceinfo->cash_amount, $package, $patient, $location);
            }

            return ApiHelper::apiResponse($this->success, 'Record deleted successfully.', true, [
                'id' => $request->package_advance_id,
                'cash_receveive_remain' => $cash_receveive_remain,
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'Unable to delete consume amount.', false);
    }

    /*
     *  Get the information of appointment against (optimized)
     */
    public function getappointmentinfo(Request $request)
    {
        // Validate required parameters
        if (!$request->patient_id || !$request->location_id) {
            return ApiHelper::apiResponse($this->error, 'Patient ID and Location ID are required.', false);
        }

        try {
            $data = $this->planService->getAppointmentInfo(
                (int) $request->patient_id,
                (int) $request->location_id
            );

            return ApiHelper::apiResponse($this->success, 'Record found', true, $data);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Get Appointment Info Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, 'Failed to load appointment information.', false);
        }
    }

    /*
     * Get sold by data for editing
     */
    public function getSoldByData(Request $request)
    {
        try {
            // Handle both package_service_id and package_bundle_id
            if ($request->has('package_service_id')) {
                $packageService = PackageService::find($request->package_service_id);

                if (!$packageService) {
                    return ApiHelper::apiResponse($this->notfound, 'Package service not found', false);
                }

                $package = Packages::find($packageService->package_id);
                $locationId = $request->location_id ?? $package->location_id;
                $currentSoldBy = $packageService->sold_by;
                $packageServices = [$packageService];
                $serviceId = $packageService->service_id;
            } elseif ($request->has('package_bundle_id')) {
                $packageBundle = PackageBundles::find($request->package_bundle_id);

                if (!$packageBundle) {
                    return ApiHelper::apiResponse($this->notfound, 'Package bundle not found', false);
                }

                $package = Packages::find($packageBundle->package_id);
                $locationId = $request->location_id ?? $package->location_id;

                // Get all services - if config_bundle_ids provided, fetch for all bundles in the group
                $bundleIds = $request->has('config_bundle_ids') && is_array($request->config_bundle_ids)
                    ? $request->config_bundle_ids
                    : [$packageBundle->id];

                $packageServices = PackageService::whereIn('package_bundle_id', $bundleIds)->get();

                if ($packageServices->isEmpty()) {
                    return ApiHelper::apiResponse($this->notfound, 'No services found for this bundle', false);
                }

                // Get the first service's sold_by as default
                $currentSoldBy = $packageServices->first()->sold_by;
                $serviceId = $packageServices->first()->service_id;
            } else {
                return ApiHelper::apiResponse($this->notfound, 'Package service or bundle ID required', false);
            }
            // Get all active doctors from the location
            $doctorsIds = DoctorHasLocations::where('is_allocated',1)->where('location_id', $locationId)->pluck('user_id')->toArray();

            $allDoctors = User::whereIn('id', $doctorsIds)
                ->where('active', 1)
                ->pluck('name', 'id')
                ->toArray();

            // Get FDM users by getting the user_ids associated with the center (location_id)
            $findFDM = UserHasLocations::where('location_id', $locationId)->pluck('user_id')->toArray();

            // Fetch the 'FDM' role and get its user ids
            $findRole = DB::table('roles')->where('name', 'FDM')->first();
            $fdmUserIds = [];
            if ($findRole) {
                $roleId = $findRole->id;

                // Get users who have the FDM role
                $roleHasUser = RoleHasUsers::where('role_id', $roleId)->pluck('user_id')->toArray();

                // Get the intersection of users who are both FDM and belong to the center
                $fdmUserIds = array_intersect($findFDM, $roleHasUser);
            }

            // Get selected user ID (current sold_by)
            $selectedUserId = $currentSoldBy;

            // Show all active doctors and FDM users from the branch (no date filtering)
            $usersToShow = [];

            // First, ensure the currently selected user (sold_by) is ALWAYS included, even if inactive
            // This is important for editing - user needs to see who it's currently assigned to
            if ($selectedUserId) {
                $currentSoldByUser = User::find($selectedUserId);
                if ($currentSoldByUser) {
                    $usersToShow[$currentSoldByUser->id] = $currentSoldByUser->name;
                }
            }

            // Add all active doctors from the location
            foreach ($allDoctors as $doctorId => $doctorName) {
                if (!array_key_exists($doctorId, $usersToShow)) {
                    $usersToShow[$doctorId] = $doctorName;
                }
            }

            // Add all active FDM users from the location
            if (!empty($fdmUserIds)) {
                $FDMUsers = User::whereIn('id', $fdmUserIds)
                    ->where('active', 1)
                    ->pluck('name', 'id')
                    ->toArray();

                foreach ($FDMUsers as $fdmId => $fdmName) {
                    if (!array_key_exists($fdmId, $usersToShow)) {
                        $usersToShow[$fdmId] = $fdmName;
                    }
                }
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'users' => $usersToShow,
                'current_sold_by' => $currentSoldBy,
                'package_services' => $packageServices->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'sold_by' => $service->sold_by
                    ];
                })
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /*
     * Update sold by for package service(s)
     */
    public function updateSoldBy(Request $request)
    {
        try {
            // If package_services array is provided, update multiple services
            if ($request->has('package_services') && is_array($request->package_services)) {
                foreach ($request->package_services as $serviceId) {
                    $packageService = PackageService::find($serviceId);
                    if ($packageService) {
                        $packageService->sold_by = $request->sold_by;
                        $packageService->save();
                    }
                }
                return ApiHelper::apiResponse($this->success, 'Sold by updated successfully for all services', true);
            }

            // Single service update
            if ($request->has('package_service_id')) {
                $packageService = PackageService::find($request->package_service_id);

                if (!$packageService) {
                    return ApiHelper::apiResponse($this->notfound, 'Package service not found', false);
                }

                $packageService->sold_by = $request->sold_by;
                $packageService->save();

                return ApiHelper::apiResponse($this->success, 'Sold by updated successfully', true);
            }

            return ApiHelper::apiResponse($this->notfound, 'Package service ID required', false);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /*
     * Check if service is duplicate and return appropriate sold by users
     */
    public function checkDuplicateServiceForSoldBy(Request $request)
    {
        try {
            $bundleId = $request->bundle_id;
            $packageId = $request->package_id;
            $locationId = $request->location_id;

            // Find the package by random_id
            $package = Packages::where('random_id', $packageId)->first();

            if (!$package) {
                return ApiHelper::apiResponse($this->notfound, 'Package not found', false);
            }

            // Get all services in the current package for this bundle
            $existingServices = PackageService::join('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
                ->where('package_services.package_id', $package->id)
                ->where('package_bundles.bundle_id', $bundleId)
                ->count();

            $isDuplicateService = $existingServices > 0;

            // If duplicate service, only show the doctor from the appointment (even if inactive)
            if ($isDuplicateService) {
                $package->load('appointment.doctor');

                $usersToShow = [];

                // Always include appointment doctor, even if inactive
                if ($package->appointment && $package->appointment->doctor_id) {
                    $appointmentDoctor = User::find($package->appointment->doctor_id);

                    if ($appointmentDoctor) {
                        $usersToShow[$appointmentDoctor->id] = $appointmentDoctor->name;
                    }
                }

                return ApiHelper::apiResponse($this->success, 'Duplicate service detected', true, [
                    'users' => $usersToShow,
                    'is_duplicate' => true
                ]);
            }

            // If not duplicate, show doctors who have treated this patient in last 60 days
            $sixtyDaysAgo = now()->subDays(60);

            $recentTreatmentDoctorIds = Appointments::where('patient_id', $package->patient_id)
                ->where('location_id', $locationId)
                ->where('appointment_status_id', 2)
                ->where('appointment_type_id', 2)
                ->where('scheduled_date', '>=', $sixtyDaysAgo)
                ->pluck('doctor_id')
                ->unique()
                ->toArray();

            // Get all active doctors from the location
            $doctorsIds = DoctorHasLocations::where('is_allocated', 1)
                ->where('location_id', $locationId)
                ->pluck('user_id')
                ->toArray();

            $allDoctors = User::whereIn('id', $doctorsIds)
                ->where('active', 1)
                ->pluck('name', 'id')
                ->toArray();

            $usersToShow = [];

            // Add doctors who treated patient in last 60 days
            foreach ($recentTreatmentDoctorIds as $doctorId) {
                if (array_key_exists($doctorId, $allDoctors)) {
                    $usersToShow[$doctorId] = $allDoctors[$doctorId];
                }
            }

            // If no recent history and no doctors found, return the appointment doctor
            if (empty($usersToShow)) {
                $package->load('appointment.doctor');

                // Include appointment doctor, even if inactive
                if ($package->appointment && $package->appointment->doctor_id) {
                    $appointmentDoctor = User::find($package->appointment->doctor_id);

                    if ($appointmentDoctor) {
                        $usersToShow[$appointmentDoctor->id] = $appointmentDoctor->name;
                    }
                }
            }

            return ApiHelper::apiResponse($this->success, 'Service not duplicate', true, [
                'users' => $usersToShow,
                'is_duplicate' => false
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /*
     *  Function for log for package
     */
    public function packagelog($id, $type)
    {
        if (!Gate::allows('plans_log')) {
            return abort(401);
        }

        $action_array = [
            1 => 'Create',
            2 => 'Edit',
            3 => 'Delete',
            4 => 'Inactive',
            5 => 'Active',
            6 => 'Cancel',
        ];
        $table_array = [
            25 => 'Finance',
        ];
        $finance_log = [];

        $find_ids = PackageAdvances::withTrashed()->where('package_id', '=', $id)->pluck('id')->toArray();

        array_push($find_ids, $id);

        $audittrails = AuditTrails::whereIn('table_record_id', $find_ids)->where('audit_trail_table_name', '=', Config::get('constants.package_advance_table_name_log'))->orderBy('created_at', 'asc')->get();

        $count = 1;
        foreach ($audittrails as $audittrail) {
            $finance_log[$audittrail->id] = [
                'sr no' => $count++,
                'id' => $audittrail->id,
                'action' => $action_array[$audittrail->audit_trail_action_name],
                'table' => $table_array[$audittrail->audit_trail_table_name],
                'user_id' => $audittrail->user->name,
                'created_at_orignal' => $audittrail->created_at,
                'updated_at_orignal' => $audittrail->updated_at,
                'detail_log' => [],

            ];

            $audittrail_changes = AuditTrailChanges::where('audit_trail_id', '=', $audittrail->id)->get();

            foreach ($audittrail_changes as $changes) {
                if ($action_array[$audittrail->audit_trail_action_name] == 'Delete') {
                    if ($changes->field_name == 'cash_amount' || $changes->field_name == 'deleted_at') {
                        $result = Financelog::Calculate_Val_advance($changes);
                        $finance_log[$audittrail->id][$changes->field_name] = $result;
                    }
                } else {
                    $result = Financelog::Calculate_Val_advance($changes);
                    $finance_log[$audittrail->id][$changes->field_name] = $result;
                }
            }
            if (!isset($finance_log[$audittrail->id]['cash_flow']) && $action_array[$audittrail->audit_trail_action_name] != 'Delete') {

                $type_2_detail = AuditTrailChanges::where('audit_trail_id', '=', $finance_log[$audittrail->id]['id'])->get();

                foreach ($type_2_detail as $detail) {
                    $result = Financelog::Calculate_Val($detail);
                    $finance_log[$audittrail->id]['detail_log'][$detail->id] = [
                        'field_name' => $detail->field_name,
                        'field_before' => $result['before'],
                        'field_after' => $result['after'],
                    ];
                }
            }
        }

        foreach ($finance_log as $key => $log) {
            if ($log['sr no'] == 1 && $log['cash_flow'] == 'out' && $log['payment_mode_id'] == 'Settle Amount') {
                unset($finance_log[$key]);
            }
        }

        if ($type === 'web') {
            return view('admin.packages.log');
        }

        return $this->packagelogexcel($id, $finance_log);
    }

    public function planDatatable(Request $request, $id)
    {

        $records = [];

        $action_array = [
            1 => 'Create',
            2 => 'Edit',
            3 => 'Delete',
            4 => 'Inactive',
            5 => 'Active',
            6 => 'Cancel',
        ];
        $table_array = [
            25 => 'Finance',
        ];
        $finance_log = [];

        $find_ids = PackageAdvances::withTrashed()->where('package_id', '=', $id)->pluck('id')->toArray();

        array_push($find_ids, $id);

        [$orderBy, $order] = getSortBy($request);

        $iTotalRecords = AuditTrails::whereIn('table_record_id', $find_ids)
            ->where(
                'audit_trail_table_name',
                Config::get('constants.package_advance_table_name_log')
            )->count();

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $audittrails = AuditTrails::whereIn('table_record_id', $find_ids)
            ->where(
                'audit_trail_table_name',
                Config::get('constants.package_advance_table_name_log')
            )->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('created_at', 'asc')->get();

        $count = 1;
        foreach ($audittrails as $audittrail) {
            $finance_log[$audittrail->id] = [
                'sr no' => $count++,
                'id' => $audittrail->id,
                'action' => $action_array[$audittrail->audit_trail_action_name],
                'table' => $table_array[$audittrail->audit_trail_table_name],
                'user_id' => $audittrail->user->name,
                'created_at_orignal' => $audittrail->created_at,
                'updated_at_orignal' => $audittrail->updated_at,
                'detail_log' => [],

            ];

            $audittrail_changes = AuditTrailChanges::where('audit_trail_id', '=', $audittrail->id)->get();

            foreach ($audittrail_changes as $changes) {
                if ($action_array[$audittrail->audit_trail_action_name] == 'Delete') {
                    if ($changes->field_name == 'cash_amount' || $changes->field_name == 'deleted_at') {
                        $result = Financelog::Calculate_Val_advance($changes);
                        $finance_log[$audittrail->id][$changes->field_name] = $result;
                    }
                } else {
                    $result = Financelog::Calculate_Val_advance($changes);
                    $finance_log[$audittrail->id][$changes->field_name] = $result;
                }
            }
            if (!isset($finance_log[$audittrail->id]['cash_flow']) && $action_array[$audittrail->audit_trail_action_name] != 'Delete') {

                $type_2_detail = AuditTrailChanges::where('audit_trail_id', '=', $finance_log[$audittrail->id]['id'])->get();

                foreach ($type_2_detail as $detail) {
                    $result = Financelog::Calculate_Val($detail);
                    $finance_log[$audittrail->id]['detail_log'][$detail->id] = [
                        'field_name' => $detail->field_name,
                        'field_before' => $result['before'],
                        'field_after' => $result['after'],
                    ];
                }
            }
        }

        foreach ($finance_log as $key => $log) {
            if ($log['sr no'] == 1 && $log['cash_flow'] == 'out' && $log['payment_mode_id'] == 'Settle Amount') {
                unset($finance_log[$key]);
            }
        }

        if (!empty($finance_log)) {

            $records['data'] = $finance_log;

            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];
        }

        return ApiHelper::apiDataTable($records);
    }

    /*
     *  Function for log for package
     */

    public function packagelogexcel($id, $finance_log)
    {
        if (!Gate::allows('plans_log')) {
            return abort(401);
        }

        $spreadsheet = new Spreadsheet();
        $Excel_writer = new Xlsx($spreadsheet);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'PACKAGE ID')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', $id);

        $activeSheet->setCellValue('A2', '#')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', 'Cash Flow')->getStyle('B2')->getFont()->setBold(true);
        $activeSheet->setCellValue('C2', 'Cash Amount')->getStyle('C2')->getFont()->setBold(true);
        $activeSheet->setCellValue('D2', 'Refund')->getStyle('D2')->getFont()->setBold(true);
        $activeSheet->setCellValue('E2', 'Adjustment')->getStyle('E2')->getFont()->setBold(true);
        $activeSheet->setCellValue('F2', 'Tax')->getStyle('F2')->getFont()->setBold(true);
        $activeSheet->setCellValue('G2', 'Cancel')->getStyle('G2')->getFont()->setBold(true);
        $activeSheet->setCellValue('H2', 'Delete')->getStyle('H2')->getFont()->setBold(true);
        $activeSheet->setCellValue('I2', 'Refund Note')->getStyle('I2')->getFont()->setBold(true);
        $activeSheet->setCellValue('J2', 'Payment Mode')->getStyle('J2')->getFont()->setBold(true);
        $activeSheet->setCellValue('K2', 'Appointment Type')->getStyle('K2')->getFont()->setBold(true);
        $activeSheet->setCellValue('L2', 'Location')->getStyle('L2')->getFont()->setBold(true);
        $activeSheet->setCellValue('M2', 'Created By')->getStyle('M2')->getFont()->setBold(true);
        $activeSheet->setCellValue('N2', 'Updated By')->getStyle('N2')->getFont()->setBold(true);
        $activeSheet->setCellValue('O2', 'Plan')->getStyle('O2')->getFont()->setBold(true);
        $activeSheet->setCellValue('P2', 'Invoice Id')->getStyle('P2')->getFont()->setBold(true);
        $activeSheet->setCellValue('Q2', 'Created At Shown')->getStyle('Q2')->getFont()->setBold(true);
        $activeSheet->setCellValue('R2', 'Updated At Shown')->getStyle('R2')->getFont()->setBold(true);
        $activeSheet->setCellValue('S2', 'Created At')->getStyle('S2')->getFont()->setBold(true);
        $activeSheet->setCellValue('T2', 'Updated At')->getStyle('T2')->getFont()->setBold(true);
        $activeSheet->setCellValue('U2', 'Deleted At')->getStyle('U2')->getFont()->setBold(true);

        $count = 1;
        $counter = 4;

        foreach ($finance_log as $log) {
            if ((isset($log['package_id']) && $log['package_id'] == $id) || !isset($log['package_id'])) {
                $activeSheet->setCellValue('A' . $counter, $count++);
                $activeSheet->setCellValue('B' . $counter, isset($log['cash_flow']) ? $log['cash_flow'] : '-');
                $activeSheet->setCellValue('C' . $counter, isset($log['cash_amount']) ? $log['cash_amount'] : '-');
                $activeSheet->setCellValue('D' . $counter, isset($log['is_refund']) ? $log['is_refund'] : '-');
                $activeSheet->setCellValue('E' . $counter, isset($log['is_adjustment']) ? $log['is_adjustment'] : '-');
                $activeSheet->setCellValue('F' . $counter, isset($log['is_tax']) ? $log['is_tax'] : '-');
                $activeSheet->setCellValue('G' . $counter, isset($log['is_cancel']) ? $log['is_cancel'] : '-');
                $activeSheet->setCellValue('H' . $counter, ($log['action'] == 'Delete') ? 'Yes' : '-');
                $activeSheet->setCellValue('I' . $counter, isset($log['refund_note']) ? $log['refund_note'] : '-');
                $activeSheet->setCellValue('J' . $counter, isset($log['payment_mode_id']) ? $log['payment_mode_id'] : '-');
                $activeSheet->setCellValue('K' . $counter, isset($log['appointment_type_id']) ? $log['appointment_type_id'] : '-');
                $activeSheet->setCellValue('L' . $counter, isset($log['location_id']) ? $log['location_id'] : '-');
                $activeSheet->setCellValue('M' . $counter, isset($log['created_by']) ? $log['created_by'] : '-');
                $activeSheet->setCellValue('N' . $counter, isset($log['cash_flow']) ? isset($log['updated_by']) ? $log['updated_by'] : '-' : $log['user_id']);
                $activeSheet->setCellValue('O' . $counter, isset($log['package_id']) ? $log['package_id'] : '-');
                $activeSheet->setCellValue('P' . $counter, isset($log['invoice_id']) ? $log['invoice_id'] : '-');
                $activeSheet->setCellValue('Q' . $counter, isset($log['created_at']) ? $log['created_at'] == $log['created_at_orignal'] ? '-' : $log['created_at'] : '-');
                $activeSheet->setCellValue('R' . $counter, isset($log['updated_at']) ? $log['updated_at'] == $log['updated_at_orignal'] ? '-' : $log['updated_at'] : '-');

                if ($log['action'] == 'Delete') {
                    $activeSheet->setCellValue('S' . $counter, '-');
                    $activeSheet->setCellValue('T' . $counter, '-');
                } else {
                    $activeSheet->setCellValue('S' . $counter, isset($log['created_at_orignal']) ? \Carbon\Carbon::parse($log['created_at_orignal'])->format('F j,Y h:i A') : '-');
                    $activeSheet->setCellValue('T' . $counter, isset($log['updated_at_orignal']) ? \Carbon\Carbon::parse($log['updated_at_orignal'])->format('F j,Y h:i A') : '-');
                }

                $activeSheet->setCellValue('U' . $counter, isset($log['deleted_at']) ? \Carbon\Carbon::parse($log['deleted_at'])->format('F j, Y h:i A') : '-');

                $counter++;

                if (isset($log['detail_log']) && count($log['detail_log'])) {

                    $countt = 1;

                    $activeSheet->setCellValue('H' . $counter, '#')->getStyle('H' . $counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('I' . $counter, 'Field Name')->getStyle('I' . $counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('J' . $counter, 'Before')->getStyle('J' . $counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('K' . $counter, 'After')->getStyle('K' . $counter)->getFont()->setBold(true);

                    $counter++;

                    foreach ($log['detail_log'] as $detail) {
                        $activeSheet->setCellValue('H' . $counter, $countt++);
                        $activeSheet->setCellValue('I' . $counter, isset($detail['field_name']) ? $detail['field_name'] : '-');
                        $activeSheet->setCellValue('J' . $counter, isset($detail['field_before']) ? $detail['field_before'] : '-');
                        $activeSheet->setCellValue('K' . $counter, isset($detail['field_after']) ? $detail['field_after'] : '-');

                        $counter++;
                    }
                }
            }
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'PackageLog' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Load plan Sms History.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showSMSLogs($id)
    {
        $SMSLogs = SMSLogs::where('package_id', '=', $id)->orderBy('created_at', 'desc')->get();

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'SMSLogs' => $SMSLogs,
        ]);
    }

    /**
     * Re-send Plan SMS
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateAppointmentsRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendLogSMS(Request $request)
    {

        $SMSLog = SMSLogs::findOrFail($request->get('id'));

        if ($SMSLog) {
            $response = $this->resendSMS($SMSLog->id, $SMSLog->to, $SMSLog->text, $SMSLog->package_id);

            if ($response['status']) {
                return ApiHelper::apiResponse($this->success, 'SMS sent successfully.');
            }
        }

        return ApiHelper::apiResponse($this->success, 'SMS not sent.', false);
    }

    /**
     * Calling sms log
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateAppointmentsRequest  $request
     * @return \Illuminate\Http\Response
     */
    private function resendSMS($smsId, $patient_phone, $preparedText, $package_id)
    {
        $package_info = Packages::find($package_id);

        $setting = Settings::whereSlug('sys-current-sms-operator')->first();

        $UserOperatorSettings = UserOperatorSettings::getRecord($package_info->account_id, $setting->data);

        if ($setting->data == 1) {
            $SMSObj = [
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'to' => $patient_phone,
                'text' => $preparedText,
                'mask' => $UserOperatorSettings->mask, // Setting ID 3 for Mask
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            ];
            $response = TelenorSMSAPI::SendSMS($SMSObj);
        } else {
            $SMSObj = [
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'from' => $UserOperatorSettings->mask,
                'to' => $patient_phone,
                'text' => $preparedText,
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            ];
            $response = JazzSMSAPI::SendSMS($SMSObj);
        }
        if ($response['status']) {
            SMSLogs::find($smsId)->update(['status' => 1]);
        }

        return $response;
    }

    /*
     * Function get the variable to search in database to get the package
     *
     * */
    public function getpackage(Request $request)
    {
        $package = Packages::where('name', 'LIKE', "%{$request->q}%")->select('name', 'id')->get();

        return response()->json($package);
    }
    public function getPlans(Request $request)
    {
        $plans  = Packages::where('patient_id', $request->patient_id)->pluck('name');
        return response()->json(['stataus' => 1, 'message' => 'plan found', 'plans' => $plans]);
    }
    public function editRefund($id)
    {
        $return_tax_amount = '';

        $package_information = Packages::find($id);

        $patient = User::whereId($package_information->patient_id)->first();
        /*calculation for back date refund entry*/
        $package_advance_last_in = PackageAdvances::where([
            ['cash_flow', '=', 'in'],
            ['is_setteled', '=', '0'],
            ['cash_amount', '>', 0],
            ['package_id', '=', $package_information->id],
        ])->orderBy('created_at', 'desc')->first();

        $date_backend = date('Y-m-d', strtotime($package_advance_last_in->created_at));
        $bundle_information = PackageBundles::where('package_id', '=', $id)->first();
        $tax_percentage = $bundle_information->tax_percenatage ?? '';
        $is_adjustment_amount = 0;
        $package_is_refunded_amount = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'out'],
            ['is_refund', '=', '1'],
            ['is_tax', '=', '0'],
        ])->sum('cash_amount');
        $package_is_setteled = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'out'],
            ['is_setteled', '=', '1'],
            ['is_tax', '=', '0'],
        ])->sum('cash_amount');
        $amount_to_refund = $package_is_refunded_amount + $package_is_setteled;
        /*Document charges*/
        $documentationcharges = Settings::where('slug', '=', 'sys-documentationcharges')->first();
        $package_cash_receive = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
            ['is_setteled', '=', '0'],
        ])->sum('cash_amount');
        $package_refunded_amount = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'out'],
            ['is_cancel', '=', '0'],
            ['is_refund', '=', '1'],
            ['cash_amount', '>', '0'],
        ])->latest()->first();
        $latest_package_refunded_amount = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'out'],
            ['is_cancel', '=', '0'],
            ['is_refund', '=', '1'],
        ])->latest()->first();
        $package_setteled_amount = PackageAdvances::where([
            ['package_id', '=', $id],
            ['cash_flow', '=', 'out'],
            ['is_cancel', '=', '0'],
            ['is_setteled', '=', '1'],
        ])->sum('cash_amount');
        if ($package_cash_receive) {
            $package_service_originalPrice_consumed = PackageService::where([
                ['package_id', '=', $id],
                ['is_consumed', '=', '1'],
            ])->sum('price');

            /*Consume amount tax calculate*/
            $cosume_amount_tax = 0; //$package_service_originalPrice_consumed*($tax_percentage/100);
            /*ans is :: 38.4*/

            $refund_1 = $package_service_originalPrice_consumed + $cosume_amount_tax + $documentationcharges->data;

            $refundable_amount = ceil(($package_cash_receive - $refund_1) - $amount_to_refund);
        }

        if ($refundable_amount > 0) {
            /*consume final price with tax*/
            $package_service_Price_consumed_tax = PackageService::where([
                ['package_id', '=', $id],
                ['is_consumed', '=', '1'],
            ])->sum('tax_including_price');

            $package_service_Price_consumed_without_tax = PackageService::where([
                ['package_id', '=', $id],
                ['is_consumed', '=', '1'],
            ])->sum('tax_exclusive_price');
            /*Tax amount that given from customer*/
            $given_tax_amount = $package_service_Price_consumed_tax - $package_service_Price_consumed_without_tax;
            /*ans is :: 32*/

            $return_tax_amount = ($cosume_amount_tax - $given_tax_amount);
            $cal_adjustment_final = $package_service_Price_consumed_tax + ($package_cash_receive - $refund_1);
            $is_adjustment_amount = ceil(($package_cash_receive - $cal_adjustment_final) - $return_tax_amount);
            $return_tax_amount = ceil($return_tax_amount);
        }
        if ($refundable_amount < 0) {
            $refundable_amount = 0;
        }
        $package_is_adjuestment_amount = PackageAdvances::where([
            'package_id' => $id,
            'cash_flow' => 'out',
            'is_adjustment' => '1',
        ])->sum('cash_amount');

        if ($package_is_adjuestment_amount == 0) {
            $document = true;
        } else {
            $document = false;
        }
        $paymentmodes = PaymentModes::where('name', "!=", "Settle Amount")->get()->pluck('name', 'id');
        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'id' => $id,
            'refundable_amount' => $refundable_amount,
            'cash_amount' => $package_cash_receive,
            'is_adjustment_amount' => $is_adjustment_amount,
            'documentationcharges' => $documentationcharges,
            'document' => $document,
            'return_tax_amount' => $return_tax_amount,
            'date_backend' => $date_backend,
            'paymentmodes' => $paymentmodes,
            'refunded_amount' => $package_refunded_amount->cash_amount,
            'record_id' => $package_refunded_amount->id,
            'package_setteled_amount' => $package_setteled_amount,
            'patient_name' => $patient->name,
            'patient_id' => $patient->id,
            'plan' => $package_information->name,
            'created_date' => $latest_package_refunded_amount && $latest_package_refunded_amount->created_at ? Carbon::parse($latest_package_refunded_amount->created_at)->format('Y-m-d') : date('Y-m-d'),
            'refund_note' => $latest_package_refunded_amount->refund_note ?? '',
            'payment_method_id' => $latest_package_refunded_amount->payment_mode_id ?? 1
        ]);
    }
    public function updateRefund(Request $request)
    {

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        $latest_refund = PackageAdvances::where(
            [
                ["package_id", '=', $request['package_id']],
                ['is_refund', '=', 1],
                ['cash_amount', '>', 0],
                ['is_tax', '=', 0],
            ]

        )->latest()->first();

        // Check if case was previously settled (for activity logging)
        $wasPreviouslySettled = PackageAdvances::where([
            ['package_id', '=', $request->package_id],
            ['cash_flow', '=', 'out'],
            ['is_setteled', '=', 1],
        ])->exists();

        if ($request['case_setteled'] == '1') {

            $package_cash_receive = PackageAdvances::where([
                ['package_id', '=', $request->package_id],
                ['cash_flow', '=', 'in'],
                ['is_cancel', '=', '0'],

            ])->sum('cash_amount');

            $package_is_refunded_amount = PackageAdvances::where([
                ['package_id', '=', $request->package_id],
                ['cash_flow', '=', 'out'],
                ['is_refund', '=', '1'],
                ['is_tax', '=', '0'],
                ['is_setteled', '=', '0'],
            ])->sum('cash_amount');

            $package_is_consumed_amount = PackageAdvances::where([
                ['package_id', '=', $request->package_id],
                ['cash_flow', '=', 'out'],
                ['is_refund', '=', '0'],
                ['is_tax', '=', '0'],
                ['is_setteled', '=', '0'],
                ['is_adjustment', '=', '0'],
            ])->sum('cash_amount');

            $package_is_consumed_tax_amount = PackageAdvances::where([
                ['package_id', '=', $request->package_id],
                ['cash_flow', '=', 'out'],
                ['is_refund', '=', '0'],
                ['is_tax', '=', '1'],
                ['is_setteled', '=', '0'],
            ])->sum('cash_amount');

            $consumed_amount_with_tax = $package_is_consumed_amount + $package_is_consumed_tax_amount;

            $package_is_refunded_amount = PackageAdvances::where([
                ['package_id', '=', $request->package_id],
                ['cash_flow', '=', 'out'],
                ['is_refund', '=', '1'],
                ['is_tax', '=', '0'],
            ])->sum('cash_amount');
            $amount_after_refund = $consumed_amount_with_tax + $package_is_refunded_amount;
            $amount_left = $package_cash_receive - $amount_after_refund;
            $packageinformation = Packages::find($request->package_id);
            $find_doc = Appointments::where('id', $packageinformation->appointment_id)->first();
            if ($amount_left > 0) {

                $data_adjustment['cash_flow'] = 'out';
                $data_adjustment['cash_amount'] = $amount_left;
                $data_adjustment['is_adjustment'] = '0';
                $data_adjustment['is_setteled'] = 1;
                $data_adjustment['patient_id'] = $request->get('patient_id');
                $data_adjustment['payment_mode_id'] = $request->payment_mode_id;
                $data_adjustment['account_id'] = Auth::User()->account_id;
                $data_adjustment['created_by'] = Auth::User()->id;
                $data_adjustment['updated_by'] = Auth::User()->id;
                $data_adjustment['package_id'] = $request->package_id;
                $data_adjustment['patient_id'] = $packageinformation->patient_id;
                $data_adjustment['location_id'] = $packageinformation->location_id;
                $data_adjustment['appointment_id'] = $packageinformation->appointment_id;
                $data_adjustment['created_at'] = $request['created_at'] . ' ' . Carbon::now()->toTimeString();
                $data_adjustment['updated_at'] = $request['created_at'] . ' ' . Carbon::now()->toTimeString();

                PackageAdvances::create($data_adjustment);
                $services = Services::where('name', 'Refund Settelment')->first();
                $dataInvoice['total_price'] = $amount_left;
                $dataInvoice['account_id'] = Auth::User()->account_id;
                $dataInvoice['patient_id'] = $packageinformation->patient_id;
                $dataInvoice['appointment_id'] = $packageinformation->appointment_id;
                $dataInvoice['invoice_status_id'] = 3;
                $dataInvoice['created_by'] = Auth::User()->id;
                $dataInvoice['location_id'] = $packageinformation->location_id;
                $dataInvoice['doctor_id'] = $find_doc->doctor_id;
                $dataInvoice['active'] = 1;
                $dataInvoice['is_exclusive'] = 0;
                $dataInvoice['is_settlement'] = 1;
                $dataInvoice['package_id'] = $request->package_id;
                $create_invoice =  Invoices::create($dataInvoice);
                $dataInvoiceDetail['qty'] = 1;
                $dataInvoiceDetail['service_id'] = $services->id;
                $dataInvoiceDetail['package_id'] = $request->package_id;
                $dataInvoiceDetail['invoice_id'] = $create_invoice->id;
                 $dataInvoiceDetail['is_settlement'] = 1;
                InvoiceDetails::create($dataInvoiceDetail);
            } else {
                $latest_refund->where('id', $request['record_id'])->update(['is_setteled' => 1]);
            }
        } else {
            // Handle unchecked case - remove settlement status
            $latest_refund->where('id', $request['record_id'])->update(['is_setteled' => 0]);

            // Delete settlement records for this package
            PackageAdvances::where([
                ['package_id', '=', $request->package_id],
                ['cash_flow', '=', 'out'],
                ['is_setteled', '=', 1],
            ])->delete();
            $findInvoice = Invoices::where('package_id', $request->package_id)->where('is_settlement', 1)->first();
            if ($findInvoice) {
                $findInvoiceDetails = InvoiceDetails::where('invoice_id', $findInvoice->id)->where('is_settlement', 1)->first();
                if ($findInvoiceDetails) {
                    $findInvoiceDetails->delete();
                }
                $findInvoice->delete();
            }
            
           
        }
        $latest_refund->where('id', $request['record_id'])->update(['created_at' => $request['created_at'] . ' ' . Carbon::now()->toTimeString(), 'cash_amount' => $request['refund_amount'], 'payment_mode_id' => $request['payment_mode_id'], 'refund_note' => $request['refund_note']]);
        
        // Log refund update activity
        $packageInfo = Packages::find($request->package_id);
        $patient = User::find($packageInfo->patient_id);
        $location = Locations::find($packageInfo->location_id);
        
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $locationName = $location->name ?? '';
        $refundAmount = $request->refund_amount;
        $refundDate = $request->created_at ? date('M j, Y', strtotime($request->created_at)) : date('M j, Y');
        $caseSetteled = $request->case_setteled == "1";
        
        $description = '<span class="highlight">' . $creatorName . '</span> updated refund <span class="highlight-green">Rs. ' . number_format($refundAmount) . '</span> for <span class="highlight-orange">' . $patientName . '</span> in <span class="highlight-purple">Plan #' . sprintf('%05d', $request->package_id) . '</span>' . ($locationName ? ' at <span class="highlight">' . $locationName . '</span>' : '') . ' on <span class="highlight-purple">' . $refundDate . '</span>';
        
        if ($caseSetteled) {
            $description .= ' - <span class="highlight-green">Case Settled</span>';
        } elseif ($wasPreviouslySettled && !$caseSetteled) {
            // Only show "Case Unsettled" if it was previously settled and now being unsettled
            $description .= ' - <span class="highlight-orange">Case Unsettled</span>';
        }
        
        $activity = new Activity();
        $activity->timestamps = false;
        $activity->action = 'refund_updated';
        $activity->activity_type = 'refund_updated';
        $activity->description = $description;
        $activity->patient = $patientName;
        $activity->patient_id = $patient->id ?? null;
        $activity->appointment_type = 'Plan';
        $activity->created_by = Auth::user()->id;
        $activity->planId = $request->package_id;
        $activity->amount = $refundAmount;
        $activity->location = $locationName;
        $activity->centre_id = $packageInfo->location_id;
        $activity->account_id = Auth::user()->account_id;
        $activity->created_at = \App\Helpers\Filters::getCurrentTimeStamp();
        $activity->updated_at = \App\Helpers\Filters::getCurrentTimeStamp();
        $activity->save();
        
        return ApiHelper::apiResponse($this->success, 'Record updated', true, []);
    }
    protected function verifyFields(Request $request)
    {
        $rules = [
            'refund_amount' => ['required', 'numeric', 'regex:/^[0-9]+$/'],
            'refund_note' => 'required',
            'package_id' => 'required',
            'payment_mode_id' => 'required',
            'created_at' => ['required', 'date', 'date_format:Y-m-d'],
        ];
        $customMessages = [
            'created_at.required' => 'The created at field is required.',
            'created_at.date_format' => 'The Date field format is incorrect.',
        ];

        return Validator::make($request->all(), $rules, $customMessages);
    }
    public function viewPackage($id)
    {

        $url = route('admin.packages.edit', $id);

        return view('admin.packages.details', get_defined_vars());
    }
    public function storeRecord($package, $request)
    {

        $packageBundledata['random_id'] = $package->random_id;
        $packageBundledata['is_allocate'] = 1;
        if (isset($request['package_bundles'])) {
            foreach ($request['package_bundles'] as $packageBundle) {
                $packageBundledata['qty'] = 1;
                $packageBundledata['discount_name'] = $packageBundle['DiscountName'];
                $packageBundledata['discount_type'] = $packageBundle['Type'];
                $packageBundledata['discount_price'] = $packageBundle['DiscountValue'];
                $packageBundledata['service_price'] = str_replace(',', '', $packageBundle['RegularPrice']);
                $packageBundledata['net_amount'] = str_replace(',', '', $packageBundle['RegularPrice']);
                $packageBundledata['discount_id'] = 1;
                $packageBundledata['bundle_id'] = $packageBundle['bundleId'];
                $packageBundledata['package_id'] = $package->id;
                $packageBundledata['tax_exclusive_net_amount'] = str_replace(',', '', $packageBundle['Amount']);
                $packageBundledata['tax_percentage'] = 1;
                $packageBundledata['tax_price'] = $packageBundle['Tax'];
                $packageBundledata['tax_including_price'] = $packageBundle['Total'];
                $packageBundledata['location_id'] = $request->location_id;
                $packageBundleRecord = PackageBundles::create($packageBundledata);
                $bundleServices = BundleHasServices::where('bundle_id', '=', $packageBundleRecord->bundle_id)->get();
                $service_data = Bundles::find($packageBundle['bundleId']);
                $calculable_servcies = [];
                foreach ($bundleServices as $bundleService) {
                    $calculable_servcies[] = [
                        'service_price' => $bundleService->calculated_price,
                        'calculated_price' => $bundleService->calculated_price,
                        'service_id' => $bundleService->service_id,
                    ];
                }
                $calculatedServicesPrice = Bundles::calculatePrices($calculable_servcies, str_replace(',', '', $packageBundle['RegularPrice']), $packageBundle['Total']);
                $location_information = Locations::find($request->location_id);
                foreach ($calculatedServicesPrice as $calculatedServicePrice) {
                    $data_service['random_id'] = $request->random_id;
                    $data_service['package_bundle_id'] = $packageBundleRecord->id;
                    $data_service['service_id'] = $calculatedServicePrice['service_id'];
                    $data_service['price'] = $calculatedServicePrice['calculated_price'];
                    $data_service['orignal_price'] = $calculatedServicePrice['service_price'];
                    if ($service_data->tax_treatment_type_id == Config::get('constants.tax_both')) {
                        if ($request->is_exclusive == '1') {
                            $data_service['tax_exclusive_price'] = $calculatedServicePrice['calculated_price'];
                            $data_service['tax_percenatage'] = $location_information->tax_percentage;
                            $data_service['tax_price'] = ceil($calculatedServicePrice['calculated_price'] * ($location_information->tax_percentage / 100));
                            $data_service['tax_including_price'] = ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percenatage']) / 100));

                            $data_service['is_exclusive'] = 1;
                        } else {
                            $data_service['tax_including_price'] = $calculatedServicePrice['calculated_price'];
                            $data_service['tax_percenatage'] = $location_information->tax_percentage;
                            $data_service['tax_exclusive_price'] = ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percenatage'] + 100));
                            $data_service['tax_price'] = ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);

                            $data_service['is_exclusive'] = 0;
                        }
                    } elseif ($service_data->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                        $data_service['tax_exclusive_price'] = $calculatedServicePrice['calculated_price'];
                        $data_service['tax_percenatage'] = $location_information->tax_percentage;
                        $data_service['tax_price'] = ceil($calculatedServicePrice['calculated_price'] * ($location_information->tax_percentage / 100));
                        $data_service['tax_including_price'] = ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percenatage']) / 100));

                        $data_service['is_exclusive'] = 1;
                    } else {
                        $data_service['tax_including_price'] = $calculatedServicePrice['calculated_price'];
                        $data_service['tax_percenatage'] = $location_information->tax_percentage;
                        $data_service['tax_exclusive_price'] = ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percenatage'] + 100));
                        $data_service['tax_price'] = ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);

                        $data_service['is_exclusive'] = 0;
                    }
                    $data_service['created_at'] = Filters::getCurrentTimeStamp();
                    $data_service['updated_at'] = Filters::getCurrentTimeStamp();
                    $data_service['sold_by'] =$packageBundle['sold_by'] ;
                    $packageservice = PackageService::createPackageService($data_service);
                }
            }
            return true;
        }
    }
    public function deleteplanrowtem(Request $request){
       $voucher = PackageVouchers::where('service_id', $request->id)->where('package_random_id', $request->random_id)->first();

       if($voucher){
        $checkUser = UserVouchers::where('voucher_id', $voucher->voucher_id)->where('user_id', $voucher->user_id)->first();
        if($checkUser){
            $newAmount = $checkUser->amount + $voucher->amount;
            $checkUser->amount = $newAmount;
            $checkUser->update();
        }
        $voucher->delete();
       }
       return response()->json([
        'status' => true,
        'message' => 'Record deleted successfully',
       ]);
    }
    public function resetvoucherpacakgebundles(Request $request)
    {
        $servicesIds = $request->package_bundles;
        $randomId = $request->random_id;

        $vouchers = PackageVouchers::where('package_random_id', $randomId)
                                ->whereIn('service_id', $servicesIds)
                                ->get();


        $voucherAmounts = [];
        foreach ($vouchers as $voucher) {
            $key = $voucher->user_id . '_' . $voucher->voucher_id;
            $voucherAmounts[$key]['user_id'] = $voucher->user_id;
            $voucherAmounts[$key]['voucher_id'] = $voucher->voucher_id;
            $voucherAmounts[$key]['amount'] = ($voucherAmounts[$key]['amount'] ?? 0) + $voucher->amount;
        }

        // Update user vouchers and log activity
        foreach ($voucherAmounts as $data) {
            UserVouchers::where('user_id', $data['user_id'])
                    ->where('voucher_id', $data['voucher_id'])
                    ->increment('amount', $data['amount']);
            
            // Log voucher refund activity
            $patient = User::find($data['user_id']);
            $voucher = Discounts::find($data['voucher_id']);
            if ($patient && $voucher) {
                ActivityLogger::logVoucherRefunded($data['amount'], $patient, $voucher);
            }
        }

        // Delete package vouchers
        PackageVouchers::where('package_random_id', $randomId)
                    ->whereIn('service_id', $servicesIds)
                    ->delete();

        return response()->json(['success' => true]);
    }
}
