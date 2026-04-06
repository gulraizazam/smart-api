<?php

declare(strict_types=1);
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
    public function __construct(
        protected readonly PlanService $planService,
    ) {}

    /**
     * Display a listing of the package.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authorize('managePlans', Packages::class);

        return view('admin.packages.index');
    }

    /**
     * Show the form for creating a new package.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        $this->authorize('createPlan', Packages::class);

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
            
            return $this->successResponse('Record found.', $data);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Plans Create Form Data Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Failed to load form data.', 500);
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
                return $this->errorResponse('Location ID is required.', 500);
            }

            $services = $this->planService->getServicesByLocation(
                (int) $request->location_id,
                Auth::user()->account_id
            );

            if (!empty($services)) {
                return $this->successResponse('Record found', [
                    'service' => $services,
                ]);
            }

            return $this->errorResponse('Record not found', 404);
        } catch (\Exception $e) {
            \Log::error('Get Services Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to load services.', 500);
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
            $result = $this->planService->addBundleService([
                'bundle_id'   => $request->bundle_id,
                'location_id' => $request->location_id,
                'net_amount'  => $request->net_amount,
                'random_id'   => $request->random_id,
                'sold_by'     => $request->sold_by ?? null,
            ]);

            return $this->successResponse('Bundle service added successfully', $result);
        } catch (PlanException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            \Log::error('Save Bundle Service Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to add bundle service: ' . $e->getMessage(), 500);
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
            $result = $this->planService->addMembershipService([
                'membership_id' => $request->membership_id,
                'location_id'   => $request->location_id,
                'net_amount'    => $request->net_amount,
                'sold_by'       => $request->sold_by ?? null,
            ]);

            return $this->successResponse('Membership service added successfully', $result);
        } catch (PlanException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            \Log::error('Save Membership Service Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to add membership service: ' . $e->getMessage(), 500);
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
                return $this->errorResponse('Package ID is required', 500);
            }

            $package = Packages::find($packageId);
            if (!$package) {
                return $this->errorResponse('Package not found', 500);
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
                $isStudentMembership = $studentVerificationService->isStudentMembership((int) $packageBundle->membership_type_id);
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
                        $durationDays = (int)($membershipType->period ?? 365);

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
                return $this->successResponse($message);
            }

            return $this->successResponse('No changes made');

        } catch (\Exception $e) {
            \Log::error('Update Membership Plan Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Failed to update membership: ' . $e->getMessage(), 500);
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
                return $this->errorResponse('Location ID is required.', 500);
            }

            $result = $this->planService->getBundlesByLocation((int) $request->location_id);

            if (!empty($result['bundles']) && count($result['bundles']) > 0) {
                return $this->successResponse('Record found', $result);
            }

            return $this->errorResponse('No bundles found', 404);
        } catch (\Exception $e) {
            \Log::error('Get Bundles Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to load bundles.', 500);
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
                return $this->errorResponse('Location ID is required.', 500);
            }

            $result = $this->planService->getMembershipTypes(
                (int) $request->location_id,
                $request->patient_id ? (int) $request->patient_id : null
            );

            if (!empty($result['memberships']) && count($result['memberships']) > 0) {
                return $this->successResponse('Record found', $result);
            }

            return $this->errorResponse('No memberships found', 404);
        } catch (\Exception $e) {
            \Log::error('Get Memberships Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to load memberships.', 500);
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
            if (!$request->membership_id) {
                return $this->errorResponse('Membership ID is required.', 500);
            }

            $result = $this->planService->getMembershipInfo((int) $request->membership_id);

            return $this->successResponse('Record found', $result);
        } catch (PlanException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            \Log::error('Get Membership Info Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to load membership info.', 500);
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

            if (!$search || strlen($search) < 2) {
                return response()->json(['status' => true, 'data' => ['codes' => []]]);
            }

            $result = $this->planService->searchMembershipCodes(
                $search,
                $request->membership_type_id ? (int) $request->membership_type_id : null
            );

            return response()->json(['status' => true, 'data' => $result]);
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
        $result = $this->planService->getDiscountInfo($request->all());

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 404);
    }

    /**
     * save packages services information.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function savepackages_service(Request $request)
    {
        $result = $this->planService->savePackagesService($request->all());

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 404, $result['data'] ?? []);
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
            return $this->errorResponse('Validation failed', 422, [
                'errors' => $validator->errors()
            ]);
        }

        try {
            $servicesData = $this->planService->addServiceToPackage($request->all());

            return $this->successResponse('Record found', [
                'servicesData' => $servicesData,
            ]);
        } catch (PlanException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            \Log::error('Make Packages Services Data Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to add service to package.', 500);
        }
    }
    /**
     * get discount information for custom package.
     *
     * @return Response
     */
    public function getdiscountinfocustom(Request $request)
    {
        $result = $this->planService->getCustomDiscountInfo($request->all());

        if ($result === false) {
            return false;
        }

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 404);
    }

    /**
     * delete serive from packages
     *
     * @param request
     */
    public function deletepackagesservice(Request $request)
    {
        $result = $this->planService->deletePackageService($request->all());

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 404, $result['data'] ?? []);
    }

    public function deleteconfpackagesservice(Request $request)
    {
        $result = $this->planService->deleteConfigurablePackageService($request->all());

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 404, $result['data'] ?? []);
    }

    /**
     * delete serive from packages
     *
     * @param request
     */
    public function deletepackagesexclusive(Request $request)
    {
        $result = $this->planService->deleteExclusiveService($request->all());

        return response()->json([
            'status' => $result['status'],
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
        $result = $this->planService->getServiceInfoForPackage($request->all());

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 404, $result['data'] ?? []);
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
        $result = $this->planService->getServiceInfoForPlan($request->all());

        if ($result['success']) {
            return $this->successResponse($result['message'], $result['data'] ?? []);
        }

        return $this->errorResponse($result['message'], $result['status_code'] ?? 404, $result['data'] ?? []);
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
        $result = $this->planService->getDiscountInfoForPlan($request->all());

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 404);
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
        $result = $this->planService->getCustomDiscountInfoForPlan($request->all());

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 404);
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
        $result = $this->planService->saveServiceForPlan($request->all());

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 500);
    }

    /**
     * Get service info whan discount not selected
     *
     * @param request
     * @return mixed
     */
    public function getservices_for_zero(Request $request)
    {
        $result = $this->planService->getBundleServices((int) $request->bundle_id);

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 404);
    }

    /**
     * calculate the grand total
     *
     * @param request
     * @return mixed
     */
    public function getgrandtotal(Request $request)
    {
        $result = $this->planService->calculateGrandTotal(
            (string) $request->total,
            (float) $request->cash_amount
        );

        return $this->successResponse('Record found', $result);
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
        $this->authorize('inactivatePlan', Packages::class);

        $result = $this->planService->toggleStatus((int) $request->id, (string) $request->status);

        return $result['success']
            ? $this->successResponse($result['message'])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 400);
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
        $this->authorize('editPlan', Packages::class);

        try {
            $data = $this->planService->getEditFormData($id);
            
            return $this->successResponse('Record found.', $data);
        } catch (PlanException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PackagesController');
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
        try {
            $result = $this->planService->calculateGrandTotalForUpdate(
                (string) $request->random_id,
                (string) $request->total,
                (float) $request->cash_amount
            );

            return $this->successResponse('Record Updated', $result);
        } catch (PlanException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            \Log::error('Get Grand Total Update Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to calculate grand total.', 500);
        }
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

            $result = $this->planService->updateBundlePayment($request->all());

            return $result['success']
                ? $this->successResponse($result['message'])
                : $this->errorResponse($result['message'], $result['status_code'] ?? 500);

        } catch (\Exception $e) {
            \Log::error('Update Bundle Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to update bundle plan: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update plan name for a package based on its bundles/memberships.
     * Delegates to PlanService.
     */
    protected function updatePlanNameForPackage(Packages $package): void
    {
        $this->planService->updatePlanNameForPackage($package);
    }

    /**
     * Update plan package (optimized)
     */
    public function updatepackages(Request $request)
    {
        $request->validate([
            'random_id'      => ['required'],
            'appointment_id' => ['nullable', 'exists:appointments,id'],
        ], [
            'random_id.required'    => 'Package identifier is required',
            'appointment_id.exists' => 'Appointment not found',
        ]);

        try {
            $result = $this->planService->updatePlanPackage($request->all());
            
            return $result['status'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (PlanException $e) {
            \Log::error('Update Packages Error: ' . $e->getMessage());
            
            // Check if it's a settled package error
            if ($e->getCode() == 400 && strpos($e->getMessage(), 'settled') !== false) {
                return $this->successResponse($e->getMessage(), false, ['setteled' => 1]);
            }
            
            return $this->successResponse($e->getMessage(), false);
        } catch (\Exception $e) {
            \Log::error('Update Packages Error: ' . $e->getMessage());
            return $this->successResponse($e->getMessage(), false);
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
        $this->authorize('destroyPlan', Packages::class);

        try {
            $result = $this->planService->deletePlan($id);
            
            return $result['status'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (PlanException $e) {
            // Return clean error message without file path
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            \Log::error('Delete Package Error: ' . $e->getMessage());
            return $this->errorResponse('An error occurred while deleting the package.', 500);
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
        $this->authorize('managePlans', Packages::class);

        try {
            $data = $this->planService->getDisplayData($id);
            
            return $this->successResponse('Record found.', $data);
        } catch (PlanException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PackagesController');
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
        $this->authorize('managePlans', Packages::class);
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

        return $this->successResponse('data found', [
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
        $result = $this->planService->storePayment($request->all());

        if ($result['success']) {
            return $this->successResponse($result['message'], $result['data'] ?? []);
        }

        return $this->errorResponse($result['message'], 500);
    }

    /*
     * Delete the cash that reqquire to delete
     */
    public function deletepackageadvancescash(Request $request)
    {
        $result = $this->planService->deletePayment($request->all());

        if ($result['success']) {
            return $this->successResponse($result['message'], $result['data'] ?? []);
        }

        return $this->errorResponse($result['message'], 404);
    }

    /*
     *  Get the information of appointment against (optimized)
     */
    public function getappointmentinfo(Request $request)
    {
        // Validate required parameters
        if (!$request->patient_id || !$request->location_id) {
            return $this->errorResponse('Patient ID and Location ID are required.', 500);
        }

        try {
            $data = $this->planService->getAppointmentInfo(
                (int) $request->patient_id,
                (int) $request->location_id
            );

            return $this->successResponse('Record found', $data);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Get Appointment Info Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to load appointment information.', 500);
        }
    }

    /*
     * Get sold by data for editing
     */
    public function getSoldByData(Request $request)
    {
        try {
            $result = $this->planService->getSoldByData(
                (int) ($request->package_service_id ?? 0),
                $request->has('package_bundle_id') ? (int) $request->package_bundle_id : null,
                (int) ($request->location_id ?? 0),
                $request->has('config_bundle_ids') && is_array($request->config_bundle_ids) ? $request->config_bundle_ids : null
            );

            if ($result['success']) {
                return $this->successResponse($result['message'], $result['data']);
            }

            return $this->errorResponse($result['message'], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PackagesController');
        }
    }

    /*
     * Update sold by for package service(s)
     */
    public function updateSoldBy(Request $request)
    {
        try {
            $result = $this->planService->updateSoldBy($request->all());

            if ($result['success']) {
                return $this->successResponse($result['message']);
            }

            return $this->errorResponse($result['message'], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PackagesController');
        }
    }

    /*
     * Check if service is duplicate and return appropriate sold by users
     */
    public function checkDuplicateServiceForSoldBy(Request $request)
    {
        try {
            $result = $this->planService->checkDuplicateServiceForSoldBy($request->all());

            if ($result['success']) {
                return $this->successResponse($result['message'], $result['data']);
            }

            return $this->errorResponse($result['message'], 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PackagesController');
        }
    }

    /*
     *  Function for log for package
     */
    public function packagelog($id, $type)
    {
        $this->authorize('viewLog', Packages::class);

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

        return response()->json($records);
    }

    /*
     *  Function for log for package
     */

    public function packagelogexcel($id, $finance_log)
    {
        $this->authorize('viewLog', Packages::class);

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

        return $this->successResponse('Record found', [
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
        $result = $this->planService->resendSms((int) $request->get('id'));

        if ($result['success']) {
            return $this->successResponse($result['message']);
        }

        return $this->errorResponse($result['message'], 404);
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
        $result = $this->planService->getRefundFormData((int) $id);

        return $this->successResponse($result['message'], $result['data']);
    }
    public function updateRefund(Request $request)
    {
        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return $this->successResponse($validator->messages()->first(), false);
        }

        $result = $this->planService->processRefund($request->all());

        if ($result['success']) {
            return $this->successResponse($result['message'], $result['data'] ?? []);
        }

        return $this->errorResponse($result['message'], 500);
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
    public function deleteplanrowtem(Request $request)
    {
        $result = $this->planService->deletePlanRow($request->all());

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
        ]);
    }

    public function resetvoucherpacakgebundles(Request $request)
    {
        $result = $this->planService->resetVoucherPackageBundles($request->all());

        return response()->json(['success' => $result['success']]);
    }
}
