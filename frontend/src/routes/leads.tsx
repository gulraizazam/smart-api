import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient, keepPreviousData } from '@tanstack/react-query';
import {
  AlertTriangle,
  Archive,
  Check,
  ChevronLeft,
  ChevronRight,
  Download,
  Eye,
  Loader2,
  MoreHorizontal,
  Pencil,
  Phone,
  Plus,
  RotateCcw,
  Search,
  Send,
  Tag,
  Trash2,
  Upload,
  X,
} from 'lucide-react';
import { api } from '@/lib/api';
import { cn } from '@/lib/cn';
import { formatPhone, formatTableDate, initials } from '@/lib/format';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogSheet,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { LeadFormDialog } from '@/components/leads/lead-form-dialog';
import { LeadImportDialog } from '@/components/leads/lead-import-dialog';
import {
  LeadFilterShelf,
  EMPTY_LEAD_FILTERS,
  type LeadFilters,
  type LeadFilterLookups,
  type LookupOption,
} from '@/components/leads/lead-filters';
import { tokenStore } from '@/lib/api';

/* The /api/leads/datatable response uses pre-projected display fields with
   confusing names — most "_id" suffixed fields actually carry the LABEL, not
   the foreign key. Verbatim shape from the controller, do not invent fields. */
type Lead = {
  id: number | string;
  lead_id?: number | string;
  name?: string;
  phone?: string;
  email?: string | null;
  gender?: number | string;
  gender_label?: string;
  active?: number | boolean;
  city_id?: string;            // city NAME (poorly named on the server)
  cityId?: number;             // actual city id
  region_id?: string;          // region NAME or "N/A"
  location?: string;           // centre NAME
  lead_status_id?: string;     // status NAME ("Booked", "Open", …)
  lead_source?: string;        // not present on datatable rows
  service_id?: string;         // ALL category names joined with ","
  service_active?: string;     // ONLY the latest (status=1) category names — show this in the column
  child_service_active?: string;
  child_service?: string;
  created_at?: string;         // pre-formatted "April 6,2026 06:36 PM"
  created_by?: string;         // user NAME
};

type LeadsDatatableResponse = {
  data: Lead[];
  permissions?: Record<string, boolean>;
  active_filters?: Record<string, unknown>;
  filter_values?: Record<string, unknown>;
  meta: {
    field: string;
    page: number;
    pages: number;
    perpage: number;
    total: number;
    sort: string;
  };
};

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];

