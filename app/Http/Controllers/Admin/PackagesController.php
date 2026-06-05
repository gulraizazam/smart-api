<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentType;
use App\Exceptions\PlanException;
use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Helpers\Financelog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MakePackageServicesRequest;
use App\Http\Requests\Admin\StoreUpdateAppointmentsRequest;
use App\Http\Requests\Admin\UpdatePackagesRequest;
use App\Models\Accounts;
use App\Models\Activity;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AuditTrailChanges;
use App\Models\AuditTrails;
use App\Models\BundleHasServices;
use App\Models\Bundles;
use App\Models\Discounts;
use App\Models\Invoices;
use App\Models\Leads;
use App\Models\LeadStatuses;
use App\Models\Locations;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\Patients;
use App\Models\PaymentModes;
use App\Models\Services;
use App\Models\Settings;
use App\Models\SMSLogs;
use App\Models\StudentVerification;
use App\Models\User;
use App\Services\Membership\StudentVerificationService;
use App\Services\MetaConversionApiService;
use App\Services\Plan\PlanService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PackagesController extends Controller
{
    public function __construct(
        protected readonly PlanService $planService,
        protected readonly StudentVerificationService $studentVerificationService,
    ) {}

    /**
     * Display a listing of the package.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): View
    {
        $this->authorize('managePlans', Packages::class);

        return view('admin.packages.index');
    }

    /**
     * Show the form for creating a new package.
     */
    public function create(Request $request): JsonResponse
    {
        $this->authorize('createPlan', Packages::class);

        try {
            // Get patient ID from route parameter
            $patientId = $request->route('id');

            // Use patient-specific method if patient ID is provided
            if ($patientId) {
                \Log::info('Loading patient-specific plan data for patient: '.$patientId);
                $data = $this->planService->getCreateFormDataForPatient(ACL::getUserCentres(), (int) $patientId);
            } else {
                \Log::info('Loading general plan data (no patient ID)');
                $data = $this->planService->getCreateFormData(ACL::getUserCentres());
            }

            return $this->successResponse('Record found.', $data);
        } catch (\Exception $e) {
            // Round 4 Crypto-H3 — drop trace string (inlines arg PII), keep
            // file/line for debuggability without leaking patient identifiers.
            Log::error('Plans Create Form Data Error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('Failed to load form data.', 500);
        }
    }

    /**
     * Return an array of location base service.
     */
    public function getservices(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            if (! $request->has('location_id') || ! $request->location_id) {
                return $this->errorResponse('Location ID is required.', 500);
            }

            $services = $this->planService->getServicesByLocation(
                (int) $request->location_id,
                Auth::user()->account_id
            );

            if (! empty($services)) {
                return $this->successResponse('Record found', [
                    'service' => $services,
                ]);
            }

            return $this->errorResponse('Record not found', 404);
        } catch (\Exception $e) {
            \Log::error('Get Services Error: '.$e->getMessage());

            return $this->errorResponse('Failed to load services.', 500);
        }
    }

    /**
     * Save bundle service for bundle plan creation
     * Uses the same logic as plan creation but for bundles
     */
    public function savebundle_service(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.edit') && ! Gate::allows('plans.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $result = $this->planService->addBundleService([
                'bundle_id' => $request->bundle_id,
                'location_id' => $request->location_id,
                'net_amount' => $request->net_amount,
                'random_id' => $request->random_id,
                'sold_by' => $request->sold_by ?? null,
                'source_type' => $request->source_type ?? 'bundle',
                // SPA opt-in: persist a draft PackageBundles + PackageService
                // set so the simple-id final-save path can bind them by
                // random_id. Legacy create-bundle.js does not send this and
                // continues to ship the structured array on save.
                'stage_draft' => $request->boolean('stage_draft'),
            ]);

            return $this->successResponse('Bundle service added successfully', $result);
        } catch (PlanException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            \Log::error('Save Bundle Service Error: '.$e->getMessage());

            return $this->errorResponse('Failed to add bundle service.', 500);
        }
    }

    /**
     * Save membership service (Add button in membership creation)
     */
    public function savemembership_service(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.edit') && ! Gate::allows('plans.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $result = $this->planService->addMembershipService([
                'membership_id' => $request->membership_id,
                'location_id' => $request->location_id,
                'net_amount' => $request->net_amount,
                'sold_by' => $request->sold_by ?? null,
            ]);

            return $this->successResponse('Membership service added successfully', $result);
        } catch (PlanException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            \Log::error('Save Membership Service Error: '.$e->getMessage());

            return $this->errorResponse('Failed to add membership service.', 500);
        }
    }

    /**
     * Update membership plan - add payment to existing membership package
     */
    public function updateMembershipPlan(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $packageId = $request->package_id;
            $patientId = $request->patient_id;
            $locationId = $request->location_id;
            $appointmentId = $request->appointment_id;
            $paymentModeId = $request->payment_mode_id;
            $cashAmount = (float) ($request->cash_amount ?? 0);
            $grandTotal = (float) ($request->grand_total ?? 0);
            $isStudentMembership = $request->is_student_membership === '1';
            $membershipTypeId = $request->membership_type_id;

            if (! $packageId) {
                return $this->errorResponse('Package ID is required', 500);
            }

            $package = Packages::find($packageId);
            if (! $package) {
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
            if (! $isStudentMembership && $packageBundle && $packageBundle->membership_type_id) {
                $isStudentMembership = $this->studentVerificationService->isStudentMembership((int) $packageBundle->membership_type_id);
            }

            \Log::info('Edit membership - document check', [
                'is_student_membership_param' => $request->is_student_membership,
                'is_student_membership' => $isStudentMembership,
                'has_files' => $request->hasFile('student_documents'),
                'membership_type_id_from_bundle' => $packageBundle?->membership_type_id,
            ]);

            if ($isStudentMembership) {
                // Get existing verification record
                $existingVerification = StudentVerification::where('package_id', $packageId)->first();
                $existingDocPaths = $existingVerification ? ($existingVerification->document_paths ?? []) : [];

                // Handle document removal
                $documentsToRemove = $request->documents_to_remove ? json_decode($request->documents_to_remove, true) : [];
                if (! empty($documentsToRemove)) {
                    foreach ($documentsToRemove as $docPath) {
                        // Remove from existing paths array
                        $existingDocPaths = array_filter($existingDocPaths, fn ($path) => $path !== $docPath);
                        // Delete file from storage
                        $fullPath = storage_path('app/public/'.$docPath);
                        if (file_exists($fullPath)) {
                            unlink($fullPath);
                        }
                    }
                    $existingDocPaths = array_values($existingDocPaths); // Re-index array
                    $messages[] = count($documentsToRemove).' document(s) removed';

                    \Log::info('Documents removed', [
                        'package_id' => $packageId,
                        'removed_count' => count($documentsToRemove),
                        'remaining_count' => count($existingDocPaths),
                    ]);
                }

                // Store new documents IMMEDIATELY
                $documents = $request->file('student_documents', []);
                $newStoredPaths = $this->storeStudentDocumentsImmediately($documents);
                $hasNewDocuments = ! empty($newStoredPaths);

                // Merge existing (after removal) with new documents
                $allDocumentPaths = array_merge($existingDocPaths, $newStoredPaths);

                \Log::info('Student membership - document processing', [
                    'existing_after_removal' => count($existingDocPaths),
                    'new_uploaded' => count($newStoredPaths),
                    'total_documents' => count($allDocumentPaths),
                ]);

                // Update or create verification record
                if (! empty($allDocumentPaths)) {
                    $membershipCodeId = $packageBundle?->membership_code_id;

                    if ($existingVerification) {
                        // Update existing record
                        $existingVerification->update([
                            'document_paths' => $allDocumentPaths,
                        ]);
                    } else {
                        // Create new record
                        $this->studentVerificationService->createVerificationRecord([
                            'patient_id' => $patientId,
                            'membership_id' => $membershipCodeId,
                            'membership_type_id' => $membershipTypeId ?: $packageBundle?->membership_type_id,
                            'package_id' => $packageId,
                            'document_paths' => $allDocumentPaths,
                        ]);
                    }

                    if ($hasNewDocuments) {
                        $documentsUploaded = true;
                        $messages[] = count($newStoredPaths).' document(s) uploaded';
                    }
                } elseif ($existingVerification && empty($allDocumentPaths)) {
                    // All documents removed - delete the verification record
                    $existingVerification->delete();
                    \Log::info('Verification record deleted - no documents remaining', ['package_id' => $packageId]);
                }

                // Check if student membership has documents
                $hasStudentDocuments = ! empty($allDocumentPaths);

                \Log::info('Student membership - final document status', [
                    'has_documents' => $hasStudentDocuments,
                    'document_count' => count($allDocumentPaths),
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

                $advance = PackageAdvances::createRecord($packageAdvanceData, $package);
                $paymentAdded = true;
                $messages[] = 'Payment recorded';

                // Record the payment on the patient's Activity feed. The
                // regular-plan edit path logs this via PlanService; the
                // membership edit path runs here, so it was the one flow that
                // recorded a payment with no activity trail. Non-fatal.
                try {
                    $payer = \App\Models\Patients::find($patientId);
                    $payLocation = Locations::with('city')->find($locationId);
                    if ($payer) {
                        ActivityLogger::logPaymentReceived($advance, $package, $payer, $payLocation);
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Membership payment activity log failed', [
                        'package_id' => $packageId,
                        'error' => $e->getMessage(),
                    ]);
                }

                \Log::info('Payment added in edit', [
                    'package_id' => $packageId,
                    'cash_amount' => $cashAmount,
                    'grand_total_after' => $grandTotal,
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
                'is_already_consumed' => $isAlreadyConsumed,
            ]);

            // ========================================
            // STEP 4: Determine if membership should be consumed
            // ========================================
            // For student membership: consume only if fully paid AND has documents
            // For non-student membership: consume if fully paid
            $shouldConsume = false;

            if (! $isAlreadyConsumed && $isFullyPaid) {
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
                        $durationDays = (int) ($membershipType->period ?? 365);

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
                            'patient_id' => $patientId,
                        ]);
                    }
                }

                // Create 'out' payment entries for settled amount
                $taxExclusiveTotal = $packageBundle->tax_exclusive_net_amount;
                $taxTotal = $packageBundle->tax_price;

                $settlePaymentMode = PaymentModes::where('name', 'Settle Amount')->first();
                $settlePaymentModeId = $settlePaymentMode?->id;

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
            // Round 4 Crypto-H3 — drop trace, keep file/line.
            \Log::error('Update Membership Plan Error: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('Failed to update membership.', 500);
        }
    }

    /**
     * Get bundles by location for bundle creation
     */
    public function getbundles(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            if (! $request->has('location_id') || ! $request->location_id) {
                return $this->errorResponse('Location ID is required.', 500);
            }

            $result = $this->planService->getBundlesByLocation((int) $request->location_id);

            if (! empty($result['bundles'])) {
                return $this->successResponse('Record found', $result);
            }

            return $this->errorResponse('No bundles found', 404);
        } catch (\Exception $e) {
            \Log::error('Get Bundles Error: '.$e->getMessage());

            return $this->errorResponse('Failed to load bundles.', 500);
        }
    }

    /**
     * Get membership types for membership creation
     */
    public function getmemberships(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            if (! $request->has('location_id') || ! $request->location_id) {
                return $this->errorResponse('Location ID is required.', 500);
            }

            $result = $this->planService->getMembershipTypes(
                (int) $request->location_id,
                $request->patient_id ? (int) $request->patient_id : null
            );

            if (! empty($result['memberships'])) {
                return $this->successResponse('Record found', $result);
            }

            return $this->errorResponse('No memberships found', 404);
        } catch (\Exception $e) {
            \Log::error('Get Memberships Error: '.$e->getMessage());

            return $this->errorResponse('Failed to load memberships.', 500);
        }
    }

    /**
     * Get membership type info (price) for membership creation
     */
    public function getmembershipinfo(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            if (! $request->membership_id) {
                return $this->errorResponse('Membership ID is required.', 500);
            }

            $result = $this->planService->getMembershipInfo((int) $request->membership_id);

            return $this->successResponse('Record found', $result);
        } catch (PlanException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            \Log::error('Get Membership Info Error: '.$e->getMessage());

            return $this->errorResponse('Failed to load membership info.', 500);
        }
    }

    /**
     * Search membership codes by keyword and check if assigned
     */
    public function searchMembershipCodes(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $search = $request->search;

            if (! $search || strlen($search) < 2) {
                return response()->json(['status' => true, 'data' => ['codes' => []]]);
            }

            $result = $this->planService->searchMembershipCodes(
                $search,
                $request->membership_type_id ? (int) $request->membership_type_id : null
            );

            return response()->json(['status' => true, 'data' => $result]);
        } catch (\Exception $e) {
            \Log::error('Search Membership Codes Error: '.$e->getMessage());

            return response()->json(['status' => false, 'message' => 'Failed to search codes.']);
        }
    }

    /**
     * get discount information.
     */
    public function getdiscountinfo(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $result = $this->planService->getDiscountInfo($request->all());

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 404);
    }

    /**
     * save packages services information.
     */
    public function savepackages_service(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.edit') && ! Gate::allows('plans.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $result = $this->planService->savePackagesService($request->all());

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 404, $result['data'] ?? []);
    }

    /**
     * Add service/bundle to package (optimized)
     */
    public function makePackagesServicesData(MakePackageServicesRequest $request): JsonResponse
    {
        if (! Gate::allows('plans.edit') && ! Gate::allows('plans.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        \Log::info('=== makePackagesServicesData (POST BUNDLE PATH) CALLED ===', [
            'bundle_id_from_request' => $request->bundle_id,
            'discount_id' => $request->discount_id,
            'random_id' => $request->random_id,
        ]);

        try {
            $servicesData = $this->planService->addServiceToPackage($request->all());

            return $this->successResponse('Record found', [
                'servicesData' => $servicesData,
            ]);
        } catch (PlanException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            \Log::error('Make Packages Services Data Error: '.$e->getMessage());

            return $this->errorResponse('Failed to add service to package.', 500);
        }
    }

    /**
     * get discount information for custom package.
     *
     * @return Response
     */
    public function getdiscountinfocustom(Request $request): JsonResponse|false
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

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
     * @param Request
     */
    public function deletepackagesservice(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.service.delete') && ! Gate::allows('plans.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $result = $this->planService->deletePackageService($request->all());

        if ($result['success']) {
            return $this->successResponse($result['message'], $result['data'] ?? []);
        }

        // Business-rule failures (e.g. service already consumed) return 200 so the
        // front-end success handler can branch on `status:false` + `data.del` and
        // surface the toast/alert. jQuery's error handler is not wired at any of
        // the delete call sites; a 4xx here would silently drop the message.
        return response()->json([
            'success' => false,
            'status' => false,
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
            'errors' => $result['errors'] ?? [],
        ], 200);
    }

    public function deleteconfpackagesservice(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.service.delete') && ! Gate::allows('plans.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $result = $this->planService->deleteConfigurablePackageService($request->all());

        if ($result['success']) {
            return $this->successResponse($result['message'], $result['data'] ?? []);
        }

        return response()->json([
            'success' => false,
            'status' => false,
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
            'errors' => $result['errors'] ?? [],
        ], 200);
    }

    /**
     * Atomic cascade delete for a configurable Buy/Get group — wraps
     * voucher refunds + per-row deletes in a single DB transaction
     * so a mid-batch failure rolls back EVERYTHING. Replaces the
     * SPA's earlier best-effort sequential loop. Closes G6.
     */
    public function cascadeDeleteGroup(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.service.delete') && ! Gate::allows('plans.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'random_id' => 'nullable|string|max:255',
            'package_total' => 'nullable',
            'patient_id' => 'nullable|integer',
            'vouchers' => 'nullable|array',
            'vouchers.*.voucher_id' => 'required_with:vouchers|integer',
            'vouchers.*.amount' => 'required_with:vouchers|numeric|min:0',
        ]);

        $result = $this->planService->cascadeDeleteGroup($validated);

        if ($result['success']) {
            return $this->successResponse($result['message'], $result['data'] ?? []);
        }

        return response()->json([
            'success' => false,
            'status' => false,
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
            'errors' => $result['errors'] ?? [],
        ], $result['status_code'] ?? 500);
    }

    /**
     * delete serive from packages
     *
     * @param Request
     */
    public function deletepackagesexclusive(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.service.delete') && ! Gate::allows('plans.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $result = $this->planService->deleteExclusiveService($request->all());

        return response()->json([
            'status' => $result['status'],
        ]);
    }

    /**
     * save package
     *
     * @param Request
     */
    /**
     * Save plan package (optimized)
     */
    public function savepackages(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            // IMPORTANT: Store student documents IMMEDIATELY at the start of the request
            // before any other processing can consume/delete the temp files
            $storedDocumentPaths = [];
            if ($request->hasFile('student_documents')) {
                $storedDocumentPaths = $this->storeStudentDocumentsImmediately($request->file('student_documents'));
                \Log::info('Documents stored at controller entry', [
                    'count' => count($storedDocumentPaths),
                    'paths' => $storedDocumentPaths,
                ]);
            }

            // Pass the full request object and pre-stored document paths
            $data = $request->all();
            $data['pre_stored_document_paths'] = $storedDocumentPaths;

            $result = $this->planService->savePlanPackage($data, $request);

            return response()->json($result);
        } catch (PlanException $e) {
            \Log::error('Save Packages Error: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Save Packages Error: '.$e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while saving the package',
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

        // Round 4 C3 — store under storage/app/student_verifications (private),
        // NOT storage/app/public/student_verifications which is exposed via the
        // public/storage symlink. Files are streamed only through the
        // authenticated admin.files.student_verification route.
        $storagePath = storage_path('app/student_verifications');
        if (! file_exists($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

        foreach ($documents as $index => $document) {
            if ($document instanceof UploadedFile && $document->isValid()) {
                try {
                    $extension = strtolower($document->getClientOriginalExtension() ?: 'jpg');
                    if (! in_array($extension, $allowedExtensions, true)) {
                        \Log::warning('Student document upload rejected: invalid extension', ['extension' => $extension]);

                        continue;
                    }
                    $filename = 'student_doc_'.time().'_'.$index.'_'.uniqid().'.'.$extension;

                    // Move the file immediately
                    $document->move($storagePath, $filename);

                    $path = 'student_verifications/'.$filename;
                    $storedPaths[] = $path;

                    \Log::info('Document stored immediately', [
                        'path' => $path,
                        'original_name' => $document->getClientOriginalName(),
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to store document immediately', [
                        'index' => $index,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $storedPaths;
    }


    /**
     * Get service info
     *
     * @param Request
     * @return mixed
     */
    public function getserviceinfo(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $result = $this->planService->getServiceInfoForPackage($request->all());

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 404, $result['data'] ?? []);
    }

    /**
     * Get service info for simple plans (non-bundle)
     * Directly queries services table instead of bundles
     *
     * @param Request
     * @return mixed
     */
    public function getserviceinfo_for_plan(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

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
     * @param Request
     * @return mixed
     */
    public function getdiscountinfo_for_plan(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $result = $this->planService->getDiscountInfoForPlan($request->all());

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 404);
    }

    /**
     * Get custom discount info for simple plans (non-bundle)
     * Directly queries services table instead of bundles
     *
     * @param Request
     * @return mixed
     */
    public function getdiscountinfocustom_for_plan(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $result = $this->planService->getCustomDiscountInfoForPlan($request->all());

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 404);
    }

    /**
     * Save service to plan - handles both simple and configurable discounts.
     * For plans, services are stored directly (not via bundles).
     *
     * @param Request
     * @return mixed
     */
    public function savepackages_service_for_plan(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.edit') && ! Gate::allows('plans.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $result = $this->planService->saveServiceForPlan($request->all());

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 500);
    }

    /**
     * Reserve voucher amount at the moment the user clicks "Add" on
     * the plan form. Decrements `user_vouchers.amount` immediately so
     * picking the same voucher on a subsequent row reflects the
     * reduced balance — even before the plan is saved.
     */
    public function reserveVoucherForPlan(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.edit') && ! Gate::allows('plans.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $validated = $request->validate([
            'voucher_id' => 'required|integer|exists:discounts,id',
            'patient_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0',
        ]);

        $result = $this->planService->reserveVoucherAmount(
            (int) $validated['voucher_id'],
            (int) $validated['patient_id'],
            (float) $validated['amount'],
        );

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 500, $result['data'] ?? []);
    }

    /**
     * Refund a prior reservation — called when the user removes a
     * plan row or abandons the in-progress plan.
     */
    public function refundVoucherForPlan(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $validated = $request->validate([
            'voucher_id' => 'required|integer|exists:discounts,id',
            'patient_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0',
        ]);

        $result = $this->planService->refundVoucherAmount(
            (int) $validated['voucher_id'],
            (int) $validated['patient_id'],
            (float) $validated['amount'],
        );

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 500);
    }

    /**
     * Get service info whan discount not selected
     *
     * @param Request
     * @return mixed
     */
    public function getservices_for_zero(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $result = $this->planService->getBundleServices((int) $request->bundle_id);

        return $result['success']
            ? $this->successResponse($result['message'], $result['data'] ?? [])
            : $this->errorResponse($result['message'], $result['status_code'] ?? 404);
    }

    /**
     * calculate the grand total
     *
     * @param Request
     * @return mixed
     */
    public function getgrandtotal(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $result = $this->planService->calculateGrandTotal(
            (string) $request->total,
            (float) $request->cash_amount
        );

        return $this->successResponse('Record found', $result);
    }

    /**
     * Display a User As package in datatables.
     *
     * @param Request
     */

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     */
    public function status(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.activate') && ! Gate::allows('plans.deactivate')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

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
    public function store(Request $request): JsonResponse
    {
        abort(404);
    }

    public function show(int $id): JsonResponse
    {
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    /**
     * Get edit form data for package (optimized)
     */
    public function edit(int $id): JsonResponse
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
     * @param Request
     * @return mixed
     */
    public function getgrandtotal_update(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

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
            \Log::error('Get Grand Total Update Error: '.$e->getMessage());

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
    public function updatebundle(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $request->validate([
                'package_id' => 'required|exists:packages,id',
                'appointment_id' => 'required|exists:appointments,id',
                'payment_mode_id' => 'nullable|exists:payment_modes,id',
                'cash_amount' => 'nullable|numeric|min:0',
                'grand_total' => 'nullable|numeric',
            ]);

            $result = $this->planService->updateBundlePayment($request->all());

            return $result['success']
                ? $this->successResponse($result['message'])
                : $this->errorResponse($result['message'], $result['status_code'] ?? 500);

        } catch (\Exception $e) {
            \Log::error('Update Bundle Error: '.$e->getMessage());

            return $this->errorResponse('Failed to update bundle plan.', 500);
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
    public function updatepackages(UpdatePackagesRequest $request): JsonResponse
    {

        try {
            $result = $this->planService->updatePlanPackage($request->all());

            return $result['status'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (PlanException $e) {
            \Log::error('Update Packages Error: '.$e->getMessage());

            // Check if it's a settled package error
            if ($e->getCode() == 400 && str_contains($e->getMessage(), 'settled')) {
                return $this->successResponse($e->getMessage(), false, ['setteled' => 1]);
            }

            return $this->successResponse($e->getMessage(), false);
        } catch (\Exception $e) {
            \Log::error('Update Packages Error: '.$e->getMessage());

            return $this->successResponse('An error occurred. Please try again.', false);
        }
    }

    protected function verifyRefundsFields(Request $request): \Illuminate\Contracts\Validation\Validator
    {
        return $validator = Validator::make($request->all(), [
            'refund_amount' => 'required',
            'refund_note' => 'required',
            'payment_mode_id' => 'required',

        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * Delete plan package (optimized)
     */
    public function destroy(int $id): JsonResponse
    {
        if (! Gate::allows('plans.destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $this->authorize('destroyPlan', Packages::class);

        try {
            $result = $this->planService->deletePlan($id);

            return $result['status'] ? $this->successResponse($result['message']) : $this->errorResponse($result['message'], 400);
        } catch (PlanException $e) {
            // Honour the exception's own status (e.g. 409 hasChildRecords,
            // 404 notFound). Forcing 500 here masks the real reason — the
            // SPA's generic 500 mapping then shows "Something went wrong"
            // instead of the actionable message body.
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        } catch (\Exception $e) {
            \Log::error('Delete Package Error: '.$e->getMessage());

            return $this->errorResponse('An error occurred while deleting the package.', 500);
        }
    }

    /**
     * display the package.
     */
    /**
     * Display package details (optimized)
     */
    public function display(int $id): JsonResponse
    {
        if (! Gate::allows('plans.detail.view') && ! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

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

    /**
     * SPA-print payload: display data + org/centre header (NTN/STN, address,
     * head-office phone). Same auth gate as `display`. Replaces the legacy
     * dompdf-rendered `package_pdf` Blade — the SPA owns the layout now.
     */
    public function printData(int $id): JsonResponse
    {
        if (! Gate::allows('plans.print')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $this->authorize('managePlans', Packages::class);

        try {
            $data = $this->planService->getPrintData($id);

            return $this->successResponse('Record found.', $data);
        } catch (PlanException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PackagesController');
        }
    }

    private function appointmentPackage($packageadvances): array|\Illuminate\Database\Eloquent\Collection
    {

        if ($packageadvances->count() > 0) {

            $packageAdvancesCollection = [];
            foreach ($packageadvances as $packageadvance) {
                if ($packageadvance->cash_flow == 'out' && $packageadvance->is_tax == 0) {
                    if ($packageadvance->refund_note !== null) {
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
     */
    public function package_pdf(int $id): \Illuminate\Http\Response
    {
        // Route-level gate kept via PlanPolicy::managePlans → plans.list.view.
        // `plans.print` is the more specific permission; either suffices
        // because exporting the PDF is a viewing action — operators who
        // can see the plan can print it. Strict-print-only roles should
        // remove `plans.list.view` to enforce that distinction.
        $this->authorize('managePlans', Packages::class);

        if (! Gate::allows('plans.print') && ! Gate::allows('plans.list.view')) {
            abort(403);
        }

        $package = Packages::find($id);

        $location_info = Locations::find($package->location_id);

        $account_info = Accounts::find($package->account_id);

        // Include service, bundle, membershipType, serviceBundle, and child package
        // service relationships — the last is a last-resort fallback when the primary
        // bundle_id reference no longer resolves (see normalizeBundleDisplayRelations).
        $packagebundles = PackageBundles::with(['bundle', 'service', 'membershipType', 'serviceBundle.service', 'packageservice.service'])->where('package_id', '=', $package->id)->get();

        $this->planService->normalizeBundleDisplayRelations($packagebundles);

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

        // For membership plans, use PackageBundles sum; for others use PackageService sum.
        // Cast to float — Eloquent `sum()` on a decimal column returns a
        // string, which would break the `number_format()` call below (PHP 8
        // rejects strings in numeric functions).
        if ($package->plan_type === 'membership') {
            $packageservices_price = (float) PackageBundles::where('package_id', '=', $package->id)->sum('tax_including_price');
        } else {
            $packageservices_price = (float) PackageService::with('service')->where('package_id', '=', $package->id)->sum('package_services.price');
        }
        $cash_amount = (float) $cash_amount_in - (float) $cash_amount_out;
        /* We discuss it in future what happen next */
        // $grand_total = number_format($package->total_price - $cash_amount_in);
        $grand_total = number_format($packageservices_price);
        $services = Services::getServices();
        $discount = Discounts::getDiscount(Auth::user()->account_id);

        $paymentmodes = PaymentModes::get()->pluck('name', 'id');
        $paymentmodes->prepend('Select Payment Mode', '');

        $company_phone_number = Settings::where('slug', '=', 'sys-headoffice')->first();

        $content = view('admin.packages.packagepdf', compact('package', 'packagebundles', 'packageservices', 'packageadvances', 'services', 'discount', 'paymentmodes', 'grand_total', 'location_info', 'account_info', 'company_phone_number'));
        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($content->render());

        return $pdf->stream('treatment-plans-invoice-C-'.$package->patient_id.'.pdf');
    }

    /*
     * Fetch a package_advance row + its payment-mode list for the
     * edit-payment dialog. Tenant-scoped to prevent IDOR.
     */
    public function editpackageadvancescashindex(int $id, int $package_id): JsonResponse
    {
        // Allow READ if the operator holds the master `plans.cash.edit`
        // OR any of the field-level cash perms — a user with only
        // `cash.edit_date` legitimately needs to open the form to change
        // that field. The dialog itself locks the other inputs read-only
        // and the save endpoint enforces per-field perms server-side, so
        // surfacing the payment for those users isn't a leak.
        $canOpen = \Illuminate\Support\Facades\Gate::allows('plans.cash.edit')
            || \Illuminate\Support\Facades\Gate::allows('plans.cash.edit_amount')
            || \Illuminate\Support\Facades\Gate::allows('plans.cash.edit_date')
            || \Illuminate\Support\Facades\Gate::allows('plans.cash.edit_payment_mode');
        if (! $canOpen) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        $accountId = (int) \Illuminate\Support\Facades\Auth::user()->account_id;

        // Tenant-scoped fetch — bare ::find() let an attacker fetch any
        // tenant's payment by guessing the id. firstOrFail throws 404
        // (Laravel exception handler) for both "doesn't exist" and
        // "belongs to another tenant", so no existence-leak.
        $pack_adv_info = PackageAdvances::query()
            ->where('id', $id)
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        // Same scope for the package_id route param — prevents the
        // attacker from confirming another tenant's plan id even when
        // the advance id check passes.
        if ((int) $pack_adv_info->package_id !== (int) $package_id) {
            return $this->errorResponse('Payment does not belong to that plan.', 404);
        }

        $paymentmodes = PaymentModes::query()
            ->where('account_id', $accountId)
            ->where('type', 'application')
            ->whereNull('deleted_at')
            ->get();

        return $this->successResponse('data found', [
            'pack_adv_info' => $pack_adv_info,
            'package_id' => $package_id,
            'paymentmodes' => $paymentmodes,
        ]);
    }

    /*
     * Save an edit on an existing package_advance row (cash_amount and/
     * or payment_mode). Tenant + permission + amount validation enforced
     * here; `PlanService::storePayment` does the heavy lifting.
     */
    public function storepackageadvancescash(Request $request): JsonResponse
    {
        // Master OR any field-level cash perm grants entry to the save
        // endpoint. Per-field enforcement happens below — the operator
        // can only mutate the fields they explicitly hold a perm for.
        $canMaster   = \Illuminate\Support\Facades\Gate::allows('plans.cash.edit');
        $canAmount   = $canMaster || \Illuminate\Support\Facades\Gate::allows('plans.cash.edit_amount');
        $canDate     = $canMaster || \Illuminate\Support\Facades\Gate::allows('plans.cash.edit_date');
        $canPayMode  = $canMaster || \Illuminate\Support\Facades\Gate::allows('plans.cash.edit_payment_mode');

        if (! $canAmount && ! $canDate && ! $canPayMode) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        $accountId = (int) \Illuminate\Support\Facades\Auth::user()->account_id;

        // Tenant-scoped + soft-delete-aware FK validation. Strict-positive
        // amount: cash_amount must be a whole rupee > 0 (`integer` rejects
        // scientific notation, fractional, and negative values). Without
        // this, a malicious / careless caller could drop an advance to
        // 0 and silently cancel the payment, or flip the amount negative
        // and drain the pool via the observer.
        $request->validate([
            'package_advances_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('package_advances', 'id')->where('account_id', $accountId)->whereNull('deleted_at')],
            'package_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('packages', 'id')->where('account_id', $accountId)->whereNull('deleted_at')],
            'payment_mode_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('payment_modes', 'id')->where('account_id', $accountId)->whereNull('deleted_at')],
            'cash_amount' => 'required|integer|min:1|max:99999999',
            'created_at' => 'required|date|before_or_equal:today',
        ]);

        // Per-field enforcement: for any field the operator lacks perm
        // for, ignore the posted value and pin it to the existing row.
        // This makes the SPA's per-field read-only UX a UX hint only —
        // the server is the authority. Without this, a hand-crafted
        // request could mutate amount/payment_mode while the operator
        // technically only holds `plans.cash.edit_date`.
        $existing = PackageAdvances::query()
            ->where('id', (int) $request->input('package_advances_id'))
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->firstOrFail();

        $payload = $request->all();
        if (! $canAmount) {
            $payload['cash_amount'] = (int) $existing->cash_amount;
        }
        if (! $canPayMode) {
            $payload['payment_mode_id'] = (int) $existing->payment_mode_id;
        }
        if (! $canDate) {
            // Persist the original created_at date so the storePayment
            // service doesn't re-format it to today's clock time.
            $payload['created_at'] = $existing->created_at?->format('Y-m-d');
        }

        $result = $this->planService->storePayment($payload);

        if ($result['success']) {
            return $this->successResponse($result['message'], $result['data'] ?? []);
        }

        return $this->errorResponse($result['message'], 500);
    }

    /*
     * Delete an existing package_advance row. Tenant + permission gated;
     * additional SoD check (creator ≠ deleter) blocks an operator from
     * silently erasing a payment they entered themselves.
     */
    public function deletepackageadvancescash(Request $request): JsonResponse
    {
        if (! \Illuminate\Support\Facades\Gate::allows('plans.cash.delete')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        $accountId = (int) \Illuminate\Support\Facades\Auth::user()->account_id;

        $request->validate([
            'package_advance_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('package_advances', 'id')->where('account_id', $accountId)->whereNull('deleted_at')],
        ]);

        // Separation of duties — the operator who recorded the payment
        // must not be the one to delete it. Mirrors the cancel() SoD
        // check on PackageAdvancesController.
        $advance = PackageAdvances::where(['id' => $request->package_advance_id, 'account_id' => $accountId])->first();
        if ($advance && (int) $advance->created_by === (int) \Illuminate\Support\Facades\Auth::user()->id) {
            return $this->errorResponse(
                'You cannot delete a payment you created. Another admin must delete it.',
                403,
            );
        }

        $result = $this->planService->deletePayment($request->all());

        if ($result['success']) {
            return $this->successResponse($result['message'], $result['data'] ?? []);
        }

        return $this->errorResponse($result['message'], 404);
    }

    /*
     *  Get the information of appointment against (optimized)
     */
    public function getappointmentinfo(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        // Validate required parameters
        if (! $request->patient_id || ! $request->location_id) {
            return $this->errorResponse('Patient ID and Location ID are required.', 500);
        }

        try {
            $data = $this->planService->getAppointmentInfo(
                (int) $request->patient_id,
                (int) $request->location_id
            );

            return $this->successResponse('Record found', $data);
        } catch (\Exception $e) {
            Log::error('Get Appointment Info Error: '.$e->getMessage());

            return $this->errorResponse('Failed to load appointment information.', 500);
        }
    }

    /*
     * Get sold by data for editing
     */
    public function getSoldByData(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.sold_by.edit') && ! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

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
    public function updateSoldBy(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.sold_by.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

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
    public function checkDuplicateServiceForSoldBy(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.sold_by.edit') && ! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

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
    public function packagelog(int $id, string $type): View
    {
        if (! Gate::allows('plans.log.view')) {
            abort(403);
        }

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

        $find_ids[] = $id;

        // Eager-load auditTrailChanges (was 2 queries per audit trail row in
        // the loop below — line 1632 + duplicate at line 1647 fetched the
        // same data via the same audit_trail_id).
        $audittrails = AuditTrails::with('auditTrailChanges')
            ->whereIn('table_record_id', $find_ids)
            ->where('audit_trail_table_name', '=', Config::get('constants.package_advance_table_name_log'))
            ->orderBy('created_at', 'asc')
            ->get();

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

            $audittrail_changes = $audittrail->auditTrailChanges;

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
            if (! isset($finance_log[$audittrail->id]['cash_flow']) && $action_array[$audittrail->audit_trail_action_name] != 'Delete') {

                // Was a second copy of the same query — reuse the eager-loaded relation.
                $type_2_detail = $audittrail->auditTrailChanges;

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

    public function planDatatable(Request $request, int $id): JsonResponse
    {
        if (! Gate::allows('plans.log.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }


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

        $find_ids[] = $id;

        [$orderBy, $order] = getSortBy($request);

        $iTotalRecords = AuditTrails::whereIn('table_record_id', $find_ids)
            ->where(
                'audit_trail_table_name',
                Config::get('constants.package_advance_table_name_log')
            )->count();

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        // Eager-load auditTrailChanges (was 2 queries per audit trail row in
        // the loop below — same N+1 pattern as in packagelog() above).
        $audittrails = AuditTrails::with('auditTrailChanges')
            ->whereIn('table_record_id', $find_ids)
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

            $audittrail_changes = $audittrail->auditTrailChanges;

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
            if (! isset($finance_log[$audittrail->id]['cash_flow']) && $action_array[$audittrail->audit_trail_action_name] != 'Delete') {

                // Was a second copy of the same query — reuse the eager-loaded relation.
                $type_2_detail = $audittrail->auditTrailChanges;

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

        if (! empty($finance_log)) {

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

    public function packagelogexcel(int $id, mixed $finance_log): void
    {
        if (! Gate::allows('plans.log.export')) {
            abort(403);
        }

        $this->authorize('viewLog', Packages::class);

        $spreadsheet = new Spreadsheet;
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
            if ((isset($log['package_id']) && $log['package_id'] == $id) || ! isset($log['package_id'])) {
                $activeSheet->setCellValue('A'.$counter, $count++);
                $activeSheet->setCellValue('B'.$counter, $log['cash_flow'] ?? '-');
                $activeSheet->setCellValue('C'.$counter, $log['cash_amount'] ?? '-');
                $activeSheet->setCellValue('D'.$counter, $log['is_refund'] ?? '-');
                $activeSheet->setCellValue('E'.$counter, $log['is_adjustment'] ?? '-');
                $activeSheet->setCellValue('F'.$counter, $log['is_tax'] ?? '-');
                $activeSheet->setCellValue('G'.$counter, $log['is_cancel'] ?? '-');
                $activeSheet->setCellValue('H'.$counter, ($log['action'] == 'Delete') ? 'Yes' : '-');
                $activeSheet->setCellValue('I'.$counter, $log['refund_note'] ?? '-');
                $activeSheet->setCellValue('J'.$counter, $log['payment_mode_id'] ?? '-');
                $activeSheet->setCellValue('K'.$counter, $log['appointment_type_id'] ?? '-');
                $activeSheet->setCellValue('L'.$counter, $log['location_id'] ?? '-');
                $activeSheet->setCellValue('M'.$counter, $log['created_by'] ?? '-');
                $activeSheet->setCellValue('N'.$counter, isset($log['cash_flow']) ? ($log['updated_by'] ?? '-') : $log['user_id']);
                $activeSheet->setCellValue('O'.$counter, $log['package_id'] ?? '-');
                $activeSheet->setCellValue('P'.$counter, $log['invoice_id'] ?? '-');
                $activeSheet->setCellValue('Q'.$counter, isset($log['created_at']) ? $log['created_at'] == $log['created_at_orignal'] ? '-' : $log['created_at'] : '-');
                $activeSheet->setCellValue('R'.$counter, isset($log['updated_at']) ? $log['updated_at'] == $log['updated_at_orignal'] ? '-' : $log['updated_at'] : '-');

                if ($log['action'] == 'Delete') {
                    $activeSheet->setCellValue('S'.$counter, '-');
                    $activeSheet->setCellValue('T'.$counter, '-');
                } else {
                    $activeSheet->setCellValue('S'.$counter, isset($log['created_at_orignal']) ? Carbon::parse($log['created_at_orignal'])->format('F j,Y h:i A') : '-');
                    $activeSheet->setCellValue('T'.$counter, isset($log['updated_at_orignal']) ? Carbon::parse($log['updated_at_orignal'])->format('F j,Y h:i A') : '-');
                }

                $activeSheet->setCellValue('U'.$counter, isset($log['deleted_at']) ? Carbon::parse($log['deleted_at'])->format('F j, Y h:i A') : '-');

                $counter++;

                if (isset($log['detail_log']) && count($log['detail_log'])) {

                    $countt = 1;

                    $activeSheet->setCellValue('H'.$counter, '#')->getStyle('H'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('I'.$counter, 'Field Name')->getStyle('I'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('J'.$counter, 'Before')->getStyle('J'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('K'.$counter, 'After')->getStyle('K'.$counter)->getFont()->setBold(true);

                    $counter++;

                    foreach ($log['detail_log'] as $detail) {
                        $activeSheet->setCellValue('H'.$counter, $countt++);
                        $activeSheet->setCellValue('I'.$counter, $detail['field_name'] ?? '-');
                        $activeSheet->setCellValue('J'.$counter, $detail['field_before'] ?? '-');
                        $activeSheet->setCellValue('K'.$counter, $detail['field_after'] ?? '-');

                        $counter++;
                    }
                }
            }
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'PackageLog'.'.xlsx"'); /* -- $filename is  xsl filename --- */
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Load plan Sms History.
     */
    public function showSMSLogs(int $id): JsonResponse
    {
        if (! Gate::allows('plans.sms_log.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $SMSLogs = SMSLogs::where('package_id', '=', $id)->orderBy('created_at', 'desc')->get();

        return $this->successResponse('Record found', [
            'SMSLogs' => $SMSLogs,
        ]);
    }

    /**
     * Re-send Plan SMS
     *
     * @param  StoreUpdateAppointmentsRequest  $request
     */
    public function sendLogSMS(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.sms_log.view') && ! Gate::allows('plans.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

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
    public function getpackage(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $package = Packages::where('name', 'LIKE', "%{$request->q}%")->select('name', 'id')->get();

        return response()->json($package);
    }

    public function getPlans(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.list.view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $plans = Packages::where('patient_id', $request->patient_id)->pluck('name');

        return response()->json(['stataus' => 1, 'message' => 'plan found', 'plans' => $plans]);
    }

    public function editRefund(int $id): JsonResponse
    {
        $result = $this->planService->getRefundFormData((int) $id);

        return $this->successResponse($result['message'], $result['data']);
    }

    public function updateRefund(Request $request): JsonResponse
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

    protected function verifyFields(Request $request): \Illuminate\Contracts\Validation\Validator
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

    public function viewPackage(int $id): View
    {
        if (! Gate::allows('plans.detail.view') && ! Gate::allows('plans.list.view')) {
            abort(403);
        }


        $url = route('admin.packages.edit', $id);

        return view('admin.packages.details', compact('url'));
    }

    public function storeRecord($package, $request): ?bool
    {

        $packageBundledata['random_id'] = $package->random_id;
        $packageBundledata['is_allocate'] = 1;
        if (isset($request['package_bundles'])) {
            return DB::transaction(function () use ($package, $request, &$packageBundledata) {
                // Hoist location lookup outside loop — same location_id for all bundles
                $location_information = Locations::find($request->location_id);
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
                        // `service_price` MUST be the catalog regular
                        // per-row price (`bundle_has_services.service_price`),
                        // not the pre-distributed sold-share in
                        // `calculated_price`. Same bug as the two
                        // `addBundleService` paths in PlanService —
                        // passing the sold-share triggers the
                        // last-row-absorption to swallow the gap and
                        // operators see two identical sessions land at
                        // wildly different prices.
                        $calculable_servcies[] = [
                            'service_price' => $bundleService->service_price,
                            'calculated_price' => $bundleService->calculated_price,
                            'service_id' => $bundleService->service_id,
                        ];
                    }
                    $calculatedServicesPrice = Bundles::calculatePrices($calculable_servcies, str_replace(',', '', $packageBundle['RegularPrice']), $packageBundle['Total']);
                    foreach ($calculatedServicesPrice as $calculatedServicePrice) {
                        $data_service['random_id'] = $request->random_id;
                        $data_service['package_bundle_id'] = $packageBundleRecord->id;
                        $data_service['service_id'] = $calculatedServicePrice['service_id'];
                        $data_service['price'] = $calculatedServicePrice['calculated_price'];
                        $data_service['orignal_price'] = $calculatedServicePrice['service_price'];
                        // Org-wide pricing convention from sys-tax-treatment.
                        // Per-row tax_treatment_type_id is no longer consulted —
                        // see project_tax_centralization plan, Stage 2.
                        $orgTaxTreatment = Settings::getOrgTaxTreatment(Auth::user()->account_id);
                        if ($orgTaxTreatment == Config::get('constants.tax_both')) {
                            if ($request->is_exclusive == '1') {
                                $data_service['tax_exclusive_price'] = $calculatedServicePrice['calculated_price'];
                                $data_service['tax_percentage'] = $location_information->tax_percentage;
                                $data_service['tax_price'] = ceil($calculatedServicePrice['calculated_price'] * ($location_information->tax_percentage / 100));
                                $data_service['tax_including_price'] = ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percentage']) / 100));

                                $data_service['is_exclusive'] = 1;
                            } else {
                                $data_service['tax_including_price'] = $calculatedServicePrice['calculated_price'];
                                $data_service['tax_percentage'] = $location_information->tax_percentage;
                                $data_service['tax_exclusive_price'] = ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percentage'] + 100));
                                $data_service['tax_price'] = ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);

                                $data_service['is_exclusive'] = 0;
                            }
                        } elseif ($orgTaxTreatment == Config::get('constants.tax_is_exclusive')) {
                            $data_service['tax_exclusive_price'] = $calculatedServicePrice['calculated_price'];
                            $data_service['tax_percentage'] = $location_information->tax_percentage;
                            $data_service['tax_price'] = ceil($calculatedServicePrice['calculated_price'] * ($location_information->tax_percentage / 100));
                            $data_service['tax_including_price'] = ceil($data_service['tax_exclusive_price'] + (($data_service['tax_exclusive_price'] * $data_service['tax_percentage']) / 100));

                            $data_service['is_exclusive'] = 1;
                        } else {
                            $data_service['tax_including_price'] = $calculatedServicePrice['calculated_price'];
                            $data_service['tax_percentage'] = $location_information->tax_percentage;
                            $data_service['tax_exclusive_price'] = ceil((100 * $data_service['tax_including_price']) / ($data_service['tax_percentage'] + 100));
                            $data_service['tax_price'] = ceil($data_service['tax_including_price'] - $data_service['tax_exclusive_price']);

                            $data_service['is_exclusive'] = 0;
                        }
                        $data_service['created_at'] = Filters::getCurrentTimeStamp();
                        $data_service['updated_at'] = Filters::getCurrentTimeStamp();
                        $data_service['sold_by'] = $packageBundle['sold_by'];
                        $packageservice = PackageService::createPackageService($data_service);
                    }
                }

                return true;
            });
        }
    }

    public function deleteplanrowtem(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.service.delete') && ! Gate::allows('plans.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $result = $this->planService->deletePlanRow($request->all());

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'],
        ]);
    }

    public function resetvoucherpacakgebundles(Request $request): JsonResponse
    {
        if (! Gate::allows('plans.edit') && ! Gate::allows('plans.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        $result = $this->planService->resetVoucherPackageBundles($request->all());

        return response()->json(['success' => $result['success']]);
    }
}
