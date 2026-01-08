<?php

namespace App\Services\Lead;

use App\Models\Leads;
use App\Models\Cities;
use App\Models\Services;
use App\Models\Locations;
use App\Models\LeadSources;
use App\Models\LeadStatuses;
use App\Models\LeadComments;
use App\Models\LeadsServices;
use App\Models\User;
use App\Models\Regions;
use App\Models\Settings;
use App\Models\Patients;
use App\Models\AuditTrails;
use App\Models\AppointmentStatuses;
use App\Models\Telecomprovidernumber;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\ActivityLogger;
use App\Helpers\GeneralFunctions;
use App\Helpers\Widgets\LocationsWidget;
use App\Exceptions\LeadException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class LeadService
{
    protected int $cacheTtl = 3600; // 1 hour cache
    
    // Cached lookup data for batch operations
    protected ?array $lookupCache = null;

    /**
     * Get paginated leads for datatable (OPTIMIZED)
     * Uses single query approach with deferred count for better performance
     */
    public function getDatatableData(array $filters, ?string $leadType = null): array
    {
        $userId = Auth::id();
        $accountId = Auth::user()->account_id;
        $filename = $leadType ? 'junk_leads' : 'leads';

        $whereConditions = $this->buildWhereConditions($filters, $filename, $userId);
        $serviceConditions = $this->buildServiceConditions($filters, $filename, $userId);
        [$orderBy, $order] = $this->getOrderParams($filters, $filename, $userId);

        $junkStatus = $this->getJunkLeadStatus($accountId);
        $junkStatusId = $junkStatus->id ?? 0;
        $userCities = ACL::getUserCities();

        // Build optimized count query (no eager loading, minimal columns)
        $countQuery = $this->buildCountQuery($whereConditions, $serviceConditions, $leadType, $junkStatusId, $userCities);
        $totalRecords = $countQuery->count();

        // Build result query with optimized eager loading
        $resultQuery = $this->buildOptimizedResultQuery($whereConditions, $serviceConditions, $leadType, $junkStatusId, $userCities);

        return [
            'total' => $totalRecords,
            'query' => $resultQuery,
            'orderBy' => $orderBy,
            'order' => $order,
        ];
    }

    /**
     * Build lightweight count query (no eager loading)
     */
    protected function buildCountQuery(array $where, array $whereService, ?string $leadType, int $junkStatusId, array $userCities): \Illuminate\Database\Eloquent\Builder
    {
        $query = Leads::where(function ($q) use ($userCities) {
            $q->whereIn('leads.city_id', $userCities)
              ->orWhereNull('leads.city_id');
        });

        if (!empty($where)) {
            $query->where($where);
        }

        // Use EXISTS subquery for service filter (faster than whereHas for counts)
        if (!empty($whereService)) {
            $query->whereExists(function ($subquery) use ($whereService) {
                $subquery->select(DB::raw(1))
                    ->from('leads_services')
                    ->whereColumn('leads_services.lead_id', 'leads.id')
                    ->where('leads_services.status', 1)
                    ->where($whereService);
            });
        }

        // Filter by junk status
        if ($leadType) {
            $query->where('leads.lead_status_id', $junkStatusId);
        } else {
            $query->where('leads.lead_status_id', '!=', $junkStatusId);
        }

        return $query;
    }

    /**
     * Build optimized result query with selective eager loading
     */
    protected function buildOptimizedResultQuery(array $where, array $whereService, ?string $leadType, int $junkStatusId, array $userCities): \Illuminate\Database\Eloquent\Builder
    {
        // Only eager load what's needed, with minimal columns
        $query = Leads::with([
            'lead_service' => function ($q) {
                $q->select('id', 'lead_id', 'service_id', 'child_service_id', 'status')
                  ->with([
                      'service:id,name',
                      'childservice:id,name',
                  ]);
            },
            'city:id,name',
            'towns:id,name',
        ])->where(function ($q) use ($userCities) {
            $q->whereIn('leads.city_id', $userCities)
              ->orWhereNull('leads.city_id');
        });

        if (!empty($where)) {
            $query->where($where);
        }

        // Use EXISTS for better performance on large datasets
        if (!empty($whereService)) {
            $query->whereExists(function ($subquery) use ($whereService) {
                $subquery->select(DB::raw(1))
                    ->from('leads_services')
                    ->whereColumn('leads_services.lead_id', 'leads.id')
                    ->where('leads_services.status', 1)
                    ->where($whereService);
            });
        }

        // Filter by junk status
        if ($leadType) {
            $query->where('leads.lead_status_id', $junkStatusId);
        } else {
            $query->where('leads.lead_status_id', '!=', $junkStatusId);
        }

        return $query;
    }

    /**
     * @deprecated Use buildCountQuery and buildOptimizedResultQuery instead
     */
    protected function buildBaseQuery(array $where, array $whereService): \Illuminate\Database\Eloquent\Builder
    {
        return $this->buildOptimizedResultQuery($where, $whereService, null, 0, ACL::getUserCities());
    }

    /**
     * @deprecated Use buildOptimizedResultQuery instead
     */
    protected function buildResultQuery(array $where, array $whereService, ?string $leadType, ?LeadStatuses $junkStatus): \Illuminate\Database\Eloquent\Builder
    {
        $junkStatusId = $junkStatus->id ?? 0;
        return $this->buildOptimizedResultQuery($where, $whereService, $leadType, $junkStatusId, ACL::getUserCities());
    }

    /**
     * Create a new lead
     */
    public function createLead(array $data): Leads
    {
        return DB::transaction(function () use ($data) {
            $accountId = Auth::user()->account_id;
            $userId = Auth::id();

            // Clean phone number
            $data['phone'] = GeneralFunctions::cleanNumber($data['phone']);

            // Check for existing lead
            $existingLead = Leads::where('phone', $data['phone'])
                ->where('account_id', $accountId)
                ->first();

            if ($data['new_lead'] ?? false) {
                if ($existingLead) {
                    throw LeadException::phoneAlreadyExists($data['phone']);
                }
                return $this->createNewLead($data, $accountId, $userId);
            }

            return $this->updateExistingLead($existingLead, $data, $accountId, $userId);
        });
    }

    /**
     * Create new lead record
     */
    protected function createNewLead(array $data, int $accountId, int $userId): Leads
    {
        // Set default lead status if not provided
        if (empty($data['lead_status_id'])) {
            $defaultStatus = $this->getDefaultLeadStatus($accountId);
            $data['lead_status_id'] = $defaultStatus?->id;
        }

        // Set region from city
        if (!empty($data['city_id'])) {
            $data['region_id'] = $this->getRegionFromCity($data['city_id']);
        }

        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        $data['converted_by'] = $userId;
        $data['account_id'] = $accountId;
        $data['created_at'] = Carbon::now();

        $lead = Leads::create($data);

        // Create lead service
        $this->createLeadService($lead->id, $data, $accountId);

        // Log activity
        $this->logLeadActivity($lead, $data);

        return $lead;
    }

    /**
     * Update existing lead
     */
    protected function updateExistingLead(?Leads $existingLead, array $data, int $accountId, int $userId): Leads
    {
        $defaultStatus = $this->getDefaultLeadStatus($accountId);

        if ($existingLead) {
            $data['lead_status_id'] = $defaultStatus?->id ?? $existingLead->lead_status_id;
            $data['updated_by'] = $userId;
            $data['updated_at'] = Carbon::now();

            $existingLead->update($data);
            $lead = $existingLead;
        } else {
            $data['lead_status_id'] = $defaultStatus?->id;
            $lead = $this->createNewLead($data, $accountId, $userId);
        }

        // Create new lead service entry
        $this->createLeadService($lead->id, $data, $accountId);

        // Log activity
        $this->logLeadActivity($lead, $data);

        return $lead;
    }

    /**
     * Update lead
     */
    public function updateLead($id, array $data): Leads
    {
        return DB::transaction(function () use ($id, $data) {
            $lead = Leads::findOrFail($id);

            // Check if status change is allowed
            if (isset($data['lead_status_id']) && $data['lead_status_id'] != $lead->lead_status_id) {
                $this->validateStatusChange($lead);
            }

            // Handle service updates
            if (!empty($data['service_id'])) {
                $this->updateLeadServices($id, $data);
            }

            $data['updated_at'] = Carbon::now();
            $data['updated_by'] = Auth::id();
            $data['account_id'] = Auth::user()->account_id;

            $lead->update($data);

            // Update patient name if phone matches
            if (!empty($data['phone']) && !empty($data['name'])) {
                GeneralFunctions::patientNameUpdate($data['phone'], $data['name']);
            }

            return $lead->fresh();
        });
    }

    /**
     * Validate status change is allowed
     */
    protected function validateStatusChange(Leads $lead): void
    {
        if (!$lead->lead_status_id) {
            return;
        }

        $currentStatus = LeadStatuses::find($lead->lead_status_id);
        if ($currentStatus && ($currentStatus->is_arrived || $currentStatus->is_converted)) {
            throw LeadException::statusChangeNotAllowed($currentStatus->name);
        }
    }

    /**
     * Update lead services
     */
    protected function updateLeadServices(int $leadId, array $data): void
    {
        // Delete old services for this service type
        if (!empty($data['old_service'])) {
            LeadsServices::where([
                'lead_id' => $leadId,
                'service_id' => $data['old_service'],
                'consultancy_id' => null,
            ])->delete();
        }

        $childServices = $data['child_service_id'] ?? [];

        if (!empty($childServices)) {
            foreach ($childServices as $childServiceId) {
                $leadService = LeadsServices::updateOrCreate([
                    'lead_id' => $leadId,
                    'service_id' => $data['service_id'],
                    'child_service_id' => $childServiceId,
                    'consultancy_id' => null,
                ], [
                    'status' => 1,
                ]);

                // Deactivate other services
                LeadsServices::where('lead_id', $leadId)
                    ->where('id', '!=', $leadService->id)
                    ->update(['status' => 0]);
            }
        } else {
            $leadService = LeadsServices::updateOrCreate([
                'lead_id' => $leadId,
                'service_id' => $data['service_id'],
                'consultancy_id' => null,
            ], [
                'status' => 1,
            ]);

            LeadsServices::where('lead_id', $leadId)
                ->where('id', '!=', $leadService->id)
                ->update(['status' => 0]);
        }
    }

    /**
     * Delete lead
     */
    public function deleteLead($id): bool
    {
        $lead = Leads::findOrFail($id);
        return $lead->delete();
    }

    /**
     * Bulk delete leads
     */
    public function bulkDelete(array $ids): int
    {
        return Leads::whereIn('id', $ids)->delete();
    }

    /**
     * Update lead status
     */
    public function updateLeadStatus($leadId, array $data): Leads
    {
        return DB::transaction(function () use ($leadId, $data) {
            $lead = Leads::findOrFail($leadId);

            // Validate status change
            $this->validateStatusChange($lead);

            $statusId = $data['lead_status_chalid_id'] ?? $data['lead_status_parent_id'];

            $lead->update([
                'lead_status_id' => $statusId,
                'converted_by' => Auth::id(),
            ]);

            // Add comment if provided
            $comment = $data['comment1'] ?? $data['comment2'] ?? null;
            if ($comment) {
                LeadComments::create([
                    'lead_id' => $leadId,
                    'comment' => $comment,
                    'created_by' => Auth::id(),
                ]);
            }

            return $lead->fresh();
        });
    }

    /**
     * Toggle lead active status
     */
    public function toggleStatus($id, $status): Leads
    {
        $lead = Leads::findOrFail($id);
        $lead->update(['active' => $status]);
        return $lead;
    }

    /**
     * Get lead detail with all relations
     */
    public function getLeadDetail($id): ?Leads
    {
        return Leads::with([
            'lead_comments.user:id,name',
            'towns:id,name',
            'city:id,name',
            'lead_source:id,name',
            'lead_status:id,name,parent_id',
            'lead_service.service:id,name',
            'lead_service.childservice:id,name',
        ])->find($id);
    }

    /**
     * Get lead for editing
     */
    public function getLeadForEdit($id): ?Leads
    {
        return Leads::with('lead_service')->where([
            'id' => $id,
            'account_id' => Auth::user()->account_id,
        ])->first();
    }

    /**
     * Create lead service entry
     */
    public function createLeadService($leadId, array $data, $accountId): LeadsServices
    {
        $openStatus = $this->getDefaultLeadStatus($accountId);
        $metaLeadId = !empty($data['meta_lead_id']) ? trim($data['meta_lead_id']) : null;

        $leadService = LeadsServices::create([
            'lead_id' => $leadId,
            'service_id' => $data['service_id'] ?? null,
            'child_service_id' => $data['child_service_id'] ?? null,
            'meta_lead_id' => $metaLeadId,
            'status' => 1,
            'lead_status_id' => $openStatus?->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // Deactivate previous services
        LeadsServices::where('lead_id', $leadId)
            ->where('id', '!=', $leadService->id)
            ->update(['status' => 0]);

        return $leadService;
    }

    /**
     * Import leads from file
     */
    public function importLeads(array $rows, array $options): array
    {
        $accountId = Auth::user()->account_id;
        $userId = Auth::id();

        // Pre-load all lookup data for performance
        $lookupData = $this->loadImportLookupData($accountId);

        $stats = [
            'created' => 0,
            'updated' => 0,
            'invalid_phones' => [],
            'invalid_services' => [],
        ];

        // Get existing phones
        $phones = $this->extractValidPhones($rows);
        $existingPhones = $this->getExistingLeadPhones($phones, $accountId);
        $newPhones = array_diff($phones, $existingPhones);

        foreach ($rows as $row) {
            $result = $this->processImportRow($row, $lookupData, $options, $existingPhones, $newPhones, $userId, $accountId);
            
            if ($result['status'] === 'created') {
                $stats['created']++;
            } elseif ($result['status'] === 'updated') {
                $stats['updated']++;
            } elseif ($result['status'] === 'invalid_phone') {
                $stats['invalid_phones'][] = $result['phone'];
            } elseif ($result['status'] === 'invalid_service') {
                if (!in_array($result['service'], $stats['invalid_services'])) {
                    $stats['invalid_services'][] = $result['service'];
                }
            }
        }

        return $stats;
    }

    /**
     * Load all lookup data for import
     */
    protected function loadImportLookupData(int $accountId): array
    {
        return Cache::remember("lead_import_lookup_{$accountId}", 300, function () use ($accountId) {
            $cities = Cities::where('account_id', $accountId)->get();
            $citiesCache = [];
            $regionsCache = [];
            foreach ($cities as $city) {
                $key = strtolower(trim($city->name));
                $citiesCache[$key] = $city->id;
                $regionsCache[$key] = $city->region_id;
            }

            $leadSources = LeadSources::where('account_id', $accountId)
                ->pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id])
                ->toArray();

            $leadStatuses = LeadStatuses::where('account_id', $accountId)
                ->pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id])
                ->toArray();

            $services = Services::where('account_id', $accountId)->get();
            $servicesCache = [];
            $childServicesCache = [];
            foreach ($services as $service) {
                $key = strtolower(trim($service->name));
                $servicesCache[$key] = [
                    'id' => $service->id,
                    'parent_id' => $service->parent_id,
                ];
                if ($service->parent_id) {
                    $childServicesCache[$service->parent_id][$key] = $service->id;
                }
            }

            $locations = Locations::where('account_id', $accountId)
                ->pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower(trim($name)) => $id])
                ->toArray();

            return [
                'cities' => $citiesCache,
                'regions' => $regionsCache,
                'lead_sources' => $leadSources,
                'lead_statuses' => $leadStatuses,
                'services' => $servicesCache,
                'child_services' => $childServicesCache,
                'locations' => $locations,
            ];
        });
    }

    /**
     * Process single import row
     */
    protected function processImportRow(array $row, array $lookup, array $options, array $existingPhones, array $newPhones, int $userId, int $accountId): array
    {
        $phone = GeneralFunctions::cleanNumber($row['phone'] ?? '');

        // Validate phone
        if (strlen($phone) < 10 || strlen($phone) > 12) {
            return ['status' => 'invalid_phone', 'phone' => $row['phone'] ?? ''];
        }

        // Lookup service
        $serviceKey = strtolower(trim($row['service'] ?? ''));
        $serviceData = $lookup['services'][$serviceKey] ?? null;

        if (!$serviceData && !empty($serviceKey)) {
            return ['status' => 'invalid_service', 'service' => $row['service']];
        }

        $serviceId = $serviceData['id'] ?? null;
        $childServiceId = null;

        if ($serviceId && !empty($row['treatment'])) {
            $treatmentKey = strtolower(trim($row['treatment']));
            $childServiceId = $lookup['child_services'][$serviceId][$treatmentKey] ?? null;
        }

        // Build lead data
        $cityKey = strtolower(trim($row['city'] ?? ''));
        $leadData = [
            'name' => $row['full_name'] ?? '',
            'email' => $row['email'] ?? null,
            'phone' => $phone,
            'gender' => $this->parseGender($row['gender'] ?? ''),
            'city_id' => $lookup['cities'][$cityKey] ?? null,
            'region_id' => $lookup['regions'][$cityKey] ?? null,
            'lead_source_id' => $lookup['lead_sources'][strtolower(trim($row['lead_source'] ?? ''))] ?? Config::get('constants.lead_source_social_media'),
            'location_id' => $lookup['locations'][strtolower(trim($row['centre'] ?? ''))] ?? null,
            'meta_lead_id' => !empty($row['meta_lead_id']) ? trim($row['meta_lead_id']) : null,
            'created_by' => $userId,
            'updated_by' => $userId,
            'converted_by' => $userId,
            'account_id' => $accountId,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        $isNew = in_array($phone, $newPhones);
        $isExisting = in_array($phone, $existingPhones);

        if (!$serviceId) {
            return ['status' => 'skipped'];
        }

        // Determine lead status
        $leadStatusKey = strtolower(trim($row['lead_status'] ?? ''));
        $leadStatusId = $lookup['lead_statuses'][$leadStatusKey] ?? Config::get('constants.lead_status_open');

        if ($options['update_records'] && $isExisting) {
            if (!$options['skip_lead_statuses']) {
                $leadData['lead_status_id'] = $leadStatusId;
            }
            $lead = Leads::updateOrCreate(['phone' => $phone], $leadData);
            $this->createLeadServiceForImport($lead->id, $serviceId, $childServiceId, $leadData['meta_lead_id'], $accountId);
            $this->logLeadActivity($lead, $leadData);
            return ['status' => 'updated'];
        }

        if ($isNew) {
            $leadData['lead_status_id'] = $leadStatusId;
            $lead = Leads::updateOrCreate(['phone' => $phone], $leadData);
            $this->createLeadServiceForImport($lead->id, $serviceId, $childServiceId, $leadData['meta_lead_id'], $accountId);
            $this->logLeadActivity($lead, $leadData);
            return ['status' => 'created'];
        }

        if (!$options['update_records'] && $isExisting) {
            $updateData = [
                'created_by' => $userId,
                'updated_by' => $userId,
                'converted_by' => $userId,
                'updated_at' => Carbon::now(),
                'location_id' => $leadData['location_id'],
                'meta_lead_id' => $leadData['meta_lead_id'],
            ];
            if (!$options['skip_lead_statuses']) {
                $updateData['lead_status_id'] = $leadStatusId;
            }
            $lead = Leads::updateOrCreate(['phone' => $phone], $updateData);
            $this->createLeadServiceForImport($lead->id, $serviceId, $childServiceId, $leadData['meta_lead_id'], $accountId);
            $this->logLeadActivity($lead, $leadData);
            return ['status' => 'updated'];
        }

        return ['status' => 'skipped'];
    }

    /**
     * Create lead service for import
     */
    protected function createLeadServiceForImport(int $leadId, ?int $serviceId, ?int $childServiceId, ?string $metaLeadId, int $accountId): void
    {
        $openStatus = $this->getDefaultLeadStatus($accountId);

        $leadService = LeadsServices::create([
            'lead_id' => $leadId,
            'service_id' => $serviceId,
            'child_service_id' => $childServiceId,
            'meta_lead_id' => $metaLeadId,
            'status' => 1,
            'lead_status_id' => $openStatus?->id,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        LeadsServices::where('lead_id', $leadId)
            ->where('id', '!=', $leadService->id)
            ->update(['status' => 0]);
    }

    /**
     * Parse gender from string
     */
    protected function parseGender(string $gender): int
    {
        $gender = strtolower(trim($gender));
        return $gender === 'female' ? 2 : 1;
    }

    /**
     * Extract valid phones from rows
     */
    protected function extractValidPhones(array $rows): array
    {
        $phones = [];
        foreach ($rows as $row) {
            $phone = $row['phone'] ?? '';
            if (strlen($phone) >= 10 && strlen($phone) <= 13) {
                $phones[] = GeneralFunctions::cleanNumber($phone);
            }
        }
        return $phones;
    }

    /**
     * Get existing lead phones
     */
    protected function getExistingLeadPhones(array $phones, int $accountId): array
    {
        if (empty($phones)) {
            return [];
        }

        return Leads::whereIn('phone', $phones)
            ->where('account_id', $accountId)
            ->orderBy('id', 'desc')
            ->pluck('phone')
            ->unique()
            ->toArray();
    }

    /**
     * Log lead activity
     */
    protected function logLeadActivity(Leads $lead, array $data): void
    {
        $location = !empty($data['location_id']) 
            ? Locations::with('city')->find($data['location_id']) 
            : null;
        $service = !empty($data['service_id']) 
            ? Services::find($data['service_id']) 
            : null;

        ActivityLogger::logLeadCreated($lead, $location, $service);
    }

    /**
     * Get cached lookup data for forms
     */
    public function getFormLookupData(): array
    {
        $accountId = Auth::user()->account_id;

        return Cache::remember("lead_form_lookup_{$accountId}", $this->cacheTtl, function () use ($accountId) {
            return [
                'cities' => Cities::getActiveSortedFeatured(ACL::getUserCities()),
                'lead_sources' => LeadSources::getActiveSorted(),
                'lead_statuses' => LeadStatuses::getLeadStatuses(),
                'services' => Services::where([
                    'slug' => 'custom',
                    'parent_id' => 0,
                    'active' => 1,
                ])->pluck('name', 'id'),
                'gender' => Config::get('constants.gender_array'),
            ];
        });
    }

    /**
     * Get filter data for datatable
     */
    public function getFilterData(string $filename): array
    {
        $accountId = Auth::user()->account_id;
        $userId = Auth::id();

        $cacheKey = "lead_filters_{$accountId}";

        $filterValues = Cache::remember($cacheKey, $this->cacheTtl, function () use ($accountId) {
            $junkStatus = $this->getJunkLeadStatus($accountId);

            return [
                'cities' => Cities::getActiveSortedFeatured(ACL::getUserCities()),
                'locations' => Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId)->pluck('name', 'id'),
                'regions' => \App\Models\Regions::getActiveSorted(ACL::getUserRegions()),
                'users' => \App\Models\User::getAllActiveRecords($accountId)->pluck('name', 'id'),
                'lead_statuses' => $junkStatus 
                    ? LeadStatuses::getLeadStatuses($junkStatus->id)
                    : LeadStatuses::getLeadStatuses(),
                'Services' => Services::where([
                    'slug' => 'custom',
                    'parent_id' => 0,
                    'active' => 1,
                ])->pluck('name', 'id'),
            ];
        });

        $activeFilters = Filters::all($userId, $filename);
        $filterValues['leadServices'] = Filters::get($userId, 'leads', 'service_id');

        return [
            'filter_values' => $filterValues,
            'active_filters' => $activeFilters,
        ];
    }

    /**
     * Get default lead status
     */
    public function getDefaultLeadStatus(int $accountId): ?LeadStatuses
    {
        return Cache::remember("default_lead_status_{$accountId}", $this->cacheTtl, function () use ($accountId) {
            return LeadStatuses::where([
                'account_id' => $accountId,
                'is_default' => 1,
            ])->first();
        });
    }

    /**
     * Get junk lead status
     */
    public function getJunkLeadStatus(int $accountId): ?LeadStatuses
    {
        return Cache::remember("junk_lead_status_{$accountId}", $this->cacheTtl, function () use ($accountId) {
            return LeadStatuses::where([
                'account_id' => $accountId,
                'is_junk' => 1,
            ])->first();
        });
    }

    /**
     * Get converted lead status
     */
    public function getConvertedLeadStatus(int $accountId): ?LeadStatuses
    {
        return Cache::remember("converted_lead_status_{$accountId}", $this->cacheTtl, function () use ($accountId) {
            return LeadStatuses::where([
                'account_id' => $accountId,
                'is_converted' => 1,
            ])->first();
        });
    }

    /**
     * Get region from city
     */
    protected function getRegionFromCity(int $cityId): ?int
    {
        $city = Cities::find($cityId);
        return $city?->region_id;
    }

    /**
     * Build where conditions from filters
     */
    protected function buildWhereConditions(array $filters, string $filename, int $userId): array
    {
        $where = [];
        $applyFilter = checkFilters($filters, $filename);

        $filterMappings = [
            'lead_id' => ['id', '='],
            'name' => ['name', 'like', '%', '%'],
            'phone' => ['phone', 'like', '%', '%', true],
            'city_id' => ['city_id', '='],
            'location_id' => ['leads.location_id', '='],
            'gender_id' => ['gender', '='],
            'region_id' => ['region_id', '='],
            'lead_status_id' => ['lead_status_id', '='],
            'created_by' => ['leads.created_by', '='],
        ];

        foreach ($filterMappings as $filterKey => $mapping) {
            $where = $this->applyFilter($where, $filters, $filterKey, $mapping, $filename, $userId, $applyFilter);
        }

        // Handle date range
        if (hasFilter($filters, 'created_at')) {
            $dateRange = explode(' - ', $filters['created_at']);
            $startDate = date('Y-m-d H:i:s', strtotime($dateRange[0]));
            $endDate = (new \DateTime($dateRange[1]))->setTime(23, 59, 0)->format('Y-m-d H:i:s');
            $where[] = ['leads.created_at', '>=', $startDate];
            $where[] = ['leads.created_at', '<=', $endDate];
            Filters::put($userId, $filename, 'created_at', $filters['created_at']);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'created_at');
        }

        return $where;
    }

    /**
     * Apply single filter
     */
    protected function applyFilter(array $where, array $filters, string $key, array $mapping, string $filename, int $userId, bool $applyFilter): array
    {
        $column = $mapping[0];
        $operator = $mapping[1];
        $prefix = $mapping[2] ?? '';
        $suffix = $mapping[3] ?? '';
        $cleanNumber = $mapping[4] ?? false;

        if (hasFilter($filters, $key)) {
            $value = $cleanNumber ? GeneralFunctions::cleanNumber($filters[$key]) : $filters[$key];
            $where[] = [$column, $operator, $prefix . $value . $suffix];
            Filters::put($userId, $filename, $key, $value);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, $key);
        } elseif (Filters::get($userId, $filename, $key)) {
            $value = Filters::get($userId, $filename, $key);
            if ($cleanNumber) {
                $value = GeneralFunctions::cleanNumber($value);
            }
            $where[] = [$column, $operator, $prefix . $value . $suffix];
        }

        return $where;
    }

    /**
     * Build service conditions from filters
     */
    protected function buildServiceConditions(array $filters, string $filename, int $userId): array
    {
        $where = [];
        $applyFilter = checkFilters($filters, $filename);

        if (hasFilter($filters, 'service_id')) {
            $where[] = ['service_id', '=', $filters['service_id']];
            Filters::put($userId, $filename, 'service_id', $filters['service_id']);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'service_id');
        } elseif (Filters::get($userId, $filename, 'service_id')) {
            $where[] = ['service_id', '=', Filters::get($userId, $filename, 'service_id')];
        }

        return $where;
    }

    /**
     * Get order parameters
     */
    protected function getOrderParams(array $filters, string $filename, int $userId): array
    {
        if (isset($filters['sort'])) {
            [$orderBy, $order] = getSortBy(['sort' => $filters['sort']], 'leads.created_at', 'DESC');
            Filters::put($userId, $filename, 'order_by', $orderBy);
            Filters::put($userId, $filename, 'order', $order);
        } else {
            $orderBy = Filters::get($userId, $filename, 'order_by') ?: 'leads.created_at';
            $order = Filters::get($userId, $filename, 'order') ?: 'desc';

            if ($orderBy === 'created_at') {
                $orderBy = 'leads.created_at';
            }

            Filters::put($userId, $filename, 'order_by', $orderBy);
            Filters::put($userId, $filename, 'order', $order);
        }

        return [$orderBy, $order];
    }

    /**
     * Clear lead-related caches
     */
    public function clearCache(): void
    {
        $accountId = Auth::user()->account_id;
        Cache::forget("lead_form_lookup_{$accountId}");
        Cache::forget("lead_filters_{$accountId}");
        Cache::forget("default_lead_status_{$accountId}");
        Cache::forget("junk_lead_status_{$accountId}");
        Cache::forget("converted_lead_status_{$accountId}");
        Cache::forget("lead_import_lookup_{$accountId}");
    }

    /**
     * Search leads by ID or name
     */
    public function searchLeads(string $search, int $accountId): \Illuminate\Support\Collection
    {
        return $this->searchLeadsById($search, $accountId);
    }

    /**
     * Search leads by phone
     */
    public function searchByPhone(string $phone, int $accountId): \Illuminate\Support\Collection
    {
        return $this->searchLeadsByPhone($phone, $accountId);
    }

    /**
     * Get child services for a parent service
     */
    public function getChildServices($serviceId): \Illuminate\Support\Collection
    {
        return Services::where([
            'parent_id' => $serviceId,
            'active' => 1,
        ])->pluck('name', 'id');
    }

    /**
     * Add comment to lead
     */
    public function addComment($leadId, string $comment): LeadComments
    {
        return LeadComments::create([
            'lead_id' => $leadId,
            'comment' => $comment,
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * Get lead statuses with children
     */
    public function getLeadStatusesWithChildren($leadId): array
    {
        $lead = Leads::find($leadId);
        if (!$lead) {
            throw LeadException::notFound($leadId);
        }

        $leadStatus = LeadStatuses::find($lead->lead_status_id);
        $parentStatuses = LeadStatuses::getLeadStatuses();
        $comments = LeadComments::where('lead_id', $leadId)->get();

        if ($leadStatus->parent_id == 0) {
            $parentStatus = $leadStatus;
            $childStatus = null;
        } else {
            $childStatus = $leadStatus;
            $parentStatus = LeadStatuses::find($leadStatus->parent_id);
        }

        $childStatuses = LeadStatuses::where('parent_id', $parentStatus->id)->get();

        return [
            'lead' => $lead,
            'lead_statuses_Pdata' => $parentStatuses,
            'lead_statuses_Cdata' => $childStatuses->isEmpty() ? 'nothing' : $childStatuses,
            'lead_status_parent' => $parentStatus,
            'lead_status_chalid' => $childStatus ?? 'null',
            'lead_status_comment' => $comments,
        ];
    }

    // =========================================================================
    // BUSINESS LOGIC MOVED FROM LEADS MODEL
    // =========================================================================

    /**
     * Search leads by phone (optimized)
     * Moved from Leads::getLeadPhoneAjax
     */
    public function searchLeadsByPhone(string $phone, int $accountId): Collection
    {
        return Leads::where([
            ['active', '=', 1],
            ['account_id', '=', $accountId],
            ['phone', 'LIKE', "%{$phone}%"],
        ])
        ->select('name', 'id', 'phone')
        ->limit(50)
        ->get();
    }

    /**
     * Search leads by ID or name (optimized)
     * Moved from Leads::getLeadidAjax
     */
    public function searchLeadsById(string $search, int $accountId): Collection
    {
        // First try exact ID match
        if (is_numeric($search)) {
            $leads = Leads::where([
                'active' => 1,
                'account_id' => $accountId,
                'id' => $search,
            ])->select('name', 'id', 'phone')->get();

            if ($leads->isNotEmpty()) {
                return $leads;
            }
        }

        // Search by name or phone
        $searchTerm = GeneralFunctions::patientSearch($search);
        $phoneNumeric = GeneralFunctions::clearnString($search);

        $query = Leads::where(['active' => 1, 'account_id' => $accountId]);

        if (is_numeric($phoneNumeric)) {
            $phone = GeneralFunctions::cleanNumber($search);
            $query->where('phone', 'LIKE', "%{$phone}%");
        } else {
            $query->where('name', 'LIKE', "%{$searchTerm}%");
        }

        return $query->select('name', 'id', 'phone')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get()
            ->unique('phone');
    }

    /**
     * Prepare SMS content for delivery
     * Moved from Leads::prepareSMSContent
     */
    public function prepareSMSContent($leadId, string $smsContent): string
    {
        if (!$leadId) {
            return $smsContent;
        }

        // Load global setting for head office
        $setting = Settings::find(5);
        if ($setting) {
            $smsContent = str_replace('##head_office_phone##', $setting->data, $smsContent);
        }

        $lead = Leads::with(['city', 'lead_source', 'lead_status'])->find($leadId);
        if (!$lead) {
            return $smsContent;
        }

        $patient = Patients::find($lead->patient_id);
        if ($patient) {
            $smsContent = str_replace('##full_name##', $patient->full_name ?? '', $smsContent);
            $smsContent = str_replace('##email##', $patient->email ?? '', $smsContent);
            $smsContent = str_replace('##phone##', $patient->phone ?? '', $smsContent);
            $smsContent = str_replace('##gender##', Config::get('constants.gender_array')[$patient->gender] ?? '', $smsContent);
        }

        if ($lead->city) {
            $smsContent = str_replace('##city_name##', $lead->city->name, $smsContent);
        }

        if ($lead->lead_source) {
            $smsContent = str_replace('##lead_source_name##', $lead->lead_source->name, $smsContent);
        }

        if ($lead->lead_status) {
            $smsContent = str_replace('##lead_status_name##', $lead->lead_status->name, $smsContent);
        }

        return $smsContent;
    }

    /**
     * Create lead record with audit trail
     * Moved from Leads::createRecord
     */
    public function createLeadRecord(array $data, ?string $status = null): Leads
    {
        return DB::transaction(function () use ($data, $status) {
            $accountId = Auth::user()->account_id;

            if ($status === 'Appointment') {
                $data['service_id'] = $data['base_service_id'] ?? null;
                $record = Leads::updateOrCreate([
                    'phone' => $data['phone'],
                    'account_id' => $accountId,
                ], $data);

                $data['lead_id'] = $record->id;
                LeadsServices::create($data);

                AuditTrails::addEventLogger('leads', 'create', $data, Leads::getFillableFields(), $record);
                return $record;
            }

            // Set region from city
            if (!empty($data['city_id'])) {
                $city = Cities::find($data['city_id']);
                $data['region_id'] = $city?->region_id;
            }

            $existingLead = Leads::where([
                'phone' => $data['phone'],
                'account_id' => $accountId,
            ])->first();

            if (!$existingLead) {
                $record = Leads::create($data);
            } else {
                $openStatus = $this->getDefaultLeadStatus($accountId);
                if ($openStatus) {
                    $existingLead->lead_status_id = $openStatus->id;
                }
                $existingLead->created_at = Carbon::now();
                $existingLead->save();
                $record = $existingLead;
                $data['lead_id'] = $record->id;
            }

            AuditTrails::addEventLogger('leads', 'create', $data, Leads::getFillableFields(), $record);
            return $record;
        });
    }

    /**
     * Update lead record with audit trail
     * Moved from Leads::updateRecord
     */
    public function updateLeadRecord($id, array $data, bool $isAppointment = false): ?Leads
    {
        return DB::transaction(function () use ($id, $data, $isAppointment) {
            $record = Leads::find($id);
            if (!$record) {
                return null;
            }

            $oldData = $isAppointment ? $record->toArray() : [];

            // Set region from city
            if (!empty($data['city_id'])) {
                $city = Cities::find($data['city_id']);
                $data['region_id'] = $city?->region_id;
            }

            $data['updated_at'] = Carbon::now();
            $record->update($data);

            AuditTrails::editEventLogger('leads', 'Edit', $data, Leads::getFillableFields(), $oldData, $record);
            return $record;
        });
    }

    /**
     * Get lead report data (optimized)
     * Moved from Leads::getLeadReport
     */
    public function getLeadReport(array $filters): Collection
    {
        $query = $this->buildReportBaseQuery($filters);
        
        // Apply additional filters
        $this->applyReportFilters($query, $filters);

        // Age group filter
        if (!empty($filters['age_group_range'])) {
            $ageRange = explode(':', $filters['age_group_range']);
            $from = Carbon::now()->subYears((int) $ageRange[1])->toDateString();
            $to = Carbon::now()->subYears((int) $ageRange[0])->toDateString();
            $query->whereBetween('users.dob', [$from, $to]);
        }

        // Telecom provider filter
        if (!empty($filters['telecomprovider_id'])) {
            $providers = Telecomprovidernumber::whereIn('id', $filters['telecomprovider_id'])->get();
            $prefixes = $providers->pluck('pre_fix')->map(fn($p) => ltrim($p, '0'))->toArray();
            
            if (!empty($prefixes)) {
                $query->where(function ($q) use ($prefixes) {
                    foreach ($prefixes as $i => $prefix) {
                        if ($i === 0) {
                            $q->where('users.phone', 'like', $prefix . '%');
                        } else {
                            $q->orWhere('users.phone', 'like', $prefix . '%');
                        }
                    }
                });
            }
        }

        return $query->select([
            '*',
            'leads.created_by as lead_created_by',
            'leads.id as lead_id',
            'leads.created_at as lead_created_at',
            'users.id as PatientId',
        ])->get();
    }

    /**
     * Get marketing report data (optimized)
     * Moved from Leads::getMarketingReport
     */
    public function getMarketingReport(array $filters): Collection
    {
        $accountId = Auth::user()->account_id;
        $junkStatus = $this->getJunkLeadStatus($accountId);
        $junkStatusId = $junkStatus?->id ?? Config::get('constants.lead_status_junk');

        $query = $this->buildReportBaseQuery($filters, 'users.created_at');
        $query->whereNotIn('leads.lead_status_id', [$junkStatusId]);

        $this->applyReportFilters($query, $filters);

        return $query->select([
            '*',
            'leads.created_by as lead_created_by',
            'leads.id as lead_id',
            'leads.created_at as lead_created_at',
            'users.id as PatientId',
        ])->get();
    }

    /**
     * Get lead summary report (optimized)
     * Moved from Leads::getLeadSummaryReport
     */
    public function getLeadSummaryReport(array $filters): Collection
    {
        $query = $this->buildReportBaseQuery($filters);

        if (!empty($filters['region_id'])) {
            $query->where('leads.region_id', $filters['region_id']);
        }
        if (!empty($filters['city_id'])) {
            $query->where('leads.city_id', $filters['city_id']);
        }

        return $query->select([
            '*',
            'leads.created_by as lead_created_by',
            'leads.id as lead_id',
            'leads.created_at as lead_created_at',
            'users.id as PatientId',
        ])->get();
    }

    /**
     * Get NOW report (optimized)
     * Moved from Leads::getNowReport
     */
    public function getNowReport(array $filters, int $accountId): Collection
    {
        [$startDate, $endDate] = $this->parseDateRange($filters['date_range'] ?? null);

        $junkStatus = LeadStatuses::where('is_junk', 1)->first();
        $arrived = AppointmentStatuses::where('is_arrived', 1)->first();
        $pending = AppointmentStatuses::where('is_default', 1)->first();

        $appointments = DB::table('leads')
            ->join('appointments', 'leads.id', '=', 'appointments.lead_id')
            ->where('leads.lead_status_id', '!=', $junkStatus?->id ?? 0)
            ->where('appointments.base_appointment_status_id', Config::get('constants.appointment_status_not_show'))
            ->whereDate('appointments.created_at', '>=', $startDate)
            ->whereDate('appointments.created_at', '<=', $endDate)
            ->select('appointments.*', DB::raw('MAX(appointments.created_at) as max_created_at'))
            ->groupBy('appointments.patient_id', 'appointments.service_id')
            ->orderBy('appointments.created_at', 'DESC')
            ->get();

        // Pre-load services for performance
        $services = Services::where('account_id', $accountId)
            ->select('id', 'parent_id', 'slug', 'end_node')
            ->get()
            ->keyBy('id');

        // Filter out appointments with follow-ups
        return $appointments->filter(function ($appointment) use ($junkStatus, $arrived, $pending, $endDate, $services) {
            $rootService = LocationsWidget::findRoot($appointment->service_id, $services);

            $hasFollowUp = DB::table('leads')
                ->join('appointments', 'leads.id', '=', 'appointments.lead_id')
                ->where('leads.lead_status_id', '!=', $junkStatus?->id ?? 0)
                ->where('appointments.patient_id', $appointment->patient_id)
                ->whereIn('appointments.base_appointment_status_id', [$arrived?->id, $pending?->id])
                ->whereDate('appointments.created_at', '>', $endDate)
                ->exists();

            if (!$hasFollowUp) {
                return true;
            }

            // Check if follow-up is for same service
            $followUps = DB::table('appointments')
                ->where('patient_id', $appointment->patient_id)
                ->whereDate('created_at', '>', $endDate)
                ->pluck('service_id');

            foreach ($followUps as $serviceId) {
                if (LocationsWidget::findRoot($serviceId, $services) === $rootService) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Build base query for reports
     */
    protected function buildReportBaseQuery(array $filters, string $dateColumn = 'leads.created_at'): \Illuminate\Database\Eloquent\Builder
    {
        [$startDate, $endDate] = $this->parseDateRange($filters['date_range'] ?? null);

        return Leads::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', Config::get('constants.patient_id'))
            ->where(function ($query) {
                $query->whereIn('leads.city_id', ACL::getUserCities())
                    ->orWhereNull('leads.city_id');
            })
            ->when($startDate, fn($q) => $q->whereDate($dateColumn, '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate($dateColumn, '<=', $endDate));
    }

    /**
     * Apply common report filters
     */
    protected function applyReportFilters($query, array $filters): void
    {
        $filterMap = [
            'cnic' => 'users.cnic',
            'dob' => 'users.dob',
            'patient_id' => 'users.id',
            'gender_id' => 'users.gender',
            'region_id' => 'leads.region_id',
            'city_id' => 'leads.city_id',
            'lead_status_id' => 'leads.lead_status_id',
            'service_id' => 'leads.service_id',
            'user_id' => 'leads.created_by',
            'town_id' => 'leads.town_id',
            'referred_id' => 'users.referred_by',
        ];

        foreach ($filterMap as $key => $column) {
            if (!empty($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }

        // Like filters
        if (!empty($filters['email'])) {
            $query->where('users.email', 'like', '%' . $filters['email'] . '%');
        }
        if (!empty($filters['phone'])) {
            $query->where('users.phone', 'like', '%' . GeneralFunctions::cleanNumber($filters['phone']) . '%');
        }
    }

    /**
     * Parse date range string
     */
    protected function parseDateRange(?string $dateRange): array
    {
        if (!$dateRange) {
            return [null, null];
        }

        $parts = explode(' - ', $dateRange);
        return [
            date('Y-m-d', strtotime($parts[0])),
            date('Y-m-d', strtotime($parts[1] ?? $parts[0])),
        ];
    }

    // =========================================================================
    // OPTIMIZED DATATABLE METHODS
    // =========================================================================

    /**
     * Get datatable data with optimized queries (single query approach)
     */
    public function getOptimizedDatatableData(array $filters, ?string $leadType, int $limit, int $offset): array
    {
        $userId = Auth::id();
        $accountId = Auth::user()->account_id;
        $filename = $leadType ? 'junk_leads' : 'leads';

        // Build conditions
        $whereConditions = $this->buildWhereConditions($filters, $filename, $userId);
        $serviceConditions = $this->buildServiceConditions($filters, $filename, $userId);
        [$orderBy, $order] = $this->getOrderParams($filters, $filename, $userId);

        $junkStatus = $this->getJunkLeadStatus($accountId);
        $junkStatusId = $junkStatus?->id ?? 0;

        // Single optimized query with all eager loading
        $query = Leads::with([
            'lead_service' => fn($q) => $q->with([
                'service:id,name,parent_id',
                'childservice:id,name,parent_id',
                'leadStatus:id,name',
            ]),
            'city:id,name',
            'towns:id,name',
            'region:id,name',
        ])
        ->where(function ($q) {
            $q->whereIn('leads.city_id', ACL::getUserCities())
              ->orWhereNull('leads.city_id');
        });

        // Apply where conditions
        if (!empty($whereConditions)) {
            $query->where($whereConditions);
        }

        // Apply service conditions
        if (!empty($serviceConditions)) {
            $query->whereHas('lead_service', fn($q) => $q->where($serviceConditions)->where('status', 1));
        }

        // Filter by junk status
        if ($leadType) {
            $query->where('leads.lead_status_id', $junkStatusId);
        } else {
            $query->where('leads.lead_status_id', '!=', $junkStatusId);
        }

        // Get total count (use clone to avoid modifying main query)
        $total = (clone $query)->count();

        // Get paginated results
        $leads = $query->select([
            'leads.*',
            'leads.created_by as lead_created_by',
            'leads.id as lead_id',
            'leads.created_at as lead_created_at',
        ])
        ->orderBy($orderBy, $order)
        ->limit($limit)
        ->offset($offset)
        ->get();

        return [
            'leads' => $leads,
            'total' => $total,
            'orderBy' => $orderBy,
            'order' => $order,
        ];
    }

    /**
     * Transform leads collection to datatable format
     */
    public function transformLeadsForDatatable(Collection $leads, array $users, array $regions, array $leadStatuses, bool $canViewContact): array
    {
        return $leads->map(function ($lead) use ($users, $regions, $leadStatuses, $canViewContact) {
            $services = [];
            $childServices = [];
            $activeServices = [];

            foreach ($lead->lead_service as $ls) {
                if ($ls->service && !in_array($ls->service->name, $services)) {
                    $services[] = $ls->service->name;
                }
                if ($ls->status == 1) {
                    $childServices[] = $ls->childservice->name ?? '';
                    $activeServices[] = $ls->service->name ?? '';
                }
            }

            // Get lead status data with parent resolution
            $leadStatusData = null;
            if (isset($leadStatuses[$lead->lead_status_id])) {
                $status = $leadStatuses[$lead->lead_status_id];
                $leadStatusData = $status->parent_id == 0
                    ? $status
                    : ($leadStatuses[$status->parent_id] ?? $status);
            }

            return [
                'id' => $lead->id,
                'lead_id' => $lead->lead_id,
                'name' => $lead->name,
                'gender' => $lead->gender == 1 ? 'Male' : 'Female',
                'active' => $lead->active,
                'cityId' => $lead->city?->id ?? 0,
                'phone' => $canViewContact ? GeneralFunctions::prepareNumber4Call($lead->phone) : '***********',
                'city_id' => $lead->city->name ?? '',
                'region_id' => $regions[$lead->region_id]->name ?? 'N/A',
                'lead_status_id' => $leadStatusData->name ?? '',
                'service_id' => implode(',', $services),
                'service_active' => implode(',', array_filter($activeServices)),
                'created_at' => Carbon::parse($lead->lead_created_at)->format('F j,Y h:i A'),
                'created_by' => $users[$lead->lead_created_by]->name ?? 'N/A',
                'location' => $lead->towns->name ?? '',
                'child_service' => implode(',', array_filter($childServices)),
            ];
        })->toArray();
    }

    // =========================================================================
    // OPTIMIZED IMPORT METHODS
    // =========================================================================

    /**
     * Import leads with batch processing (optimized)
     */
    public function importLeadsOptimized(array $rows, array $options): array
    {
        $accountId = Auth::user()->account_id;
        $userId = Auth::id();

        // Pre-load all lookup data once
        $lookup = $this->loadImportLookupData($accountId);
        $defaultStatus = $this->getDefaultLeadStatus($accountId);

        // Extract and validate phones in batch
        $phoneData = $this->preprocessImportPhones($rows, $accountId);

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'invalid_phones' => [],
            'invalid_services' => [],
        ];

        // Prepare batch data
        $leadsToCreate = [];
        $leadsToUpdate = [];
        $servicesToCreate = [];

        foreach ($rows as $row) {
            $result = $this->prepareImportRow(
                $row, 
                $lookup, 
                $options, 
                $phoneData, 
                $userId, 
                $accountId,
                $defaultStatus
            );

            if ($result['action'] === 'create') {
                $leadsToCreate[] = $result['data'];
                $servicesToCreate[] = $result['service'];
                $stats['created']++;
            } elseif ($result['action'] === 'update') {
                $leadsToUpdate[] = $result['data'];
                $servicesToCreate[] = $result['service'];
                $stats['updated']++;
            } elseif ($result['action'] === 'invalid_phone') {
                $stats['invalid_phones'][] = $result['phone'];
            } elseif ($result['action'] === 'invalid_service') {
                if (!in_array($result['service'], $stats['invalid_services'])) {
                    $stats['invalid_services'][] = $result['service'];
                }
            } else {
                $stats['skipped']++;
            }
        }

        // Batch insert/update in transaction
        DB::transaction(function () use ($leadsToCreate, $leadsToUpdate, $servicesToCreate, $accountId) {
            // Batch create new leads
            if (!empty($leadsToCreate)) {
                foreach (array_chunk($leadsToCreate, 500) as $chunk) {
                    Leads::insert($chunk);
                }
            }

            // Batch update existing leads
            foreach ($leadsToUpdate as $updateData) {
                Leads::where('phone', $updateData['phone'])
                    ->where('account_id', $accountId)
                    ->update($updateData);
            }

            // Get lead IDs for service creation
            $phones = array_merge(
                array_column($leadsToCreate, 'phone'),
                array_column($leadsToUpdate, 'phone')
            );

            if (!empty($phones)) {
                $leadIds = Leads::whereIn('phone', $phones)
                    ->where('account_id', $accountId)
                    ->pluck('id', 'phone')
                    ->toArray();

                // Create services
                $serviceRecords = [];
                foreach ($servicesToCreate as $service) {
                    if (isset($leadIds[$service['phone']])) {
                        $serviceRecords[] = [
                            'lead_id' => $leadIds[$service['phone']],
                            'service_id' => $service['service_id'],
                            'child_service_id' => $service['child_service_id'],
                            'meta_lead_id' => $service['meta_lead_id'],
                            'status' => 1,
                            'lead_status_id' => $service['lead_status_id'],
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ];
                    }
                }

                if (!empty($serviceRecords)) {
                    foreach (array_chunk($serviceRecords, 500) as $chunk) {
                        LeadsServices::insert($chunk);
                    }

                    // Deactivate old services
                    $leadIdsToUpdate = array_values($leadIds);
                    LeadsServices::whereIn('lead_id', $leadIdsToUpdate)
                        ->whereNotIn('id', function ($q) use ($leadIdsToUpdate) {
                            $q->select(DB::raw('MAX(id)'))
                              ->from('leads_services')
                              ->whereIn('lead_id', $leadIdsToUpdate)
                              ->groupBy('lead_id');
                        })
                        ->update(['status' => 0]);
                }
            }
        });

        return $stats;
    }

    /**
     * Preprocess import phones for batch lookup
     */
    protected function preprocessImportPhones(array $rows, int $accountId): array
    {
        $allPhones = [];
        $validPhones = [];
        $invalidPhones = [];

        foreach ($rows as $row) {
            $rawPhone = $row['phone'] ?? '';
            if (strlen($rawPhone) >= 10 && strlen($rawPhone) <= 13) {
                $cleanPhone = GeneralFunctions::cleanNumber($rawPhone);
                $allPhones[] = $cleanPhone;
                $validPhones[$rawPhone] = $cleanPhone;
            } else {
                $invalidPhones[] = $rawPhone;
            }
        }

        // Batch lookup existing phones
        $existingPhones = [];
        if (!empty($allPhones)) {
            $existingPhones = Leads::whereIn('phone', $allPhones)
                ->where('account_id', $accountId)
                ->pluck('phone')
                ->unique()
                ->toArray();
        }

        return [
            'valid' => $validPhones,
            'invalid' => $invalidPhones,
            'existing' => $existingPhones,
            'new' => array_diff($allPhones, $existingPhones),
        ];
    }

    /**
     * Prepare single import row data
     */
    protected function prepareImportRow(
        array $row,
        array $lookup,
        array $options,
        array $phoneData,
        int $userId,
        int $accountId,
        ?LeadStatuses $defaultStatus
    ): array {
        $rawPhone = $row['phone'] ?? '';
        
        // Check if phone is valid
        if (!isset($phoneData['valid'][$rawPhone])) {
            return ['action' => 'invalid_phone', 'phone' => $rawPhone];
        }

        $phone = $phoneData['valid'][$rawPhone];

        // Lookup service
        $serviceKey = strtolower(trim($row['service'] ?? ''));
        $serviceData = $lookup['services'][$serviceKey] ?? null;

        if (!$serviceData && !empty($serviceKey)) {
            return ['action' => 'invalid_service', 'service' => $row['service']];
        }

        if (!$serviceData) {
            return ['action' => 'skip'];
        }

        $serviceId = $serviceData['id'];
        $childServiceId = null;

        if (!empty($row['treatment'])) {
            $treatmentKey = strtolower(trim($row['treatment']));
            $childServiceId = $lookup['child_services'][$serviceId][$treatmentKey] ?? null;
        }

        // Build lead data
        $cityKey = strtolower(trim($row['city'] ?? ''));
        $leadStatusKey = strtolower(trim($row['lead_status'] ?? ''));
        $leadStatusId = $lookup['lead_statuses'][$leadStatusKey] ?? $defaultStatus?->id;

        $leadData = [
            'name' => $row['full_name'] ?? '',
            'email' => $row['email'] ?? null,
            'phone' => $phone,
            'gender' => $this->parseGender($row['gender'] ?? ''),
            'city_id' => $lookup['cities'][$cityKey] ?? null,
            'region_id' => $lookup['regions'][$cityKey] ?? null,
            'lead_source_id' => $lookup['lead_sources'][strtolower(trim($row['lead_source'] ?? ''))] ?? Config::get('constants.lead_source_social_media'),
            'location_id' => $lookup['locations'][strtolower(trim($row['centre'] ?? ''))] ?? null,
            'meta_lead_id' => !empty($row['meta_lead_id']) ? trim($row['meta_lead_id']) : null,
            'created_by' => $userId,
            'updated_by' => $userId,
            'converted_by' => $userId,
            'account_id' => $accountId,
            'active' => 1,
        ];

        $serviceRecord = [
            'phone' => $phone,
            'service_id' => $serviceId,
            'child_service_id' => $childServiceId,
            'meta_lead_id' => $leadData['meta_lead_id'],
            'lead_status_id' => $leadStatusId,
        ];

        $isNew = in_array($phone, $phoneData['new']);
        $isExisting = in_array($phone, $phoneData['existing']);

        if ($isNew) {
            $leadData['lead_status_id'] = $leadStatusId;
            $leadData['created_at'] = Carbon::now();
            $leadData['updated_at'] = Carbon::now();
            return ['action' => 'create', 'data' => $leadData, 'service' => $serviceRecord];
        }

        if ($isExisting && $options['update_records']) {
            if (!$options['skip_lead_statuses']) {
                $leadData['lead_status_id'] = $leadStatusId;
            }
            $leadData['updated_at'] = Carbon::now();
            unset($leadData['created_at']);
            return ['action' => 'update', 'data' => $leadData, 'service' => $serviceRecord];
        }

        if ($isExisting && !$options['update_records']) {
            $updateData = [
                'phone' => $phone,
                'updated_by' => $userId,
                'converted_by' => $userId,
                'updated_at' => Carbon::now(),
                'location_id' => $leadData['location_id'],
                'meta_lead_id' => $leadData['meta_lead_id'],
                'account_id' => $accountId,
            ];
            if (!$options['skip_lead_statuses']) {
                $updateData['lead_status_id'] = $leadStatusId;
            }
            return ['action' => 'update', 'data' => $updateData, 'service' => $serviceRecord];
        }

        return ['action' => 'skip'];
    }
}