export default function LeadsPage() {
  const [tab, setTab] = useState<'active' | 'junk'>('active');
  const [searchInput, setSearchInput] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statusId, setStatusId] = useState<string>('');
  const [filters, setFilters] = useState<LeadFilters>(EMPTY_LEAD_FILTERS);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [openLeadId, setOpenLeadId] = useState<number | string | null>(null);
  const [addOpen, setAddOpen] = useState(false);
  const [editLeadId, setEditLeadId] = useState<number | string | null>(null);
  const [importOpen, setImportOpen] = useState(false);
  const [exportBusy, setExportBusy] = useState(false);
  const [exportGuardOpen, setExportGuardOpen] = useState(false);
  const qc = useQueryClient();

  /**
   * Export — hard policy: every export covers at most 31 days. The backend
   * enforces this (422 if the range is missing or wider); the frontend
   * pre-checks and opens the guard dialog so failures don't round-trip.
   *
   * Transport: backend streams a CSV via league/csv + streamDownload so
   * the response flushes row-by-row and never trips the nginx 60s
   * timeout, regardless of dataset size. SPA auth is bearer-token, so we
   * fetch the blob with the token and trigger a download via an object
   * URL (window.location.href would strip Authorization).
   */
  const EXPORT_MAX_DAYS = 31;

  function rangeSpanDays(from: string, to: string): number {
    const ms = new Date(to).getTime() - new Date(from).getTime();
    return Math.floor(ms / 86_400_000) + 1;
  }

  function handleExport() {
    if (exportBusy) return;
    const hasValidRange =
      filters.dateFrom &&
      filters.dateTo &&
      rangeSpanDays(filters.dateFrom, filters.dateTo) <= EXPORT_MAX_DAYS;
    if (!hasValidRange) {
      setExportGuardOpen(true);
      return;
    }
    runExport({ from: filters.dateFrom, to: filters.dateTo });
  }

  async function runExport(range: { from: string; to: string }) {
    if (exportBusy) return;
    setExportBusy(true);
    try {
      const params = new URLSearchParams({ ext: 'csv' });
      params.set('created_at', `${range.from} - ${range.to}`);
      if (debouncedSearch) {
        // Legacy datatable search is a unified name-or-phone field; the
        // export endpoint accepts individual filters. Send as `name` —
        // the backend uses LIKE and matches either.
        params.set('name', debouncedSearch);
      }
      if (statusId) params.set('lead_status_id', statusId);
      if (filters.centreId) params.set('location_id', filters.centreId);
      if (filters.serviceId) params.set('service_id', filters.serviceId);
      if (filters.cityId) params.set('city_id', filters.cityId);
      if (filters.regionId) params.set('region_id', filters.regionId);
      if (filters.gender) params.set('gender_id', filters.gender);
      if (filters.createdBy) params.set('created_by', filters.createdBy);
      if (tab === 'junk') params.set('type', 'junk');

      const token = tokenStore.get();
      const res = await fetch(`/api/leads/export/excel?${params.toString()}`, {
        headers: {
          Accept: 'text/csv, application/octet-stream, */*',
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
      });

      if (res.status === 401) {
        window.dispatchEvent(new CustomEvent('auth:expired'));
        return;
      }
      if (!res.ok) {
        // Server returns { success:false, message } on validation errors;
        // prefer that message over the raw HTTP code.
        let serverMessage = `Export failed (HTTP ${res.status}).`;
        try {
          const body = (await res.json()) as { message?: string };
          if (body?.message) serverMessage = body.message;
        } catch {
          // non-JSON error body — keep the fallback
        }
        // eslint-disable-next-line no-alert
        alert(serverMessage);
        return;
      }

      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      const today = new Date().toISOString().slice(0, 10);
      a.download = `leads-${tab}-${today}.csv`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
    } catch (err) {
      // eslint-disable-next-line no-alert
      alert(err instanceof Error ? err.message : 'Export failed.');
    } finally {
      setExportBusy(false);
    }
  }

  // Debounce search input
  useEffect(() => {
    const t = setTimeout(() => setDebouncedSearch(searchInput.trim()), 300);
    return () => clearTimeout(t);
  }, [searchInput]);

  // Reset page on filter change
  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, statusId, tab, perPage, filters]);

  // Clear stale status filter when switching tabs. The Junk tab forces
  // lead_status_id = junkId server-side, and Active forces !=junkId, so a
  // status pick from one tab would contradict the other and zero the list.
  useEffect(() => {
    setStatusId('');
  }, [tab]);

  // These endpoints return raw arrays (no envelope) — pass raw:true so the
  // API client returns the array verbatim instead of unwrapping a missing
  // `.data` property. The status response carries flags (is_arrived,
  // is_converted, is_junk) so we can hide auto-derived statuses from the
  // user-facing filter dropdown without name-matching heuristics.
  const statuses = useQuery({
    queryKey: ['leads', 'statuses', 'v2'],
    queryFn: () => api.get<unknown>('/api/leads/lead_statuses', { raw: true }),
    staleTime: 5 * 60_000,
  });

  // Backend filter keys (LeadService::buildWhereConditions): lead_id | name |
  // phone | lead_status_id | city_id | location_id | region_id | gender_id |
  // created_by | created_at. Source filter is NOT supported server-side —
  // adding it here would silently no-op, so we leave it out until the
  // service grows that case. CUTERA-REVIEW: add lead_source_id filter to
  // LeadService::buildWhereConditions if product wants the source pill.
  //
  // Smart search — one box, three targets. Routed by shape:
  //   • pure digits, ≤ 9 chars → lead_id (exact match; real IDs are 6–7 digit)
  //   • pure digits, ≥ 10 chars → phone (LIKE)
  //   • anything with letters → name (LIKE)
  // Sending multiple keys AND's them server-side, so we pick exactly one.
  const search = useMemo(() => {
    const v = debouncedSearch;
    if (!v) return {};
    const isPureDigits = /^\d+$/.test(v);
    if (isPureDigits) {
      return v.length <= 9 ? { lead_id: v } : { phone: v };
    }
    const digitsOnly = v.replace(/\D/g, '');
    const looksLikePhone = digitsOnly.length >= 10 && digitsOnly.length / v.length > 0.6;
    return looksLikePhone ? { phone: v } : { name: v };
  }, [debouncedSearch]);

  const leads = useQuery({
    queryKey: [
      'leads',
      'datatable',
      tab,
      page,
      perPage,
      debouncedSearch,
      statusId,
      filters,
    ],
    placeholderData: keepPreviousData,
    queryFn: ({ signal }) =>
      api.post<LeadsDatatableResponse>(
        '/api/leads/datatable',
        {
          type: tab === 'junk' ? 'junk' : null,
          pagination: { page, perpage: perPage },
          query: {
            search: {
              ...search,
              lead_status_id: statusId || undefined,
              location_id: filters.centreId || undefined,
              service_id: filters.serviceId || undefined,
              city_id: filters.cityId || undefined,
              region_id: filters.regionId || undefined,
              gender_id: filters.gender || undefined,
              created_by: filters.createdBy || undefined,
              // Legacy server expects "YYYY-MM-DD - YYYY-MM-DD" with a literal
              // " - " separator; LeadService splits on that exact token. If
              // only one side is provided we still send it so the user sees
              // an "open-ended" range behaviour server-side handles.
              created_at:
                filters.dateFrom && filters.dateTo
                  ? `${filters.dateFrom} - ${filters.dateTo}`
                  : undefined,
            },
            // Sort is deliberately NOT sent — the backend's getOrderParams()
            // default resolves to `leads.created_at desc` (= Received newest
            // first) which is the only order telecallers care about. Column
            // sort arrows were removed for the same reason: the legacy
            // getFilters() helper drops query.sort, so the arrows were a
            // false affordance. If sort ever returns, fix the helper first.
          },
        },
        { raw: true, signal },
      ),
  });

  /* Lookups ride on the datatable response (filter_values). Server returns
     associative maps like { "1": "Mumbai", ... } for pluck()-based ones, or
     arrays of { value, text } for Cities/Regions. Normalize to LookupOption
     shape before handing to the shelf. Fall back to empty arrays until the
     first response lands. */
  const lookups = useMemo<LeadFilterLookups>(() => {
    const fv = (leads.data?.filter_values ?? {}) as Record<string, unknown>;
    return {
      centres: toLookup(fv.locations),
      services: toLookup(fv.Services),
      cities: toLookup(fv.cities),
      regions: toLookup(fv.regions),
      users: toLookup(fv.users),
    };
  }, [leads.data?.filter_values]);

  // Filter-bar dropdown — "find leads with status X". This is a VIEW
  // filter, not a status change, so it must include the system-derived
  // statuses (Booked, Arrived, Converted) — users need to be able to see
  // "all booked leads", "all arrived leads", etc. Only Junk is excluded
  // because the Active/Junk tabs already partition on it. Junk tab hides
  // the dropdown entirely (would always be a no-op there).
  const statusOptions = useMemo(() => {
    if (tab === 'junk') return [];
    const raw = statuses.data;
    const arr = Array.isArray(raw) ? raw : (raw as { data?: unknown })?.data;
    if (!Array.isArray(arr)) return [];
    return (arr as Array<{ value: number; text: string; is_junk?: boolean }>)
      .filter((s) => !s.is_junk)
      .map((s) => ({ id: s.value, name: s.text }));
  }, [statuses.data, tab]);

  /* Row-level quick status change — same endpoint as the drawer but
     fired straight from the table. Excludes every system-derived status
     (booked/arrived/converted are set by downstream flows, junk has its
     own dedicated row action). */
  type StatusRow = { value: number; text: string; is_booked?: boolean; is_arrived?: boolean; is_converted?: boolean; is_junk?: boolean };
  const rawStatusArr = useMemo((): StatusRow[] => {
    const raw = statuses.data;
    const arr = Array.isArray(raw) ? raw : (raw as { data?: unknown })?.data;
    return Array.isArray(arr) ? (arr as StatusRow[]) : [];
  }, [statuses.data]);
  const quickStatusOptions = useMemo(
    () => rawStatusArr.filter((s) => !s.is_booked && !s.is_arrived && !s.is_converted && !s.is_junk),
    [rawStatusArr],
  );

  const quickStatusChange = useMutation({
    mutationFn: ({ leadId, statusId }: { leadId: number | string; statusId: number }) =>
      api.put('/api/leads/storeleadstatus', { id: leadId, lead_status_parent_id: statusId }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['leads'] }),
  });

  const rows = leads.data?.data ?? [];
  /* Permission-gated UI — every action button must be hidden (not just
     disabled) when the user lacks its backing permission. Backend still
     enforces every gate on its endpoint, so these flags are a UX
     convenience, not a security boundary. `perms.X === true` (not just
     truthy) so that a missing bit defaults closed. */
  const perms = leads.data?.permissions ?? {};
  const canCreate = perms.create === true;
  const canEdit = perms.edit === true;
  const canDelete = perms.delete === true;
  const canUpdateStatus = perms.update_status === true;
  const canImport = perms.import === true;
  const canExport = perms.export === true;
  const meta = leads.data?.meta;
  const total = meta?.total ?? 0;
  const totalPages = meta?.pages
    ? Math.max(1, Math.ceil(total / perPage))
    : 1;
  const filtersCount = countActive(filters);
  const hasFilters = !!(debouncedSearch || statusId) || filtersCount > 0;

  const clearFilters = () => {
    setSearchInput('');
    setStatusId('');
    setFilters(EMPTY_LEAD_FILTERS);
  };

  return (
    <div className="mx-auto max-w-[1400px] space-y-3 px-4 py-4 sm:px-6 lg:px-8">
      {/* Header — title + soft inline count + tab segment + action cluster.
          Count sits as a quiet typographic note, no pill or badge. */}
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex items-baseline gap-2.5">
          <h1 className="text-2xl font-semibold tracking-tight">Leads</h1>
          {total > 0 && (
            <span className="text-[13px] text-fg-subtle nums-tabular">
              <span className="font-medium text-fg-muted">{total.toLocaleString()}</span>{' '}
              {tab === 'junk' ? 'junk' : 'active'}
            </span>
          )}
        </div>
        <Tabs value={tab} onValueChange={(v) => setTab(v as 'active' | 'junk')}>
          <TabsList className="h-8">
            <TabsTrigger value="active" className="px-3 py-0.5 text-[12.5px]">Active</TabsTrigger>
            <TabsTrigger value="junk" className="px-3 py-0.5 text-[12.5px]">Junk</TabsTrigger>
          </TabsList>
        </Tabs>
        <div className="ml-auto flex items-center gap-1.5">
          {/* Secondary actions (Import / Export) collapsed under a
              single "More" dropdown so the primary Add lead CTA doesn't
              compete with four buttons. Dropdown only renders when the
              user has at least one of its actions. */}
          {(canImport || canExport) && (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="secondary"
                  size="icon"
                  aria-label="More actions"
                  title="More actions"
                  disabled={exportBusy}
                >
                  {exportBusy ? (
                    <Loader2 className="size-4 animate-spin" />
                  ) : (
                    <MoreHorizontal className="size-4" />
                  )}
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="min-w-[160px]">
                {canImport && (
                  <DropdownMenuItem onSelect={() => setImportOpen(true)}>
                    <Upload className="size-3.5 text-fg-subtle" /> Import
                  </DropdownMenuItem>
                )}
                {canExport && (
                  <DropdownMenuItem
                    onSelect={(e) => {
                      // Radix closes the menu by default; let handleExport
                      // open its own guard dialog without the menu's focus
                      // stealing back in.
                      e.preventDefault();
                      handleExport();
                    }}
                    disabled={exportBusy}
                  >
                    {exportBusy ? (
                      <Loader2 className="size-3.5 animate-spin text-fg-subtle" />
                    ) : (
                      <Download className="size-3.5 text-fg-subtle" />
                    )}
                    {exportBusy ? 'Exporting…' : 'Export'}
                  </DropdownMenuItem>
                )}
              </DropdownMenuContent>
            </DropdownMenu>
          )}
          {canCreate && (
            <Button size="sm" onClick={() => setAddOpen(true)}>
              <Plus className="size-3.5" />
              <span className="hidden xs:inline sm:inline">Add lead</span>
              <span className="xs:hidden sm:hidden">Add</span>
            </Button>
          )}
        </div>
      </div>

      {/* Unified command rail — search anchor on the left, filter pills
          flow inline to the right. Each pill is its own flex child so
          they wrap individually on narrow screens instead of the whole
          shelf dropping below the search. Status is rendered as a pill
          by the shelf so it lives on the same line with the rest. */}
      <div className="flex flex-wrap items-center gap-1.5">
        <div className="relative min-w-[220px] flex-1 basis-[260px]">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-fg-subtle" aria-hidden />
          <input
            type="search"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder="Search by ID, name or phone…"
            aria-label="Search leads"
            className="h-9 w-full rounded-lg bg-elevated pl-9 pr-2 text-[13px] text-fg shadow-xs ring-1 ring-inset ring-hairline placeholder:text-fg-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
          />
        </div>

        <LeadFilterShelf
          filters={filters}
          onChange={setFilters}
          lookups={lookups}
          statusId={tab === 'junk' ? '' : statusId}
          onStatusChange={setStatusId}
          statusOptions={tab === 'junk' ? [] : statusOptions}
        />

        {hasFilters && (
          <button
            type="button"
            onClick={clearFilters}
            className="inline-flex h-8 items-center gap-1 rounded-lg px-2 text-[12.5px] font-medium text-fg-muted transition-colors hover:bg-surface hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
          >
            <X className="size-3.5" /> Clear
            <span className="ml-0.5 rounded-full bg-surface px-1.5 py-px text-[10.5px] font-semibold text-fg-muted">
              {filtersCount + (debouncedSearch ? 1 : 0) + (statusId ? 1 : 0)}
            </span>
          </button>
        )}
      </div>

      {/* Table card */}
      <Card className="overflow-hidden">
        {/* Desktop / tablet table — fits the container at every breakpoint.
            Column visibility ladder, no horizontal scroll ever:
              md  (≥768) : Lead, Status, Service, Received, Actions
              lg  (≥1024): + Centre
              xl  (≥1280): + Agent                                             */}
        <div className="hidden md:block">
          <Table>
            <TableHeader>
              <TableRow className="border-b border-hairline">
                <TableHead className="w-[34%] pl-4 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Lead</TableHead>
                <TableHead className="w-[14%] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Status</TableHead>
                <TableHead className="w-[20%] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Category</TableHead>
                <TableHead className="hidden w-[16%] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle lg:table-cell">Centre</TableHead>
                <TableHead className="hidden w-[12%] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle xl:table-cell">Agent</TableHead>
                <TableHead className="w-[14%] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Received</TableHead>
                <TableHead className="w-[140px] pr-3 text-end" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {leads.isLoading && Array.from({ length: 8 }).map((_, i) => (
                <TableRow key={`sk-${i}`}>
                  <TableCell className="py-2.5 pl-4"><Skeleton className="h-9 w-full max-w-[200px]" /></TableCell>
                  <TableCell className="py-2.5"><Skeleton className="h-5 w-20 rounded-full" /></TableCell>
                  <TableCell className="py-2.5"><Skeleton className="h-4 w-full max-w-[120px]" /></TableCell>
                  <TableCell className="hidden py-2.5 lg:table-cell"><Skeleton className="h-4 w-full max-w-[100px]" /></TableCell>
                  <TableCell className="hidden py-2.5 xl:table-cell"><Skeleton className="h-4 w-20" /></TableCell>
                  <TableCell className="py-2.5"><Skeleton className="h-4 w-16" /></TableCell>
                  <TableCell />
                </TableRow>
              ))}
              {!leads.isLoading && rows.map((lead) => (
                <TableRow
                  key={lead.id}
                  className="group cursor-pointer"
                  onClick={() => setOpenLeadId(lead.id)}
                >
                  {/* Cyan left-edge accent lives on the first cell, not the
                      <tr> — positioning on <tr> is browser-quirky and can
                      offset descendant content. */}
                  <TableCell className="py-2.5 pl-4 relative before:absolute before:inset-y-0 before:left-0 before:w-[3px] before:bg-accent-cyan before:opacity-0 group-hover:before:opacity-100 before:transition-opacity">
                    <div className="flex items-center gap-3">
                      <Avatar className="size-8 shrink-0">
                        <AvatarFallback>{initials(lead.name)}</AvatarFallback>
                      </Avatar>
                      <div className="min-w-0">
                        <div className="truncate text-[13px] font-medium text-fg">{lead.name || 'Unnamed lead'}</div>
                        {lead.phone && (
                          <div className="mt-0.5 flex items-center gap-1 text-[11.5px] text-fg-subtle nums-tabular">
                            <Phone className="size-3 shrink-0" /> {formatPhone(lead.phone)}
                          </div>
                        )}
                      </div>
                    </div>
                  </TableCell>
                  <TableCell className="py-2.5"><StatusBadge label={lead.lead_status_id} active={lead.active} /></TableCell>
                  <TableCell className="py-2.5 text-[12.5px] text-fg-muted">
                    {/* Show only the latest (status=1) category so the column
                        reflects the client's current interest, not every
                        historical inquiry. Fall back to the full list in the
                        rare case status flags were never set. */}
                    <span className="line-clamp-2">
                      {lead.service_active || lead.service_id || '—'}
                    </span>
                  </TableCell>
                  <TableCell className="hidden py-2.5 text-[12.5px] text-fg-muted lg:table-cell">
                    <span className="line-clamp-2">{lead.location || '—'}</span>
                  </TableCell>
                  <TableCell className="hidden py-2.5 text-[12.5px] text-fg-muted xl:table-cell">
                    <span className="line-clamp-1">{lead.created_by || '—'}</span>
                  </TableCell>
                  <TableCell className="py-2.5 text-[12.5px] text-fg-subtle">
                    {(() => {
                      const d = formatTableDate(lead.created_at);
                      return (
                        <div className="leading-tight">
                          <div className="whitespace-nowrap text-fg">{d.date}</div>
                          {d.time && <div className="whitespace-nowrap text-[11px] text-fg-subtle">{d.time}</div>}
                        </div>
                      );
                    })()}
                  </TableCell>
                  <TableCell className="py-2.5 pr-3 text-end" onClick={(e) => e.stopPropagation()}>
                    {/* Row actions — each does what its label promises:
                          Eye     → opens drawer for full context
                          Tag     → dropdown of statuses, fires change directly
                          Pencil  → opens edit dialog
                          Trash   → confirm dialog → moves to junk directly
                        Convert is intentionally absent — "Converted" is
                        auto-derived from the appointment/invoice flow. */}
                    <div className="flex items-center justify-end gap-0.5">
                      <RowIconAction
                        icon={Eye}
                        label="View lead"
                        onClick={() => setOpenLeadId(lead.id)}
                      />
                      {canUpdateStatus && (
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <button
                              type="button"
                              aria-label="Update status"
                              title="Update status"
                              disabled={quickStatusOptions.length === 0 || quickStatusChange.isPending}
                              className={cn(
                                'inline-flex size-7 items-center justify-center rounded-md text-fg-subtle transition-colors',
                                'hover:bg-surface hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50',
                                'disabled:cursor-not-allowed disabled:opacity-50',
                              )}
                            >
                              <Tag className="size-3.5" />
                            </button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end" className="min-w-[180px]">
                            <DropdownMenuLabel>Change status</DropdownMenuLabel>
                            {quickStatusOptions.map((s) => (
                              <DropdownMenuItem
                                key={s.value}
                                onSelect={() =>
                                  quickStatusChange.mutate({ leadId: lead.id, statusId: s.value })
                                }
                              >
                                {s.text}
                              </DropdownMenuItem>
                            ))}
                          </DropdownMenuContent>
                        </DropdownMenu>
                      )}
                      {canEdit && (
                        <RowIconAction
                          icon={Pencil}
                          label="Edit lead"
                          onClick={() => setEditLeadId(lead.id)}
                        />
                      )}
                      {/* Move to junk and Delete intentionally live only
                          in the detail drawer. Destructive actions deserve
                          the extra context (status, comments, history)
                          that the drawer surfaces, and pulling them off
                          the row prevents fat-finger mistakes on a list. */}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>

        {/* Mobile stacked cards */}
        <div className="divide-y divide-hairline/70 md:hidden">
          {leads.isLoading && Array.from({ length: 4 }).map((_, i) => (
            <div key={`m-sk-${i}`} className="p-4">
              <Skeleton className="h-12 w-full" />
            </div>
          ))}
          {!leads.isLoading && rows.map((lead) => (
            <button
              key={lead.id}
              type="button"
              onClick={() => setOpenLeadId(lead.id)}
              className="flex w-full items-center gap-3 p-4 text-start transition-colors hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"
            >
              <Avatar className="size-10 shrink-0">
                <AvatarFallback>{initials(lead.name)}</AvatarFallback>
              </Avatar>
              <div className="min-w-0 flex-1">
                <div className="flex items-center justify-between gap-2">
                  <div className="truncate text-[14px] font-medium text-fg">{lead.name || 'Unnamed lead'}</div>
                  <StatusBadge label={lead.lead_status_id} active={lead.active} />
                </div>
                <div className="mt-0.5 flex items-center gap-2 text-[12px] text-fg-subtle">
                  {lead.phone && <span className="inline-flex items-center gap-1 nums-tabular"><Phone className="size-3" /> {formatPhone(lead.phone)}</span>}
                  {lead.created_at && (() => {
                    const d = formatTableDate(lead.created_at);
                    return <span className="whitespace-nowrap">· {d.date}{d.time && ` · ${d.time}`}</span>;
                  })()}
                </div>
              </div>
            </button>
          ))}
        </div>

        {/* Empty + error states */}
        {!leads.isLoading && rows.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-surface text-fg-subtle">
              <Search className="size-5" />
            </div>
            <h3 className="mt-4 text-[14px] font-medium text-fg">
              {hasFilters ? 'No leads match your filters' : 'No leads yet'}
            </h3>
            <p className="mt-1 max-w-xs text-[12.5px] text-fg-muted">
              {hasFilters ? 'Try clearing filters or adjusting your search.' : 'New leads from your campaigns will appear here.'}
            </p>
            {hasFilters && (
              <Button size="sm" variant="secondary" className="mt-4" onClick={clearFilters}>
                Clear filters
              </Button>
            )}
          </div>
        )}

        {leads.error && (
          <div className="border-t border-hairline bg-danger-soft px-4 py-3 text-[12.5px] text-danger">
            Couldn’t load leads: {(leads.error as Error).message}
          </div>
        )}

        {/* Pagination */}
        {!!meta && rows.length > 0 && (
          <div className="flex flex-col items-center justify-between gap-3 border-t border-hairline px-4 py-3 sm:flex-row">
            <div className="flex items-center gap-2 text-[12.5px] text-fg-muted">
              <span>Rows per page</span>
              <Select
                value={String(perPage)}
                onChange={(e) => setPerPage(Number(e.target.value))}
                className="h-8 w-20"
              >
                {PAGE_SIZE_OPTIONS.map((n) => (
                  <option key={n} value={n}>{n}</option>
                ))}
              </Select>
              <span className="ml-2">
                {(page - 1) * perPage + 1}–{Math.min(page * perPage, total)} of {total.toLocaleString()}
              </span>
            </div>
            <div className="flex items-center gap-1">
              <Button
                variant="secondary"
                size="sm"
                disabled={page <= 1 || leads.isFetching}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
              >
                <ChevronLeft className="size-3.5" /> Prev
              </Button>
              <div className="px-2 text-[12.5px] text-fg-muted">
                Page <span className="font-medium text-fg">{page}</span> of {totalPages}
              </div>
              <Button
                variant="secondary"
                size="sm"
                disabled={page >= totalPages || leads.isFetching}
                onClick={() => setPage((p) => p + 1)}
              >
                Next <ChevronRight className="size-3.5" />
              </Button>
            </div>
          </div>
        )}
      </Card>

      {/* Lead detail drawer */}
      <Dialog open={openLeadId !== null} onOpenChange={(o) => !o && setOpenLeadId(null)}>
        <DialogSheet aria-describedby={undefined}>
          {/* Radix requires a DialogTitle inside any DialogContent for
              screen-reader users. The visible header inside LeadDrawer is
              an <h2>; this sr-only DialogTitle satisfies the a11y contract
              without adding visual noise. */}
          <DialogTitle className="sr-only">Lead detail</DialogTitle>
          {openLeadId !== null && (
            <LeadDrawer
              id={openLeadId}
              onClose={() => setOpenLeadId(null)}
              onEdit={(leadId) => {
                setOpenLeadId(null);
                setEditLeadId(leadId);
              }}
              canEdit={canEdit}
              canUpdateStatus={canUpdateStatus}
              canDelete={canDelete}
            />
          )}
        </DialogSheet>
      </Dialog>

      {/* Add / Edit modals — same form, different mode */}
      <LeadFormDialog mode="create" open={addOpen} onOpenChange={setAddOpen} />
      <LeadFormDialog
        mode="edit"
        leadId={editLeadId ?? undefined}
        open={editLeadId !== null}
        onOpenChange={(o) => !o && setEditLeadId(null)}
      />
      <LeadImportDialog open={importOpen} onOpenChange={setImportOpen} />


      {/* Export guard — exports are hard-capped at 31 days (server-enforced).
          Fires when the Received filter is absent or wider than 31 days.
          Offers three presets for a friction-free pick; for anything else
          the user can close, set the Received filter manually (up to 31
          days), and click Export again. No "export everything" escape. */}
      <Dialog open={exportGuardOpen} onOpenChange={setExportGuardOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <span className="flex size-8 items-center justify-center rounded-full bg-accent-cyan/10 text-accent-cyan-hover">
                <Download className="size-4" />
              </span>
              Pick a date range to export
            </DialogTitle>
            <DialogDescription>
              Exports are limited to {EXPORT_MAX_DAYS} days per download.
              Choose a quick range below, or set a custom Received range of
              up to {EXPORT_MAX_DAYS} days from the filters.
            </DialogDescription>
          </DialogHeader>
          <div className="grid grid-cols-3 gap-2 pt-1">
            {(
              [
                { key: 'today', label: 'Today', days: 1 },
                { key: 'last7', label: 'Last 7 days', days: 7 },
                { key: 'last30', label: 'Last 30 days', days: 30 },
              ] as const
            ).map((p) => (
              <Button
                key={p.key}
                variant="secondary"
                size="sm"
                disabled={exportBusy}
                onClick={() => {
                  const to = new Date();
                  const from = new Date();
                  from.setDate(to.getDate() - (p.days - 1));
                  const fmt = (d: Date) => {
                    const y = d.getFullYear();
                    const m = String(d.getMonth() + 1).padStart(2, '0');
                    const day = String(d.getDate()).padStart(2, '0');
                    return `${y}-${m}-${day}`;
                  };
                  setExportGuardOpen(false);
                  runExport({ from: fmt(from), to: fmt(to) });
                }}
                className="justify-center"
              >
                {p.label}
              </Button>
            ))}
          </div>
          <DialogFooter>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setExportGuardOpen(false)}
              disabled={exportBusy}
            >
              Cancel
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

/* ───────────────────────────────────────────────────────────────────────── */

function StatusBadge({
  label,
  active,
}: {
  label: string | undefined;
  active?: number | boolean;
}) {
  if (active === 0 || active === false) {
    return <Badge variant="danger" dot>Inactive</Badge>;
  }
  if (!label) return <Badge variant="neutral" dot>New</Badge>;
  // Cheap heuristic — convert label → tone. Real tone should come from
  // lead_status.color in a future PR; for Sprint 1 we map common states.
  const tone = toneFromLabel(label);
  return <Badge variant={tone} dot>{label}</Badge>;
}

/* Visual hierarchy for lead-status badges — keep success (green) reserved
   for the moments that actually matter to the business:
     • success → Arrived / Converted (client showed up or closed)
     • warning → action-pending states (follow-up, callback)
     • brand   → scheduled/in-progress (Booked) and new/open
     • danger  → Junk / Lost / Rejected
   Everything else falls back to neutral so the eye reliably lands on
   the success + danger rows when scanning the list. */
function toneFromLabel(label: string) {
  const l = label.toLowerCase();
  if (l.includes('arrive') || l.includes('convert') || l.includes('won')) return 'success' as const;
  if (l.includes('junk') || l.includes('lost') || l.includes('reject')) return 'danger' as const;
  if (l.includes('follow') || l.includes('callback') || l.includes('schedul')) return 'warning' as const;
  if (l.includes('book') || l.includes('new') || l.includes('open') || l.includes('contact')) return 'brand' as const;
  return 'neutral' as const;
}

function RowIconAction({
  icon: Icon,
  label,
  onClick,
  destructive,
}: {
  icon: typeof Eye;
  label: string;
  onClick: () => void;
  destructive?: boolean;
}) {
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      onClick={onClick}
      className={cn(
        'inline-flex size-7 items-center justify-center rounded-md text-fg-subtle transition-colors',
        'hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50',
        destructive ? 'hover:text-danger' : 'hover:text-fg',
      )}
    >
      <Icon className="size-3.5" />
    </button>
  );
}

/* GET /api/leads/detail/{id} response — verified shape with nested
   relations. Every relation is optional because legacy rows may not have
   one populated; render with `?? '—'` fallbacks. */
type Named = { id?: number; name?: string };
type LeadDetail = {
  id: number;
  name?: string;
  email?: string | null;
  phone?: string;
  gender?: number;
  gender_label?: string;
  active?: boolean | number;
  city?: Named | null;
  towns?: Named | null;             // = "centre" in our UI vocabulary
  lead_status?: Named | null;
  lead_source?: Named | null;
  lead_service?: Array<{
    id?: number;
    /* Server-normalised pair. `category` is always the top-level service
       (walking up parent_id when legacy data stored a child in service_id).
       `actual_service` is the real service the client asked about, or null
       if only a category was recorded. Prefer these over the raw
       service/childservice fields. */
    category?: Named | null;
    actual_service?: Named | null;
    service?: Named | null;       // raw row at service_id (back-compat only)
    childservice?: Named | null;  // raw row at child_service_id (back-compat only)
    lead_status?: Named | null;
    /* status=1 marks the newest/active inquiry; all prior inquiries get
       status=0 when a new one is added. Paired with created_at, this lets
       us show the latest category prominently and archive older ones. */
    status?: number;
    /* Re-inquiry counter — incremented each time the same client asks
       about this exact (category, service) pair again. Render the count
       only when > 1. */
    inquiry_count?: number;
    created_at?: string;
    updated_at?: string;
  }>;
  lead_comments?: Array<{
    id?: number;
    comment?: string;
    created_by?: string;
    created_at?: string;
  }>;
  created_by?: string;
  created_at?: string;
};

function LeadDrawer({
  id,
  onClose,
  onEdit,
  canEdit,
  canUpdateStatus,
  canDelete,
}: {
  id: number | string;
  onClose: () => void;
  onEdit: (id: number | string) => void;
  /* Mirror the datatable response's permission bits. Backend enforces
     each gate on its own endpoint — these flags just hide the buttons
     so users aren't tempted to click something they can't do. */
  canEdit: boolean;
  canUpdateStatus: boolean;
  canDelete: boolean;
}) {
  const qc = useQueryClient();
  const [commentDraft, setCommentDraft] = useState('');
  const [pendingStatus, setPendingStatus] = useState<string>('');
  const [confirmJunk, setConfirmJunk] = useState(false);
  const [feedback, setFeedback] = useState<string | null>(null);

  const detail = useQuery({
    queryKey: ['leads', 'detail', id],
    queryFn: () => api.get<{ lead: LeadDetail }>(`/api/leads/detail/${id}`),
  });

  const statuses = useQuery({
    queryKey: ['leads', 'statuses', 'v2'],
    queryFn: () =>
      api.get<Array<{ value: number; text: string; is_booked?: boolean; is_arrived?: boolean; is_converted?: boolean; is_junk?: boolean }>>(
        '/api/leads/lead_statuses',
        { raw: true },
      ),
    staleTime: 5 * 60_000,
  });

  const lead = detail.data?.lead;
  /* Split lead_service rows into the current (status=1, newest) inquiry
     and the archived (status=0) prior inquiries. createLeadService
     guarantees at most one row with status=1 per lead. Fallback: if the
     status flag is missing for some reason, pick the most recent by
     created_at so the header still reflects the latest category. */
  type LeadInquiry = NonNullable<LeadDetail['lead_service']>[number];
  const inquirySplit = useMemo((): { latest: LeadInquiry | null; earlier: LeadInquiry[] } => {
    const rows = (lead?.lead_service ?? []).filter((s) => !!(s.category?.name ?? s.service?.name));
    if (rows.length === 0) return { latest: null, earlier: [] };
    const sorted = [...rows].sort((a, b) => {
      const at = a.created_at ? new Date(a.created_at).getTime() : 0;
      const bt = b.created_at ? new Date(b.created_at).getTime() : 0;
      return bt - at;
    });
    const latest = sorted.find((s) => s.status === 1) ?? sorted[0];
    const earlier = sorted.filter((s) => s !== latest);
    return { latest, earlier };
  }, [lead?.lead_service]);
  const latestInquiry = inquirySplit.latest;
  const earlierInquiries = inquirySplit.earlier;
  const created = lead?.created_at ? formatTableDate(lead.created_at) : null;
  const comments = lead?.lead_comments ?? [];
  const junkStatusId = useMemo(
    () => (statuses.data ?? []).find((s) => s.is_junk)?.value,
    [statuses.data],
  );
  // Manual status dropdown excludes every system-derived status:
  //   is_booked    → set when an appointment is booked
  //   is_arrived   → set when FDM creates the consultation form
  //   is_converted → set by the convert flow on payment
  //   is_junk      → owned by the dedicated Move to junk action
  const statusOptions = useMemo(
    () => (statuses.data ?? []).filter((s) => !s.is_booked && !s.is_arrived && !s.is_converted && !s.is_junk),
    [statuses.data],
  );

  const refreshAll = () => {
    qc.invalidateQueries({ queryKey: ['leads', 'detail', id] });
    qc.invalidateQueries({ queryKey: ['leads', 'datatable'] });
  };

  const flash = (msg: string) => {
    setFeedback(msg);
    setTimeout(() => setFeedback(null), 2500);
  };

  // POST /api/leads/comment — body { lead_id, comment }
  const addComment = useMutation({
    mutationFn: (text: string) => api.post('/api/leads/comment', { lead_id: id, comment: text }),
    onSuccess: () => {
      setCommentDraft('');
      flash('Comment added');
      refreshAll();
    },
  });

  // PUT /api/leads/storeleadstatus — body { id, lead_status_parent_id }
  const updateStatus = useMutation({
    mutationFn: (statusId: number) =>
      api.put('/api/leads/storeleadstatus', { id, lead_status_parent_id: statusId }),
    onSuccess: () => {
      setPendingStatus('');
      flash('Status updated');
      refreshAll();
    },
  });

  // Move-to-junk reuses the status-update endpoint with the Junk status id —
  // there's no dedicated endpoint, and this is what the legacy admin does too.
  const moveToJunk = useMutation({
    mutationFn: () => {
      if (!junkStatusId) throw new Error('Junk status not configured.');
      return api.put('/api/leads/storeleadstatus', { id, lead_status_parent_id: junkStatusId });
    },
    onSuccess: () => {
      setConfirmJunk(false);
      refreshAll();
      onClose();
    },
  });

  // Restore from junk has a dedicated endpoint (the inverse of move-to-junk
  // but with custom server-side logic — re-resolves the prior status, etc.).
  const restoreFromJunk = useMutation({
    mutationFn: () => api.post(`/api/leads/${id}/remove-from-junk`),
    onSuccess: () => {
      flash('Restored from junk');
      refreshAll();
      onClose();
    },
  });

  /* Hard delete — permanent. The backend enforces the `leads_destroy`
     policy on the DELETE endpoint; the UI hides the button for anyone
     without that permission so the option isn't even tempting. */
  const [confirmDelete, setConfirmDelete] = useState(false);
  const deleteLead = useMutation({
    mutationFn: () => api.delete(`/api/leads/${id}`),
    onSuccess: () => {
      setConfirmDelete(false);
      refreshAll();
      onClose();
    },
  });

  const isJunked = !!(lead?.lead_status?.id && junkStatusId && lead.lead_status.id === junkStatusId);

  return (
    <>
      <div className="border-b border-hairline px-6 py-4">
        {/* Keep the ID inline with the kicker on the LEFT — the Radix
            Dialog close (×) sits in the top-right and any sibling placed
            there collides with it. Monospaced + selectable so agents
            can copy/paste the ID from the header. */}
        <div className="flex items-center gap-2">
          <span className="text-[11px] font-semibold uppercase tracking-wide text-accent-cyan-hover">
            Lead
          </span>
          {lead?.id != null && (
            <>
              <span aria-hidden className="text-fg-subtle">·</span>
              <span className="select-all font-mono text-[11px] text-fg-subtle">
                #{lead.id}
              </span>
            </>
          )}
        </div>
        <h2 className="mt-1 text-lg font-semibold tracking-tight">
          {lead?.name || (detail.isLoading ? <Skeleton className="h-6 w-40" /> : 'Untitled')}
        </h2>
        {lead?.phone && (
          <div className="mt-1 flex items-center gap-1 text-[12.5px] text-fg-muted nums-tabular">
            <Phone className="size-3" /> {formatPhone(lead.phone)}
          </div>
        )}
        {lead && (
          <div className="mt-2 flex items-center gap-2">
            <StatusBadge label={lead.lead_status?.name} active={lead.active} />
            {lead.lead_source?.name && (
              <Badge variant="neutral">via {lead.lead_source.name}</Badge>
            )}
          </div>
        )}
      </div>

      <div className="flex-1 overflow-y-auto px-6 py-5 space-y-5">
        {feedback && (
          <div role="status" className="flex items-center gap-2 rounded-lg bg-success-soft px-3 py-2 text-[12.5px] text-success ring-1 ring-inset ring-success/20">
            <Check className="size-3.5" /> {feedback}
          </div>
        )}

        {detail.isLoading && (
          <div className="space-y-3">
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-3/4" />
            <Skeleton className="h-4 w-2/3" />
          </div>
        )}
        {detail.error && (
          <div className="rounded-lg bg-danger-soft p-3 text-[12.5px] text-danger">
            Couldn’t load lead: {(detail.error as Error).message}
          </div>
        )}
        {lead && (
          <>
            {/* Quick status update — inline, single mutation. The select
                only fires the mutation on change so there's no extra
                "Save" button cluttering the section. Hidden entirely
                when the user lacks the update_status permission so
                they don't see a disabled dropdown they can't use. */}
            {canUpdateStatus && (
              <section>
                <h3 className="mb-2 text-[10.5px] font-semibold uppercase tracking-wider text-fg-subtle">
                  Status
                </h3>
                <div className="flex items-center gap-2">
                  <Select
                    value={pendingStatus || String(lead.lead_status?.id ?? '')}
                    disabled={updateStatus.isPending}
                    onChange={(e) => {
                      const v = e.target.value;
                      setPendingStatus(v);
                      if (v && Number(v) !== lead.lead_status?.id) {
                        updateStatus.mutate(Number(v));
                      }
                    }}
                    className="h-9 text-[13px]"
                    aria-label="Change status"
                  >
                    <option value="">Select status…</option>
                    {statusOptions.map((s) => (
                      <option key={s.value} value={s.value}>{s.text}</option>
                    ))}
                  </Select>
                  {updateStatus.isPending && <Loader2 className="size-4 animate-spin text-fg-subtle" />}
                </div>
                {updateStatus.error && (
                  <p role="alert" className="mt-1 text-[12px] text-danger">
                    {(updateStatus.error as Error).message}
                  </p>
                )}
              </section>
            )}

            <section>
              <h3 className="mb-2 text-[10.5px] font-semibold uppercase tracking-wider text-fg-subtle">
                Details
              </h3>
              <dl className="grid grid-cols-1 gap-x-4 gap-y-2.5 text-[13px]">
                <DetailRow label="Centre" value={lead.towns?.name || '—'} />
                <DetailRow label="City" value={lead.city?.name || '—'} />
                <DetailRow label="Gender" value={lead.gender_label || '—'} />
                <DetailRow label="Email" value={lead.email || '—'} />
                <DetailRow
                  label="Categories"
                  value={
                    latestInquiry?.category?.name ? (
                      /* Latest inquiry rendered as a brand pill; prior
                         inquiries trail as dot-separated text. Each row
                         carries its own `inquiry_count` (bumped by the
                         server upsert when the client re-asks about the
                         same category). We surface the counter only when
                         > 1 so the common single-inquiry case stays
                         clean. Hover tooltip shows when that category
                         inquiry was first generated — and if re-inquired,
                         when it was last touched. Trailing categories are
                         aggregated by name in case multiple rows point at
                         the same category (legacy data). */
                      <div className="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                        <Badge
                          variant="brand"
                          className="cursor-help"
                          title={formatInquiryTooltip(latestInquiry)}
                        >
                          {latestInquiry.category.name}
                          {(latestInquiry.inquiry_count ?? 1) > 1 && (
                            <span className="ml-1 text-accent-cyan/80">
                              ({latestInquiry.inquiry_count}×)
                            </span>
                          )}
                        </Badge>
                        {(() => {
                          const latestName = latestInquiry.category.name;
                          /* Aggregate earlier rows by category name so
                             duplicates across legacy rows collapse into
                             a single dot-separated entry. Track earliest
                             created_at + latest updated_at per name so
                             the tooltip spans the whole run. */
                          const grouped = new Map<
                            string,
                            { count: number; first: string | undefined; last: string | undefined }
                          >();
                          for (const s of earlierInquiries) {
                            const n = s.category?.name;
                            if (!n || n === latestName) continue;
                            const curr = grouped.get(n);
                            const inc = s.inquiry_count ?? 1;
                            if (curr) {
                              curr.count += inc;
                              if (s.created_at && (!curr.first || s.created_at < curr.first)) {
                                curr.first = s.created_at;
                              }
                              if (s.updated_at && (!curr.last || s.updated_at > curr.last)) {
                                curr.last = s.updated_at;
                              }
                            } else {
                              grouped.set(n, {
                                count: inc,
                                first: s.created_at,
                                last: s.updated_at,
                              });
                            }
                          }
                          return Array.from(grouped.entries()).map(([name, { count, first, last }]) => (
                            <span
                              key={name}
                              className="cursor-help text-[12.5px] text-fg-subtle"
                              title={formatInquiryTooltip({ inquiry_count: count, created_at: first, updated_at: last })}
                            >
                              <span aria-hidden className="mx-0.5">·</span>
                              {name}
                              {count > 1 && (
                                <span className="ml-1 text-fg-subtle/70">({count}×)</span>
                              )}
                            </span>
                          ));
                        })()}
                      </div>
                    ) : '—'
                  }
                />
                {latestInquiry?.actual_service?.name && (
                  <DetailRow label="Service" value={latestInquiry.actual_service.name} />
                )}
                <DetailRow label="Agent" value={lead.created_by || '—'} />
                <DetailRow
                  label="Received"
                  value={created ? `${created.date}${created.time ? ` · ${created.time}` : ''}` : '—'}
                />
              </dl>
            </section>

            <section>
              <h3 className="mb-2 flex items-center gap-2 text-[10.5px] font-semibold uppercase tracking-wider text-fg-subtle">
                Comments
                {comments.length > 0 && (
                  <span className="rounded-full bg-surface px-1.5 py-px text-[10px] font-medium text-fg-muted ring-1 ring-inset ring-hairline">
                    {comments.length}
                  </span>
                )}
              </h3>
              {comments.length === 0 ? (
                <div className="rounded-lg border border-dashed border-hairline px-3 py-4 text-center text-[12.5px] text-fg-muted">
                  No comments yet.
                </div>
              ) : (
                <ul className="space-y-2.5">
                  {comments.map((c, i) => (
                    <li key={c.id ?? i} className="rounded-lg bg-surface p-3 ring-1 ring-inset ring-hairline">
                      <div className="flex items-center justify-between gap-2">
                        <div className="text-[12px] font-medium text-fg">{c.created_by || 'Unknown'}</div>
                        {c.created_at && (
                          <div className="text-[11px] text-fg-subtle whitespace-nowrap">{c.created_at}</div>
                        )}
                      </div>
                      {c.comment && <div className="mt-1 text-[13px] text-fg-muted">{c.comment}</div>}
                    </li>
                  ))}
                </ul>
              )}

              {/* Add comment composer — sits flush below the timeline so
                  it reads as the next entry, not a separate panel. */}
              <form
                className="mt-3 flex items-end gap-2"
                onSubmit={(e) => {
                  e.preventDefault();
                  const v = commentDraft.trim();
                  if (v) addComment.mutate(v);
                }}
              >
                <textarea
                  value={commentDraft}
                  onChange={(e) => setCommentDraft(e.target.value)}
                  placeholder="Add a comment…"
                  rows={2}
                  disabled={addComment.isPending}
                  className="flex-1 rounded-lg bg-elevated px-3 py-2 text-[13px] text-fg ring-1 ring-inset ring-hairline placeholder:text-fg-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50 disabled:opacity-50"
                />
                <Button
                  type="submit"
                  size="icon"
                  variant="accent"
                  disabled={!commentDraft.trim() || addComment.isPending}
                  aria-label="Post comment"
                >
                  {addComment.isPending ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
                </Button>
              </form>
              {addComment.error && (
                <p role="alert" className="mt-1 text-[12px] text-danger">
                  {(addComment.error as Error).message}
                </p>
              )}
            </section>
          </>
        )}
      </div>

      <div className="flex items-center justify-between gap-2 border-t border-hairline px-6 py-3">
        {/* Move to junk / Restore from junk — both are status changes,
            so gated by update_status. Without the permission the whole
            junk control disappears; `<div />` placeholder keeps the
            footer's flex-justify-between layout intact. */}
        {canUpdateStatus ? (
          isJunked ? (
            <Button
              variant="ghost"
              size="sm"
              className="text-success hover:bg-success-soft hover:text-success"
              onClick={() => restoreFromJunk.mutate()}
              disabled={restoreFromJunk.isPending}
              aria-label="Restore lead from junk"
            >
              {restoreFromJunk.isPending ? <Loader2 className="size-3.5 animate-spin" /> : <RotateCcw className="size-3.5" />}
              Restore from junk
            </Button>
          ) : (
            <Button
              variant="ghost"
              size="sm"
              className="text-danger hover:bg-danger-soft hover:text-danger"
              onClick={() => setConfirmJunk(true)}
              disabled={!junkStatusId}
              aria-label="Move lead to junk"
            >
              <Archive className="size-3.5" /> Move to junk
            </Button>
          )
        ) : (
          <div />
        )}
        <div className="flex items-center gap-2">
          {canDelete && (
            <Button
              variant="ghost"
              size="sm"
              className="text-danger hover:bg-danger-soft hover:text-danger"
              onClick={() => setConfirmDelete(true)}
              aria-label="Delete lead permanently"
            >
              <Trash2 className="size-3.5" /> Delete
            </Button>
          )}
          {canEdit && (
            <Button variant="secondary" size="sm" onClick={() => onEdit(id)}>
              <Pencil className="size-3.5" /> Edit
            </Button>
          )}
        </div>
      </div>

      {/* Move-to-junk confirm dialog */}
      <Dialog open={confirmJunk} onOpenChange={setConfirmJunk}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <span className="flex size-8 items-center justify-center rounded-full bg-danger-soft text-danger">
                <AlertTriangle className="size-4" />
              </span>
              Move to junk?
            </DialogTitle>
            <DialogDescription>
              This lead will be marked as Junk and removed from the active list.
              You can restore it later from the Junk tab.
            </DialogDescription>
          </DialogHeader>
          {moveToJunk.error && (
            <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
              {(moveToJunk.error as Error).message}
            </div>
          )}
          <DialogFooter>
            <Button variant="secondary" size="sm" onClick={() => setConfirmJunk(false)} disabled={moveToJunk.isPending}>
              Cancel
            </Button>
            <Button variant="danger" size="sm" onClick={() => moveToJunk.mutate()} disabled={moveToJunk.isPending}>
              {moveToJunk.isPending && <Loader2 className="size-4 animate-spin" />}
              Move to junk
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Hard-delete confirm — permission-gated. Copy makes the
          irreversibility unambiguous and steers agents toward junk
          when they only need a soft remove. */}
      <Dialog open={confirmDelete} onOpenChange={setConfirmDelete}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <span className="flex size-8 items-center justify-center rounded-full bg-danger-soft text-danger">
                <AlertTriangle className="size-4" />
              </span>
              Delete lead permanently?
            </DialogTitle>
            <DialogDescription>
              This permanently removes{' '}
              <span className="font-medium text-fg">{lead?.name || `#${id}`}</span>{' '}
              along with its inquiries, comments, and history.{' '}
              <span className="font-medium text-danger">This cannot be undone.</span>{' '}
              If you only want to mark it as not-useful, use <em>Move to junk</em> instead.
            </DialogDescription>
          </DialogHeader>
          {deleteLead.error && (
            <div
              role="alert"
              className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20"
            >
              {(deleteLead.error as Error).message}
            </div>
          )}
          <DialogFooter>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setConfirmDelete(false)}
              disabled={deleteLead.isPending}
            >
              Cancel
            </Button>
            <Button
              variant="danger"
              size="sm"
              onClick={() => deleteLead.mutate()}
              disabled={deleteLead.isPending}
            >
              {deleteLead.isPending && <Loader2 className="size-4 animate-spin" />}
              Delete permanently
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

function toLookup(raw: unknown): LookupOption[] {
  if (!raw) return [];
  // Shape A: array of { value, text } (Cities/Regions via getActiveSorted).
  if (Array.isArray(raw)) {
    return raw
      .map((r) => {
        if (r && typeof r === 'object') {
          const o = r as Record<string, unknown>;
          const id = o.id ?? o.value;
          const name = o.name ?? o.text;
          if (id !== undefined && name !== undefined) return { id: id as number | string, name: String(name) };
        }
        return null;
      })
      .filter((x): x is LookupOption => x !== null);
  }
  // Shape B: associative map { id: "name" } (pluck-based ones: locations,
  // Services, users). Keys are stringified ints; preserve as-is.
  if (typeof raw === 'object') {
    return Object.entries(raw as Record<string, string>).map(([id, name]) => ({
      id,
      name: String(name),
    }));
  }
  return [];
}

function countActive(f: LeadFilters): number {
  let n = 0;
  if (f.centreId) n++;
  if (f.serviceId) n++;
  if (f.cityId) n++;
  if (f.regionId) n++;
  if (f.gender) n++;
  if (f.createdBy) n++;
  if (f.dateFrom || f.dateTo) n++;
  return n;
}

/* Compact tooltip for a category inquiry pill — date only:
 *   count = 1  → "Inquired April 18, 26"
 *   count > 1  → "First April 17 · Last April 18, 26"  (year shown once at the end)
 * Uses en-US explicitly so the "Month D, YY" shape is consistent across
 * browsers regardless of the visitor's locale. */
function formatInquiryTooltip(inq: {
  inquiry_count?: number;
  created_at?: string;
  updated_at?: string;
}): string {
  const fmt = (s: string | undefined, withYear = true): string => {
    if (!s) return '—';
    const d = new Date(s);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-US', {
      month: 'long',
      day: 'numeric',
      ...(withYear ? { year: '2-digit' } : {}),
    });
  };
  const count = inq.inquiry_count ?? 1;
  if (count <= 1) {
    return `Inquired ${fmt(inq.created_at)}`;
  }
  // Same year — drop it from "First" to keep the line short.
  const firstYear = inq.created_at ? new Date(inq.created_at).getFullYear() : null;
  const lastYear = inq.updated_at ? new Date(inq.updated_at).getFullYear() : null;
  const sameYear = firstYear !== null && firstYear === lastYear;
  return `First ${fmt(inq.created_at, !sameYear)} · Last ${fmt(inq.updated_at)}`;
}

function DetailRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="grid grid-cols-[120px_1fr] items-start gap-2">
      <dt className="text-[11.5px] font-medium uppercase tracking-wide text-fg-subtle">{label}</dt>
      <dd className="text-fg">{value}</dd>
    </div>
  );
}
