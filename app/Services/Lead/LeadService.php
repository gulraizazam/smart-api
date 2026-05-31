<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\Exceptions\LeadException;
use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Helpers\Widgets\LocationsWidget;
use App\Models\Activity;
use App\Models\AppointmentStatuses;
use App\Models\AuditTrails;
use App\Models\Cities;
use App\Models\LeadComments;
use App\Models\Leads;
use App\Models\LeadSources;
use App\Models\LeadsServices;
use App\Models\LeadStatuses;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\Regions;
use App\Models\Services;
use App\Models\Settings;
use App\Models\Telecomprovidernumber;
use App\Models\User;
use App\Services\Dashboard\Support\ResourceScopeResolver;
use App\Services\PatientManagement\PatientSearchService;
use App\Services\Phone\PhoneFormattingService;
use App\Services\Reports\Concerns\ParsesDateRange;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LeadService
{
    use ParsesDateRange;

    protected const CACHE_TTL = 3600; // 1 hour

    protected ?array $lookupCache = null;

    /**
     * Branch ids the current user may see leads for, per the project-wide
     * ResourceScopeResolver (same source the SPA reads as `assigned_centre_ids`):
     *   null  → company-wide (no branch restriction)
     *   array → that subset of branches (empty = none beyond the shared pool)
     *
     * This — not ACL::getUserCities() — is the lead visibility boundary: one
     * city can hold several branches, so city scoping leaks a sibling branch's
     * leads to a single-branch user.
     */
    protected function allowedBranchIds(): ?array
    {
        return app(ResourceScopeResolver::class)->allowedBranchIds(Auth::user());
    }

    // =========================================================================
    // Datatable & Listing
    // =========================================================================

    public function getDatatableData(array $filters, ?string $leadType = null): array
    {
        $userId = Auth::id();
        $accountId = Auth::user()->account_id;
        $filename = $leadType ? 'junk_leads' : 'leads';

        $whereConditions = $this->buildWhereConditions($filters, $filename, $userId);
        $serviceConditions = $this->buildServiceConditions($filters, $filename, $userId);
        [$orderBy, $order] = $this->getOrderParams($filters, $filename, $userId);

        $junkStatusId = $this->getJunkLeadStatus($accountId)?->id ?? 0;
        $branchIds = $this->allowedBranchIds();

        $countQuery = $this->buildCountQuery($whereConditions, $serviceConditions, $leadType, $junkStatusId, $branchIds);
        $totalRecords = $countQuery->count();

        $resultQuery = $this->buildOptimizedResultQuery($whereConditions, $serviceConditions, $leadType, $junkStatusId, $branchIds);

        return [
            'total' => $totalRecords,
            'query' => $resultQuery,
            'orderBy' => $orderBy,
            'order' => $order,
        ];
    }

    protected function buildCountQuery(
        array $where,
        array $whereService,
        ?string $leadType,
        int $junkStatusId,
        ?array $branchIds,
    ): Builder {
        $query = Leads::forBranches($branchIds);

        if (! empty($where)) {
            $query->where($where);
        }

        if (! empty($whereService)) {
            $query->whereExists(function ($subquery) use ($whereService): void {
                $subquery->select(DB::raw(1))
                    ->from('leads_services')
                    ->whereColumn('leads_services.lead_id', 'leads.id')
                    ->where('leads_services.status', 1)
                    ->where($whereService);
            });
        }

        return $leadType
            ? $query->where('leads.lead_status_id', $junkStatusId)
            : $query->where('leads.lead_status_id', '!=', $junkStatusId);
    }

    protected function buildOptimizedResultQuery(
        array $where,
        array $whereService,
        ?string $leadType,
        int $junkStatusId,
        ?array $branchIds,
    ): Builder {
        $query = Leads::with([
            'lead_service' => function ($q): void {
                $q->select('id', 'lead_id', 'service_id', 'child_service_id', 'status')
                    ->with(['service:id,name', 'childservice:id,name']);
            },
            'city:id,name',
            'towns:id,name',
        ])->forBranches($branchIds);

        if (! empty($where)) {
            $query->where($where);
        }

        if (! empty($whereService)) {
            $query->whereExists(function ($subquery) use ($whereService): void {
                $subquery->select(DB::raw(1))
                    ->from('leads_services')
                    ->whereColumn('leads_services.lead_id', 'leads.id')
                    ->where('leads_services.status', 1)
                    ->where($whereService);
            });
        }

        return $leadType
            ? $query->where('leads.lead_status_id', $junkStatusId)
            : $query->where('leads.lead_status_id', '!=', $junkStatusId);
    }

    public function getFilterData(string $filename): array
    {
        $accountId = Auth::user()->account_id;
        $userId = Auth::id();

        // Per-user key: ACL::getUserCities/Centres/Regions vary by user within an account.
        $cacheKey = "lead_filters_user_{$userId}";

        $filterValues = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($accountId): array {
            $junkStatus = $this->getJunkLeadStatus($accountId);

            return [
                'cities' => Cities::getActiveSortedFeatured(ACL::getUserCities()),
                'locations' => Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId)->pluck('name', 'id'),
                'regions' => Regions::getActiveSorted(ACL::getUserRegions()),
                'users' => User::getAllActiveRecords($accountId)->pluck('name', 'id'),
                'lead_statuses' => $junkStatus
                    ? LeadStatuses::getLeadStatuses($junkStatus->id)
                    : LeadStatuses::getLeadStatuses(),
                'Services' => Services::where([
                    'slug' => 'custom',
                    'active' => 1,
                ])->rootServices()->pluck('name', 'id'),
            ];
        });

        $activeFilters = Filters::all($userId, $filename);
        $filterValues['leadServices'] = Filters::get($userId, 'leads', 'service_id');

        return [
            'filter_values' => $filterValues,
            'active_filters' => $activeFilters,
        ];
    }

    // =========================================================================
    // CRUD Operations
    // =========================================================================

    public function createLead(array $data): Leads
    {
        return DB::transaction(function () use ($data): Leads {
            $accountId = Auth::user()->account_id;
            $userId = Auth::id();

            $data['phone'] = PhoneFormattingService::cleanNumber($data['phone']);

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

    protected function createNewLead(array $data, int $accountId, int $userId): Leads
    {
        if (empty($data['lead_status_id'])) {
            $data['lead_status_id'] = $this->getDefaultLeadStatus($accountId)?->id;
        }

        if (! empty($data['city_id'])) {
            $data['region_id'] = $this->getRegionFromCity((int) $data['city_id']);
        }

        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        $data['converted_by'] = $userId;
        $data['account_id'] = $accountId;
        $data['created_at'] = Carbon::now();

        $lead = Leads::create($data);

        $this->createLeadService($lead->id, $data, $accountId);
        $this->logLeadActivity($lead, $data);

        return $lead;
    }

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

        $this->createLeadService($lead->id, $data, $accountId);
        $this->logLeadActivity($lead, $data);

        return $lead;
    }

    public function updateLead(int $id, array $data): Leads
    {
        return DB::transaction(function () use ($id, $data): Leads {
            $lead = Leads::findOrFail($id);

            if (isset($data['lead_status_id']) && $data['lead_status_id'] != $lead->lead_status_id) {
                $this->validateStatusChange($lead, (int) $data['lead_status_id']);
            }

            if (! empty($data['service_id'])) {
                $this->updateLeadServices($id, $data);
            }

            $data['updated_at'] = Carbon::now();
            $data['updated_by'] = Auth::id();
            $data['account_id'] = Auth::user()->account_id;

            $lead->update($data);

            if (! empty($data['phone']) && ! empty($data['name'])) {
                PatientSearchService::patientNameUpdate($data['phone'], $data['name']);
            }

            return $lead->fresh();
        });
    }

    public function deleteLead(int $id): bool
    {
        return Leads::where('account_id', Auth::user()->account_id)->findOrFail($id)->delete();
    }

    public function bulkDelete(array $ids): int
    {
        return Leads::whereIn('id', $ids)->delete();
    }

    // =========================================================================
    // Status Management
    // =========================================================================

    public function updateLeadStatus(int $leadId, array $data): Leads
    {
        return DB::transaction(function () use ($leadId, $data): Leads {
            $lead = Leads::findOrFail($leadId);

            $statusId = $data['lead_status_chalid_id'] ?? $data['lead_status_parent_id'];

            // `validateStatusChange` enforces both directions:
            //   - outbound: cannot leave Arrived/Converted (locks once
            //     the lead has actually arrived or paid)
            //   - inbound: cannot manually pick Booked/Arrived/Converted
            //     — those are auto-set when the matching action fires
            //     (booking → Booked, invoice paid → Arrived, payment →
            //     Converted). Operators see only Open / Call Again /
            //     Not Interested / Junk in the dropdown anyway, but
            //     this is the API-level guard.
            $this->validateStatusChange($lead, (int) $statusId);

            $lead->update([
                'lead_status_id' => $statusId,
                'converted_by' => Auth::id(),
            ]);

            $comment = $data['comment1'] ?? $data['comment2'] ?? null;
            if ($comment) {
                LeadComments::create([
                    'lead_id' => $leadId,
                    'comment' => $comment,
                    'created_by' => Auth::id(),
                    'account_id' => Auth::user()->account_id,
                ]);
            }

            return $lead->fresh();
        });
    }

    public function toggleStatus(int $id, int|string $status): Leads
    {
        $lead = Leads::where('account_id', Auth::user()->account_id)->findOrFail($id);
        $lead->update(['active' => $status]);

        return $lead;
    }

    protected function validateStatusChange(Leads $lead, ?int $targetStatusId = null): void
    {
        // Outbound guard — once a lead is Arrived or Converted it is
        // locked. The only way out is the wrong-conversion admin tool
        // (`WrongConversionService`), which doesn't go through this
        // path.
        if ($lead->lead_status_id) {
            $currentStatus = LeadStatuses::find($lead->lead_status_id);
            if ($currentStatus?->is_arrived || $currentStatus?->is_converted) {
                throw LeadException::statusChangeNotAllowed($currentStatus->name);
            }
        }

        // Inbound guard — auto-only flagged statuses cannot be set
        // manually. Booked/Arrived/Converted are cascaded from
        // appointment events; any direct API call to set them is
        // either an SPA bug or an operator trying to bypass the
        // funnel. Junk has its own dedicated route.
        if ($targetStatusId !== null) {
            $targetStatus = LeadStatuses::find($targetStatusId);
            if ($targetStatus?->is_booked || $targetStatus?->is_arrived || $targetStatus?->is_converted) {
                throw LeadException::targetStatusNotAllowed($targetStatus->name);
            }
        }
    }

    // =========================================================================
    // Service Management
    // =========================================================================

    protected function updateLeadServices(int $leadId, array $data): void
    {
        if (! empty($data['old_service'])) {
            LeadsServices::where([
                'lead_id' => $leadId,
                'service_id' => $data['old_service'],
                'consultancy_id' => null,
            ])->delete();
        }

        $childServices = $data['child_service_id'] ?? [];

        if (! empty($childServices)) {
            foreach ($childServices as $childServiceId) {
                $leadService = LeadsServices::updateOrCreate([
                    'lead_id' => $leadId,
                    'service_id' => $data['service_id'],
                    'child_service_id' => $childServiceId,
                    'consultancy_id' => null,
                ], ['status' => 1]);

                LeadsServices::where('lead_id', $leadId)
                    ->where('id', '!=', $leadService->id)
                    ->update(['status' => 0]);
            }
        } else {
            $leadService = LeadsServices::updateOrCreate([
                'lead_id' => $leadId,
                'service_id' => $data['service_id'],
                'consultancy_id' => null,
            ], ['status' => 1]);

            LeadsServices::where('lead_id', $leadId)
                ->where('id', '!=', $leadService->id)
                ->update(['status' => 0]);
        }
    }

    public function createLeadService(int $leadId, array $data, int $accountId): LeadsServices
    {
        $openStatus = $this->getDefaultLeadStatus($accountId);
        $metaLeadId = ! empty($data['meta_lead_id']) ? trim($data['meta_lead_id']) : null;

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

        LeadsServices::where('lead_id', $leadId)
            ->where('id', '!=', $leadService->id)
            ->update(['status' => 0]);

        return $leadService;
    }

    // =========================================================================
    // Read / Detail / Form Data
    // =========================================================================

    public function getLeadDetail(int $id): ?Leads
    {
        return Leads::with([
            'lead_comments.user:id,name',
            'towns:id,name',
            'city:id,name',
            'lead_source:id,name',
            'lead_status:id,name,parent_id',
            'lead_service.service:id,name',
            'lead_service.childservice:id,name',
            'lead_service.leadStatus:id,name',
            'user:id,name',
        ])->find($id);
    }

    public function getLeadForEdit(int $id): ?Leads
    {
        return Leads::with([
            'lead_service.service:id,name',
            'lead_service.childservice:id,name',
        ])->where([
            'id' => $id,
            'account_id' => Auth::user()->account_id,
        ])->first();
    }

    public function findLead(int $id): ?Leads
    {
        return Leads::find($id);
    }

    public function getCreateFormData(): array
    {
        $formData = $this->getFormLookupData();
        $employees = User::getAllActiveRecords(Auth::user()->account_id)?->pluck('full_name', 'id') ?? [];

        return [
            'Services' => $formData['services'],
            'cities' => $formData['cities'],
            'lead_sources' => $formData['lead_sources'],
            'lead_statuses' => $formData['lead_statuses'],
            'lead' => [
                'id' => null,
                'name' => null,
                'email' => null,
                'phone' => null,
                'gender' => null,
            ],
            'leadServices' => null,
            'employees' => $employees,
            'edit_status' => 0,
            'gender' => $formData['gender'],
        ];
    }

    public function getEditFormData(int $id): ?array
    {
        $lead = $this->getLeadForEdit($id);

        if (! $lead) {
            return null;
        }

        $formData = $this->getFormLookupData();
        $locations = Locations::where(['active' => 1, 'city_id' => $lead->city_id])->pluck('name', 'id');
        $employees = User::getAllActiveRecords(Auth::user()->account_id)?->pluck('full_name', 'id') ?? [];

        $childServiceIds = $lead->lead_service->pluck('child_service_id')->toArray();
        $childServices = Services::whereIn('id', $childServiceIds)
            ->where(['slug' => 'custom', 'active' => 1])
            ->pluck('name', 'id');

        return [
            'Services' => $formData['services'],
            'child_services' => $childServices,
            'lead' => $lead,
            'locations' => $locations,
            'cities' => $formData['cities'],
            'lead_sources' => $formData['lead_sources'],
            'lead_statuses' => $formData['lead_statuses'],
            'employees' => $employees,
            'edit_status' => 1,
            'gender' => $formData['gender'],
        ];
    }

    public function getFormLookupData(): array
    {
        $accountId = Auth::user()->account_id;
        $userId = Auth::id();

        $accountData = Cache::remember("lead_form_lookup_{$accountId}", self::CACHE_TTL, fn (): array => [
            'lead_sources' => LeadSources::getActiveSorted(),
            'lead_statuses' => LeadStatuses::getLeadStatuses(),
            'services' => Services::where([
                'slug' => 'custom',
                'active' => 1,
            ])->rootServices()->pluck('name', 'id'),
            'gender' => Config::get('constants.gender_array'),
        ]);

        // Cities depend on ACL::getUserCities() which is per-user; caching them under the
        // account key leaked one user's allowed cities to another (and served an empty
        // list to everyone if the first caller had no featured cities in their ACL).
        $accountData['cities'] = Cache::remember(
            "lead_form_lookup_cities_user_{$userId}",
            self::CACHE_TTL,
            fn () => Cities::getActiveSortedFeatured(ACL::getUserCities()),
        );

        return $accountData;
    }

    // =========================================================================
    // Lead Status Hierarchy
    // =========================================================================

    public function getLeadStatusesWithChildren(int $leadId): array
    {
        $lead = Leads::find($leadId);

        if (! $lead) {
            throw LeadException::notFound($leadId);
        }

        $leadStatus = LeadStatuses::find($lead->lead_status_id);
        $parentStatuses = LeadStatuses::getLeadStatuses();
        $comments = LeadComments::where('lead_id', $leadId)->get();

        if ($leadStatus->parent_id === null || $leadStatus->parent_id == 0) {
            $parentStatus = $leadStatus;
            $childStatus = null;
        } else {
            $childStatus = $leadStatus;
            $parentStatus = LeadStatuses::find($leadStatus->parent_id);
        }

        $childStatuses = LeadStatuses::where('parent_id', $parentStatus->id)->get();

        $accountId = Auth::user()->account_id;
        $commentMap = LeadStatuses::where('account_id', $accountId)
            ->where('active', 1)
            ->pluck('is_comment', 'id')
            ->map(fn ($v) => (int) $v)
            ->toArray();

        return [
            'lead' => $lead,
            'lead_statuses_Pdata' => $parentStatuses,
            'lead_statuses_Cdata' => $childStatuses->isEmpty() ? 'nothing' : $childStatuses,
            'lead_status_parent' => $parentStatus,
            'lead_status_chalid' => $childStatus ?? 'null',
            'lead_status_comment' => $comments,
            'lead_status_is_comment_map' => $commentMap,
        ];
    }

    public function getStatusWithChildren(int $statusId): ?array
    {
        $leadStatus = LeadStatuses::find($statusId);

        if (! $leadStatus) {
            return null;
        }

        return [
            'd' => LeadStatuses::where('parent_id', $leadStatus->id)->get(),
            'lead_status' => $leadStatus,
        ];
    }

    public function getChildStatusWithParent(int $childStatusId): array
    {
        $childStatus = LeadStatuses::find($childStatusId);
        $parentStatus = $childStatus ? LeadStatuses::find($childStatus->parent_id) : null;

        return [
            'd' => $childStatus,
            'lead_status2' => $parentStatus,
        ];
    }

    // =========================================================================
    // Child Services
    // =========================================================================

    public function getChildServices(int $serviceId): Collection
    {
        return Services::where([
            'parent_id' => $serviceId,
            'active' => 1,
        ])->pluck('name', 'id');
    }

    public function getChildServicesWithLead(int $serviceId, ?int $leadId = null): array
    {
        $childServices = $this->getChildServices($serviceId);
        $leadChildService = '';

        if ($leadId) {
            $lead = Leads::with('lead_service')->find($leadId);
            $leadChildService = $lead?->lead_service ?? '';
        }

        return [
            'dropdown' => $childServices,
            'lead_child_service' => $leadChildService,
        ];
    }

    public function getEditServiceData(int $leadId, int $serviceId): array
    {
        $leadService = LeadsServices::with('service', 'childservice')
            ->where(['lead_id' => $leadId, 'service_id' => $serviceId])
            ->get();

        return [
            'lead_service' => $leadService,
            'Services' => Services::where(['slug' => 'custom', 'active' => 1])->rootServices()->pluck('name', 'id'),
            'Child_service' => Services::where(['slug' => 'custom', 'parent_id' => $serviceId, 'active' => 1])->pluck('name', 'id'),
        ];
    }

    // =========================================================================
    // Comments
    // =========================================================================

    public function addComment(int $leadId, string $comment): LeadComments
    {
        return LeadComments::create([
            'lead_id' => $leadId,
            'comment' => $comment,
            'created_by' => Auth::id(),
            'account_id' => Auth::user()->account_id,
        ]);
    }

    // =========================================================================
    // Search
    // =========================================================================

    public function searchLeadsById(string $search, int $accountId): Collection
    {
        $canViewContact = Gate::allows('leads.list.view_contact');

        if (is_numeric($search)) {
            $leads = Leads::where([
                'active' => 1,
                'account_id' => $accountId,
                'id' => $search,
            ])->select('name', 'id', 'phone')->get();

            if ($leads->isNotEmpty()) {
                if (! $canViewContact) {
                    $leads->each(fn ($lead) => $lead->phone = '');
                }

                return $leads;
            }
        }

        $searchTerm = PatientSearchService::patientSearch($search);
        $phoneNumeric = PhoneFormattingService::clearnString($search);

        $query = Leads::where(['active' => 1, 'account_id' => $accountId]);

        if (is_numeric($phoneNumeric)) {
            // Only allow phone-prefix matching when user can see phones; otherwise
            // treat the numeric input as an ID lookup to avoid enumerating phones.
            if ($canViewContact) {
                $phone = PhoneFormattingService::cleanNumber($search);
                $query->where('phone', 'LIKE', "%{$phone}%");
            } else {
                $query->where('id', 'LIKE', "%{$phoneNumeric}%");
            }
        } else {
            $query->where('name', 'LIKE', "%{$searchTerm}%");
        }

        $results = $query->select('name', 'id', 'phone')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->unique('phone');

        if (! $canViewContact) {
            $results->each(fn ($lead) => $lead->phone = '');
        }

        return $results;
    }

    public function searchByPhone(string $phone, int $accountId): Collection
    {
        // Blanket deny: a phone-prefix lookup has no legitimate use without
        // the leads-module contact perm.
        if (! Gate::allows('leads.list.view_contact')) {
            return new Collection;
        }

        return Leads::where([
            ['active', '=', 1],
            ['account_id', '=', $accountId],
            ['phone', 'LIKE', "%{$phone}%"],
        ])
            ->select('name', 'id', 'phone')
            ->limit(50)
            ->get();
    }

    public function searchLeads(string $search, int $accountId): Collection
    {
        return $this->searchLeadsById($search, $accountId);
    }

    // =========================================================================
    // Import
    // =========================================================================

    public function importLeads(array $rows, array $options): array
    {
        $accountId = Auth::user()->account_id;
        $userId = Auth::id();
        $now = Carbon::now();

        $lookupData = $this->loadImportLookupData($accountId);
        $defaultStatusId = $this->getDefaultLeadStatus($accountId)?->id;

        $stats = [
            'created' => 0,
            'updated' => 0,
            'invalid_phones' => [],
            'invalid_services' => [],
        ];

        $validRows = [];
        $phoneToRowMap = [];

        foreach ($rows as $row) {
            $phone = PhoneFormattingService::cleanNumber($row['phone'] ?? '');

            if (strlen($phone) < 10 || strlen($phone) > 12) {
                $stats['invalid_phones'][] = $row['phone'] ?? '';

                continue;
            }

            $serviceKey = strtolower(trim($row['service'] ?? ''));
            $serviceData = $lookupData['services'][$serviceKey] ?? null;

            if (! $serviceData && ! empty($serviceKey)) {
                if (! in_array($row['service'], $stats['invalid_services'], true)) {
                    $stats['invalid_services'][] = $row['service'];
                }

                continue;
            }

            if (! $serviceData) {
                continue;
            }

            $row['_phone_clean'] = $phone;
            $row['_service_id'] = $serviceData['id'];
            $row['_child_service_id'] = null;

            if (! empty($row['treatment'])) {
                $treatmentKey = strtolower(trim($row['treatment']));
                $row['_child_service_id'] = $lookupData['child_services'][$serviceData['id']][$treatmentKey] ?? null;
            }

            $validRows[] = $row;
            $phoneToRowMap[$phone] = $row;
        }

        if (empty($validRows)) {
            return $stats;
        }

        $allPhones = array_keys($phoneToRowMap);
        $existingLeads = Leads::whereIn('phone', $allPhones)
            ->where('account_id', $accountId)
            ->get()
            ->keyBy('phone');

        $leadsToCreate = [];
        $leadsToUpdate = [];
        $phoneToLeadId = [];

        foreach ($validRows as $row) {
            $phone = $row['_phone_clean'];
            $cityKey = strtolower(trim($row['city'] ?? ''));
            $leadStatusKey = strtolower(trim($row['lead_status'] ?? ''));
            $leadStatusId = $lookupData['lead_statuses'][$leadStatusKey] ?? $defaultStatusId;

            $leadData = [
                'name' => $row['full_name'] ?? '',
                'email' => $row['email'] ?? null,
                'phone' => $phone,
                'gender' => $this->parseGender($row['gender'] ?? ''),
                'city_id' => $lookupData['cities'][$cityKey] ?? null,
                'region_id' => $lookupData['regions'][$cityKey] ?? null,
                'lead_source_id' => $lookupData['lead_sources'][strtolower(trim($row['lead_source'] ?? ''))] ?? Config::get('constants.lead_source_social_media'),
                'location_id' => $lookupData['locations'][strtolower(trim($row['centre'] ?? ''))] ?? null,
                'meta_lead_id' => ! empty($row['meta_lead_id']) ? trim($row['meta_lead_id']) : null,
                'account_id' => $accountId,
                'updated_by' => $userId,
                'converted_by' => $userId,
                'updated_at' => $now,
                'active' => 1,
            ];

            if (isset($existingLeads[$phone])) {
                $existingLead = $existingLeads[$phone];
                $phoneToLeadId[$phone] = $existingLead->id;

                if ($options['update_records']) {
                    if (! $options['skip_lead_statuses']) {
                        $leadData['lead_status_id'] = $leadStatusId;
                    }
                    $leadsToUpdate[$existingLead->id] = $leadData;
                    $stats['updated']++;
                } else {
                    $leadsToUpdate[$existingLead->id] = [
                        'updated_by' => $userId,
                        'converted_by' => $userId,
                        'updated_at' => $now,
                        'location_id' => $leadData['location_id'],
                        'meta_lead_id' => $leadData['meta_lead_id'],
                    ];
                    if (! $options['skip_lead_statuses']) {
                        $leadsToUpdate[$existingLead->id]['lead_status_id'] = $leadStatusId;
                    }
                    $stats['updated']++;
                }
            } else {
                $leadData['lead_status_id'] = $leadStatusId;
                $leadData['created_by'] = $userId;
                $leadData['created_at'] = $now;
                $leadsToCreate[$phone] = $leadData;
                $stats['created']++;
            }
        }

        $servicesLookup = Services::whereIn('id', array_unique(array_column($validRows, '_service_id')))
            ->pluck('name', 'id')
            ->toArray();
        $locationsLookup = Locations::with('city')
            ->whereIn('id', array_filter(array_unique(array_map(fn ($r) => $lookupData['locations'][strtolower(trim($r['centre'] ?? ''))] ?? null, $validRows))))
            ->get()
            ->keyBy('id');

        return DB::transaction(function () use ($leadsToCreate, $leadsToUpdate, $validRows, $phoneToLeadId, $defaultStatusId, $now, $stats, $accountId, $userId, $servicesLookup, $locationsLookup, $lookupData): array {
            if (! empty($leadsToCreate)) {
                foreach (array_chunk($leadsToCreate, 500, true) as $chunk) {
                    Leads::insert(array_values($chunk));
                }

                $newPhones = array_keys($leadsToCreate);
                $newLeads = Leads::whereIn('phone', $newPhones)->pluck('id', 'phone');
                foreach ($newLeads as $phone => $id) {
                    $phoneToLeadId[$phone] = $id;
                }
            }

            if (! empty($leadsToUpdate)) {
                foreach ($leadsToUpdate as $leadId => $data) {
                    Leads::where('id', $leadId)->update($data);
                }
            }

            $leadServicesToCreate = [];
            $leadIdsToDeactivate = [];

            foreach ($validRows as $row) {
                $phone = $row['_phone_clean'];
                $leadId = $phoneToLeadId[$phone] ?? null;

                if (! $leadId) {
                    continue;
                }

                $leadIdsToDeactivate[] = $leadId;
                $leadServicesToCreate[] = [
                    'lead_id' => $leadId,
                    'service_id' => $row['_service_id'],
                    'child_service_id' => $row['_child_service_id'],
                    'meta_lead_id' => ! empty($row['meta_lead_id']) ? trim($row['meta_lead_id']) : null,
                    'status' => 1,
                    'lead_status_id' => $defaultStatusId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($leadIdsToDeactivate)) {
                LeadsServices::whereIn('lead_id', $leadIdsToDeactivate)->update(['status' => 0]);
            }

            if (! empty($leadServicesToCreate)) {
                foreach (array_chunk($leadServicesToCreate, 500) as $chunk) {
                    LeadsServices::insert($chunk);
                }
            }

            $this->batchCreateActivityLogs($validRows, $phoneToLeadId, $accountId, $userId, $defaultStatusId, $now, $servicesLookup, $locationsLookup, $lookupData);

            return $stats;
        });
    }

    protected function batchCreateActivityLogs(
        array $validRows,
        array $phoneToLeadId,
        int $accountId,
        int $userId,
        ?int $defaultStatusId,
        Carbon $now,
        array $servicesLookup,
        \Illuminate\Database\Eloquent\Collection $locationsLookup,
        array $lookupData,
    ): void {
        $activitiesToCreate = [];
        $creatorName = Auth::user()->name ?? 'System';

        foreach ($validRows as $row) {
            $phone = $row['_phone_clean'];
            $leadId = $phoneToLeadId[$phone] ?? null;

            if (! $leadId) {
                continue;
            }

            $serviceName = $servicesLookup[$row['_service_id']] ?? '';
            $patientName = $row['full_name'] ?? 'Unknown';
            $locationId = $lookupData['locations'][strtolower(trim($row['centre'] ?? ''))] ?? null;
            $locationName = '';

            if ($locationId && isset($locationsLookup[$locationId])) {
                $loc = $locationsLookup[$locationId];
                $locationName = ($loc->city->name ?? '').'-'.($loc->name ?? '');
            }

            $description = '<span class="highlight">'.e($creatorName).'</span> created a <span class="highlight-orange">'.e($serviceName ?: 'Service').'</span> lead for <span class="highlight-orange">'.e($patientName).'</span>'.($locationName ? ' in <span class="highlight">'.e($locationName).'</span>' : '');

            $activitiesToCreate[] = [
                'account_id' => $accountId,
                'action' => 'Lead Created',
                'activity_type' => 'lead_created',
                'description' => $description,
                'patient' => $patientName,
                'patient_id' => null,
                'lead_id' => $leadId,
                'lead_status' => 'Open',
                'lead_status_id' => $defaultStatusId,
                'service' => $serviceName,
                'service_id' => $row['_service_id'],
                'location' => $locationName,
                'centre_id' => $locationId,
                'created_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($activitiesToCreate)) {
            foreach (array_chunk($activitiesToCreate, 500) as $chunk) {
                Activity::insert($chunk);
            }
        }
    }

    protected function loadImportLookupData(int $accountId): array
    {
        return Cache::remember("lead_import_lookup_{$accountId}", 300, function () use ($accountId): array {
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
                ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
                ->toArray();

            $leadStatuses = LeadStatuses::where('account_id', $accountId)
                ->pluck('id', 'name')
                ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
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
                ->mapWithKeys(fn ($id, $name) => [strtolower(trim($name)) => $id])
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

    protected function parseGender(string $gender): int
    {
        return strtolower(trim($gender)) === 'female' ? 2 : 1;
    }

    // =========================================================================
    // Export
    // =========================================================================

    public function getExportData(array $filters): \Illuminate\Database\Eloquent\Collection
    {
        return $this->buildExportQuery($filters)
            ->with([
                'lead_service' => fn ($q) => $q->where('status', 1)->with(['service:id,name', 'childservice:id,name']),
                'city:id,name',
                'towns:id,name',
                'region:id,name',
                'lead_status:id,name',
                'user:id,name',
            ])
            ->get();
    }

    protected function buildExportQuery(array $filters): Builder
    {
        $where = [];

        if (! empty($filters['created_at'])) {
            [$startDate, $endDate] = self::parseDateRangeForFilter($filters['created_at']);
            $where[] = ['created_at', '>=', $startDate];
            $where[] = ['created_at', '<=', $endDate];
        }

        $simpleFilters = [
            'id' => 'id',
            'lead_status_id' => 'lead_status_id',
            'city_id' => 'city_id',
            'location_id' => 'location_id',
            'region_id' => 'region_id',
            'created_by' => 'created_by',
            'phone' => 'phone',
            'gender_id' => 'gender',
        ];

        foreach ($simpleFilters as $requestKey => $column) {
            $value = $filters[$requestKey] ?? null;
            if ($value && $value !== 'undefined' && $value !== 'null') {
                $where[] = [$column, '=', $value];
            }
        }

        if (! empty($filters['name'])) {
            $where[] = ['name', 'like', '%'.$filters['name'].'%'];
        }

        $query = Leads::forAccount()->forBranches($this->allowedBranchIds());

        if (! empty($where)) {
            $query->where($where);
        }

        $serviceId = $filters['service_id'] ?? null;
        if ($serviceId) {
            $query->with(['lead_service' => fn ($q) => $q->where(['service_id' => $serviceId, 'status' => 1])->whereNotNull('service_id')]);
        } else {
            $query->with(['lead_service' => fn ($q) => $q->where('status', 1)->whereNotNull('service_id')]);
        }

        return $query->select([
            '*',
            'leads.created_by as lead_created_by',
            'leads.id as lead_id',
            'leads.created_at as lead_created_at',
        ])->orderByDesc('id')->latest();
    }

    // =========================================================================
    // Conversion
    // =========================================================================

    public function getConversionData(int $id): ?array
    {
        $accountId = Auth::user()->account_id;

        $lead = Leads::with(['lead_service', 'patient'])->where([
            'id' => $id,
            'account_id' => $accountId,
        ])->first();

        if (! $lead) {
            return null;
        }

        $userInfo = User::where(['id' => $lead->patient_id, 'active' => 1, 'account_id' => $accountId])->first();
        $employees = User::getAllActiveRecords($accountId)?->pluck('full_name', 'id') ?? [];
        $cities = Cities::getActiveFeaturedOnly(ACL::getUserCities(), $accountId)
            ->get()?->pluck('full_name', 'id') ?? collect();

        return [
            'services' => Services::getGroupsActiveOnly()->pluck('name', 'id'),
            'lead' => $lead,
            'employees' => $employees,
            'cities' => $cities,
            'lead_sources' => LeadSources::getActiveSorted(),
            'user_info' => $userInfo,
            'setting' => Settings::where('slug', 'sys-virtual-consultancy')->first(),
            'consultancy_types' => Config::get('constants.consultancy_type_array'),
        ];
    }

    // =========================================================================
    // SMS
    // =========================================================================

    public function sendSmsToLead(int $id): array
    {
        $lead = Leads::findOrFail($id);

        return $this->sendSMS($lead->id, $lead->phone);
    }

    public function sendSMS(int $leadId, string $phone): array
    {
        return ['status' => true];
    }

    public function prepareSMSContent(?int $leadId, string $smsContent): string
    {
        if (! $leadId) {
            return $smsContent;
        }

        $setting = Settings::find(5);
        if ($setting) {
            $smsContent = str_replace('##head_office_phone##', $setting->data, $smsContent);
        }

        $lead = Leads::with(['city', 'lead_source', 'lead_status'])->find($leadId);
        if (! $lead) {
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

    // =========================================================================
    // Junk Management
    // =========================================================================

    public function removeLeadFromJunk(int $id): array
    {
        $lead = Leads::where([
            'id' => $id,
            'account_id' => Auth::user()->account_id,
        ])->first();

        if (! $lead) {
            return ['status' => false, 'message' => 'Lead not found.'];
        }

        $openStatus = $this->getDefaultLeadStatus(Auth::user()->account_id);

        if (! $openStatus) {
            return ['status' => false, 'message' => 'Open status not found.'];
        }

        $lead->update([
            'lead_status_id' => $openStatus->id,
            'updated_by' => Auth::id(),
        ]);

        LeadsServices::where('lead_id', $lead->id)
            ->where('status', 1)
            ->update(['lead_status_id' => $openStatus->id]);

        return ['status' => true, 'message' => 'Lead has been removed from junk.'];
    }

    // =========================================================================
    // City
    // =========================================================================

    public function updateLeadCity(int $leadId, int $cityId): ?array
    {
        $city = Cities::find($cityId);
        $lead = Leads::find($leadId);

        if (! $lead || ! $city) {
            return null;
        }

        $lead->update([
            'city_id' => $city->id,
            'region_id' => $city->region_id,
        ]);

        return ['city_name' => $city->name];
    }

    // =========================================================================
    // Lead Data Resolution
    // =========================================================================

    public function resolveLeadData(array $requestData, bool $hasManagePermission = true): array
    {
        $data = $requestData;
        $data['status'] = 0;
        $data['patient_id'] = 0;

        if (! $hasManagePermission || empty($requestData['phone']) || ! empty($requestData['lead_id'])) {
            return $data;
        }

        $phone = ($requestData['phone'] === '***********')
            ? ($requestData['old_phone'] ?? '')
            : $requestData['phone'];

        $phone = PhoneFormattingService::cleanNumber($phone);
        $patient = Patients::getByPhone($phone, Auth::user()->account_id, $requestData['patient_id'] ?? null);

        if (! $patient) {
            $data['status'] = 1;
            $data['service_id'] = $requestData['service_id'] ?? null;
            $data['phone'] = $requestData['phone'] ?? null;
            $data['cnic'] = $requestData['cnic'] ?? null;
            $data['dob'] = $requestData['dob'] ?? null;
            $data['address'] = $requestData['address'] ?? null;
            $data['referred_by'] = $requestData['referred_by'] ?? null;
        } else {
            $lead = Leads::where([
                'patient_id' => $patient->id,
                'service_id' => $requestData['service_id'] ?? null,
            ])->first();

            if ($lead) {
                $data['id'] = $lead->id;
                $data['city_id'] = $lead->city_id;
                $data['town_id'] = $lead->town_id;
                $data['service_id'] = $lead->service_id;
                $data['lead_source_id'] = $lead->lead_source_id;
                $data['lead_status_id'] = $lead->lead_status_id;
            } else {
                $data['service_id'] = $requestData['service_id'] ?? null;
            }

            $data['patient_id'] = $patient->id;
            $data['gender'] = $patient->gender;
            $data['phone'] = $patient->phone;
            $data['cnic'] = $patient->cnic;
            $data['dob'] = $patient->dob;
            $data['address'] = $patient->address;
            $data['name'] = $patient->name;
            $data['email'] = $patient->email;
            $data['referred_by'] = $patient->referred_by;
        }

        return $data;
    }

    // =========================================================================
    // Batch Status Lookup (N+1 prevention)
    // =========================================================================

    public function batchLoadStatusLookup(array $statusIds): array
    {
        if (empty($statusIds)) {
            return [];
        }

        $leadStatuses = LeadStatuses::whereIn('id', $statusIds)
            ->select('id', 'name', 'parent_id')
            ->get()
            ->keyBy('id')
            ->toArray();

        $parentIds = collect($leadStatuses)
            ->pluck('parent_id')
            ->filter()
            ->diff(array_keys($leadStatuses))
            ->unique()
            ->toArray();

        if (! empty($parentIds)) {
            $parentStatuses = LeadStatuses::whereIn('id', $parentIds)
                ->select('id', 'name', 'parent_id')
                ->get()
                ->keyBy('id')
                ->toArray();
            $leadStatuses = array_replace($leadStatuses, $parentStatuses);
        }

        return $leadStatuses;
    }

    // =========================================================================
    // Record Management (used by other modules)
    // =========================================================================

    public function createLeadRecord(array $data, ?string $status = null): Leads
    {
        return DB::transaction(function () use ($data, $status): Leads {
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

            if (! empty($data['city_id'])) {
                $city = Cities::find($data['city_id']);
                $data['region_id'] = $city?->region_id;
            }

            $existingLead = Leads::where([
                'phone' => $data['phone'],
                'account_id' => $accountId,
            ])->first();

            if (! $existingLead) {
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

    public function updateLeadRecord(int $id, array $data, bool $isAppointment = false): ?Leads
    {
        return DB::transaction(function () use ($id, $data, $isAppointment): ?Leads {
            $record = Leads::find($id);
            if (! $record) {
                return null;
            }

            $oldData = $isAppointment ? $record->toArray() : [];

            if (! empty($data['city_id'])) {
                $city = Cities::find($data['city_id']);
                $data['region_id'] = $city?->region_id;
            }

            $data['updated_at'] = Carbon::now();
            $record->update($data);

            AuditTrails::editEventLogger('leads', 'Edit', $data, Leads::getFillableFields(), $oldData, $record);

            return $record;
        });
    }

    // =========================================================================
    // Reports
    // =========================================================================

    public function getLeadReport(array $filters): Collection
    {
        $query = $this->buildReportBaseQuery($filters);
        $this->applyReportFilters($query, $filters);

        if (! empty($filters['age_group_range'])) {
            $ageRange = explode(':', $filters['age_group_range']);
            $from = Carbon::now()->subYears((int) $ageRange[1])->toDateString();
            $to = Carbon::now()->subYears((int) $ageRange[0])->toDateString();
            $query->whereBetween('users.dob', [$from, $to]);
        }

        if (! empty($filters['telecomprovider_id'])) {
            $providers = Telecomprovidernumber::whereIn('id', $filters['telecomprovider_id'])->get();
            $prefixes = $providers->pluck('pre_fix')->map(fn ($p) => ltrim($p, '0'))->toArray();

            if (! empty($prefixes)) {
                $query->where(function ($q) use ($prefixes): void {
                    foreach ($prefixes as $i => $prefix) {
                        $method = $i === 0 ? 'where' : 'orWhere';
                        $q->$method('users.phone', 'like', $prefix.'%');
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

    public function getMarketingReport(array $filters): Collection
    {
        $accountId = Auth::user()->account_id;
        $junkStatusId = $this->getJunkLeadStatus($accountId)?->id ?? Config::get('constants.lead_status_junk');

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

    public function getLeadSummaryReport(array $filters): Collection
    {
        $query = $this->buildReportBaseQuery($filters);

        if (! empty($filters['region_id'])) {
            $query->where('leads.region_id', $filters['region_id']);
        }
        if (! empty($filters['city_id'])) {
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

    public function getNowReport(array $filters, int $accountId): Collection
    {
        [$startDate, $endDate] = $this->parseDateRange($filters['date_range'] ?? null);

        $junkStatus = LeadStatuses::where('is_junk', 1)->first();
        $arrived = AppointmentStatuses::where('is_arrived', 1)->first();
        $pending = AppointmentStatuses::where('is_default', 1)->first();

        // Subquery: latest appointment id per (patient_id, service_id) pair.
        // The previous query selected appointments.* alongside an aggregate, which relied
        // on MariaDB non-strict GROUP BY to pick arbitrary row values. MariaDB rejects that
        // under ONLY_FULL_GROUP_BY. MAX(id) gives a deterministic latest row.
        $latestPerPair = DB::table('appointments')
            ->join('leads', 'leads.id', '=', 'appointments.lead_id')
            ->where('leads.lead_status_id', '!=', $junkStatus?->id ?? 0)
            ->where('appointments.base_appointment_status_id', Config::get('constants.appointment_status_not_show'))
            ->whereDate('appointments.created_at', '>=', $startDate)
            ->whereDate('appointments.created_at', '<=', $endDate)
            ->select('appointments.patient_id', 'appointments.service_id', DB::raw('MAX(appointments.id) as latest_id'))
            ->groupBy('appointments.patient_id', 'appointments.service_id');

        $appointments = DB::table('appointments')
            ->joinSub($latestPerPair, 'latest_per_pair', 'latest_per_pair.latest_id', '=', 'appointments.id')
            ->select('appointments.*')
            ->orderByDesc('appointments.created_at')
            ->get();

        $services = Services::where('account_id', $accountId)
            ->select('id', 'parent_id', 'slug', 'end_node')
            ->get()
            ->keyBy('id');

        return $appointments->filter(function ($appointment) use ($junkStatus, $arrived, $pending, $endDate, $services): bool {
            $rootService = LocationsWidget::findRoot($appointment->service_id, $services);

            $hasFollowUp = DB::table('leads')
                ->join('appointments', 'leads.id', '=', 'appointments.lead_id')
                ->where('leads.lead_status_id', '!=', $junkStatus?->id ?? 0)
                ->where('appointments.patient_id', $appointment->patient_id)
                ->whereIn('appointments.base_appointment_status_id', [$arrived?->id, $pending?->id])
                ->whereDate('appointments.created_at', '>', $endDate)
                ->exists();

            if (! $hasFollowUp) {
                return true;
            }

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

    protected function buildReportBaseQuery(array $filters, string $dateColumn = 'leads.created_at'): Builder
    {
        [$startDate, $endDate] = $this->parseDateRange($filters['date_range'] ?? null);

        return Leads::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', Config::get('constants.patient_id'))
            ->forBranches($this->allowedBranchIds())
            ->when($startDate, fn (Builder $q) => $q->whereDate($dateColumn, '>=', $startDate))
            ->when($endDate, fn (Builder $q) => $q->whereDate($dateColumn, '<=', $endDate));
    }

    protected function applyReportFilters(Builder $query, array $filters): void
    {
        // NOTE: cnic filter intentionally removed. The users.cnic column is now
        // encrypted at rest via App\Casts\EncryptedLegacy. SQL equality against
        // ciphertext returns 0 matches for any row that has been re-saved through
        // the cast. To restore exact-match search, add a blind-index column
        // (cnic_hash = HMAC-SHA256) and reintroduce the filter against that.
        $filterMap = [
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
            if (! empty($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }

        if (! empty($filters['email'])) {
            $query->where('users.email', 'like', '%'.$filters['email'].'%');
        }
        if (! empty($filters['phone'])) {
            $query->where('users.phone', 'like', '%'.PhoneFormattingService::cleanNumber($filters['phone']).'%');
        }
    }

    // =========================================================================
    // Cached Lookups
    // =========================================================================

    public function getDefaultLeadStatus(int $accountId): ?LeadStatuses
    {
        return Cache::remember("default_lead_status_{$accountId}", self::CACHE_TTL, fn (): ?LeadStatuses => LeadStatuses::where([
            'account_id' => $accountId,
            'is_default' => 1,
        ])->first());
    }

    public function getJunkLeadStatus(int $accountId): ?LeadStatuses
    {
        return Cache::remember("junk_lead_status_{$accountId}", self::CACHE_TTL, fn (): ?LeadStatuses => LeadStatuses::where([
            'account_id' => $accountId,
            'is_junk' => 1,
        ])->first());
    }

    public function getConvertedLeadStatus(int $accountId): ?LeadStatuses
    {
        return Cache::remember("converted_lead_status_{$accountId}", self::CACHE_TTL, fn (): ?LeadStatuses => LeadStatuses::where([
            'account_id' => $accountId,
            'is_converted' => 1,
        ])->first());
    }

    public function clearCache(): void
    {
        $accountId = Auth::user()->account_id;
        $userId = Auth::id();
        $keys = [
            "lead_form_lookup_{$accountId}",
            "lead_form_lookup_cities_user_{$userId}",
            "lead_filters_user_{$userId}",
            "default_lead_status_{$accountId}",
            "junk_lead_status_{$accountId}",
            "converted_lead_status_{$accountId}",
            "lead_import_lookup_{$accountId}",
        ];

        array_map(Cache::forget(...), $keys);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    protected function getRegionFromCity(int $cityId): ?int
    {
        return Cities::find($cityId)?->region_id;
    }

    protected function logLeadActivity(Leads $lead, array $data): void
    {
        $location = isset($data['location_id'])
            ? Locations::with('city')->find($data['location_id'])
            : ($lead->location_id ? Locations::with('city')->find($lead->location_id) : null);

        $service = isset($data['service_id'])
            ? Services::find($data['service_id'])
            : ($lead->service_id ? Services::find($lead->service_id) : null);

        ActivityLogger::logLeadCreated($lead, $location, $service);
    }

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

        if (hasFilter($filters, 'created_at')) {
            [$startDate, $endDate] = self::parseDateRangeForFilter($filters['created_at']);
            $where[] = ['leads.created_at', '>=', $startDate];
            $where[] = ['leads.created_at', '<=', $endDate];
            Filters::put($userId, $filename, 'created_at', $filters['created_at']);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'created_at');
        }

        return $where;
    }

    protected function applyFilter(
        array $where,
        array $filters,
        string $key,
        array $mapping,
        string $filename,
        int $userId,
        bool $applyFilter,
    ): array {
        $column = $mapping[0];
        $operator = $mapping[1];
        $prefix = $mapping[2] ?? '';
        $suffix = $mapping[3] ?? '';
        $cleanNumber = $mapping[4] ?? false;

        if (hasFilter($filters, $key)) {
            $value = $cleanNumber ? PhoneFormattingService::cleanNumber($filters[$key]) : $filters[$key];
            $where[] = [$column, $operator, $prefix.$value.$suffix];
            Filters::put($userId, $filename, $key, $value);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, $key);
        } else {
            $storedValue = Filters::get($userId, $filename, $key);
            if ($storedValue) {
                $value = $cleanNumber ? PhoneFormattingService::cleanNumber($storedValue) : $storedValue;
                $where[] = [$column, $operator, $prefix.$value.$suffix];
            }
        }

        return $where;
    }

    protected function buildServiceConditions(array $filters, string $filename, int $userId): array
    {
        $where = [];
        $applyFilter = checkFilters($filters, $filename);

        if (hasFilter($filters, 'service_id')) {
            $where[] = ['service_id', '=', $filters['service_id']];
            Filters::put($userId, $filename, 'service_id', $filters['service_id']);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'service_id');
        } else {
            $storedValue = Filters::get($userId, $filename, 'service_id');
            if ($storedValue) {
                $where[] = ['service_id', '=', $storedValue];
            }
        }

        return $where;
    }

    protected function getOrderParams(array $filters, string $filename, int $userId): array
    {
        if (isset($filters['sort'])) {
            $orderBy = $filters['sort']['field'] ?? 'leads.created_at';
            $order = $filters['sort']['sort'] ?? 'DESC';
        } else {
            $orderBy = Filters::get($userId, $filename, 'order_by') ?: 'leads.created_at';
            $order = Filters::get($userId, $filename, 'order') ?: 'desc';

            if ($orderBy === 'created_at') {
                $orderBy = 'leads.created_at';
            }
        }

        Filters::put($userId, $filename, 'order_by', $orderBy);
        Filters::put($userId, $filename, 'order', $order);

        return [$orderBy, $order];
    }
}
