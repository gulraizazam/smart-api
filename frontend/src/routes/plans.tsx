import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient, keepPreviousData } from '@tanstack/react-query';
import {
  AlertTriangle,
  CalendarRange,
  ChevronLeft,
  ChevronRight,
  Eye,
  Loader2,
  MapPin,
  Power,
  PowerOff,
  Search,
  Tag,
  Trash2,
  X,
} from 'lucide-react';
import { FilterPill, RadioPicker } from '@/components/filters/filter-pill';
import { api } from '@/lib/api';
import { cn } from '@/lib/cn';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
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
  DialogTitle,
} from '@/components/ui/dialog';
import { PlanDetailDialog } from '@/components/plans/plan-detail-dialog';
import { PlanCreateDialog } from '@/components/plans/plan-create-dialog';
import { PlanMembershipCreateDialog } from '@/components/plans/plan-membership-create-dialog';
import { ChevronDown, Package, Pencil, Plus, Ticket } from 'lucide-react';

/**
 * PlanDatatableResource row shape. Numbers have both formatted strings
 * ("19,498") and raw values ("19498.5"); the SPA prefers raw when doing
 * math and formatted when rendering.
 *
 * Server filter keys (see PlanDatatableRequest + PlanService::addPatientFilter):
 *   patient_name   — LIKE match on patient name (supports "name - phone")
 *   package_id     — exact plan-id match
 *   location_id    — centre
 *   plan_type      — plan | bundle | membership  (applied at PlanService:1117)
 *   status         — 0 | 1
 *   created_at     — "YYYY-MM-DD - YYYY-MM-DD" range string
 */
type PlanRow = {
  id: number;
  patient_id: number;
  name: string;
  package_id: string;
  plan_name: string;
  location_id: string;
  location_name: string;
  city_name: string;
  session_count: number;
  total: string;
  total_raw: number;
  cash_receive: string;
  cash_receive_raw: number;
  settle_amount: string;
  settle_amount_raw: number;
  refunded: string;
  balance: string;
  active: boolean;
  status: 'Active' | 'Inactive';
  date: string;
  created_at: string;
  membership_info: string | null;
  plan_type: 'plan' | 'bundle' | 'membership';
};

type DatatableResponse = {
  data: PlanRow[];
  permissions?: {
    edit?: boolean;
    delete?: boolean;
    active?: boolean;
    inactive?: boolean;
    create?: boolean;
    log?: boolean;
    sms_log?: boolean;
    plans_cash_edit?: boolean;
    plans_cash_edit_payment_mode?: boolean;
    plans_cash_edit_amount?: boolean;
    plans_cash_edit_date?: boolean;
    plans_edit_sold_by?: boolean;
  };
  filter_values?: {
    locations?: Record<string, string>;
  };
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

export default function PlansPage() {
  const qc = useQueryClient();
  const [searchInput, setSearchInput] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [typeFilter, setTypeFilter] = useState<string>('');
  const [locationFilter, setLocationFilter] = useState<string>('');
  const [createdFrom, setCreatedFrom] = useState<string>('');
  const [createdTo, setCreatedTo] = useState<string>('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [detailId, setDetailId] = useState<number | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<PlanRow | null>(null);
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [bulkOpen, setBulkOpen] = useState(false);
  const [createSubtype, setCreateSubtype] = useState<'plan' | 'bundle' | 'membership' | null>(null);
  const [editPackageId, setEditPackageId] = useState<number | null>(null);
  const [newMenuOpen, setNewMenuOpen] = useState(false);

  const createdAt = createdFrom && createdTo ? `${createdFrom} - ${createdTo}` : '';

  useEffect(() => {
    const t = setTimeout(() => setDebouncedSearch(searchInput.trim()), 300);
    return () => clearTimeout(t);
  }, [searchInput]);

  useEffect(() => {
    setPage(1);
    setSelected(new Set());
  }, [debouncedSearch, statusFilter, typeFilter, locationFilter, createdAt, perPage]);

  const list = useQuery({
    queryKey: ['plans', 'global', 'datatable', page, perPage, debouncedSearch, statusFilter, typeFilter, locationFilter, createdAt],
    placeholderData: keepPreviousData,
    queryFn: () =>
      api.post<DatatableResponse>(
        '/api/plans-optimized/global/datatable',
        {
          pagination: { page, perpage: perPage },
          query: {
            search: {
              // Server accepts patient_name (LIKE) and package_id (exact).
              // If the user typed a numeric string, search both.
              patient_name: debouncedSearch && !/^\d+$/.test(debouncedSearch) ? debouncedSearch : undefined,
              package_id: debouncedSearch && /^\d+$/.test(debouncedSearch) ? debouncedSearch : undefined,
              status: statusFilter || undefined,
              plan_type: typeFilter || undefined,
              location_id: locationFilter || undefined,
              created_at: createdAt || undefined,
            },
          },
        },
        { raw: true },
      ),
  });

  const toggleStatus = useMutation({
    mutationFn: ({ id, active }: { id: number; active: 0 | 1 }) =>
      api.post('/api/plans/status', { id, status: active }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['plans'] }),
  });

  const deleteRow = useMutation({
    mutationFn: (id: number) => api.delete(`/api/plans/destroy/${id}`),
    onSuccess: () => {
      setDeleteTarget(null);
      qc.invalidateQueries({ queryKey: ['plans'] });
    },
  });

  const bulkDelete = useMutation({
    mutationFn: (ids: number[]) =>
      api.post<{ success: boolean; message: string }>(
        '/api/plans-optimized/global/datatable',
        { delete: ids.join(',') },
        { raw: true },
      ),
    onSuccess: () => {
      setBulkOpen(false);
      setSelected(new Set());
      qc.invalidateQueries({ queryKey: ['plans'] });
    },
  });

  const rows = list.data?.data ?? [];
  const meta = list.data?.meta;
  const total = meta?.total ?? 0;
  const totalPages = Math.max(1, Math.ceil(total / perPage));
  const perms = list.data?.permissions ?? {};
  const locationOptions = list.data?.filter_values?.locations ?? {};
  const hasFilters = !!(debouncedSearch || statusFilter || typeFilter || locationFilter || createdAt);

  const visibleIds = useMemo(() => rows.map((r) => r.id), [rows]);
  const allSelectedOnPage = visibleIds.length > 0 && visibleIds.every((id) => selected.has(id));
  const someSelectedOnPage = visibleIds.some((id) => selected.has(id));

  const togglePageSelection = () => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (allSelectedOnPage) {
        visibleIds.forEach((id) => next.delete(id));
      } else {
        visibleIds.forEach((id) => next.add(id));
      }
      return next;
    });
  };

  const toggleRowSelection = (id: number) => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const clearFilters = () => {
    setSearchInput('');
    setStatusFilter('');
    setTypeFilter('');
    setLocationFilter('');
    setCreatedFrom('');
    setCreatedTo('');
  };

  const typeTone = (t: PlanRow['plan_type']) =>
    t === 'membership' ? 'brand' : t === 'bundle' ? 'warning' : 'neutral';

  return (
    <div className="mx-auto max-w-[1400px] space-y-3 px-4 py-4 sm:px-6 lg:px-8">
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex items-baseline gap-2.5">
          <h1 className="text-2xl font-semibold tracking-tight">Plans</h1>
          {total > 0 && (
            <span className="text-[13px] text-fg-subtle nums-tabular">
              <span className="font-medium text-fg-muted">{total.toLocaleString()}</span> total
            </span>
          )}
        </div>
        <div className="ml-auto flex items-center gap-2">
          {selected.size > 0 && perms.delete !== false && (
            <>
              <span className="text-[12.5px] text-fg-muted">{selected.size} selected</span>
              <Button size="sm" variant="danger" onClick={() => setBulkOpen(true)}>
                <Trash2 className="size-3.5" /> Delete selected
              </Button>
            </>
          )}
          {perms.create !== false && (
            <div className="relative">
              <Button size="sm" onClick={() => setNewMenuOpen((v) => !v)}>
                <Plus className="size-3.5" /> New
                <ChevronDown className="size-3.5" />
              </Button>
              {newMenuOpen && (
                <>
                  {/* Backdrop to close */}
                  <div
                    className="fixed inset-0 z-10"
                    onClick={() => setNewMenuOpen(false)}
                  />
                  <div className="absolute right-0 z-20 mt-1 w-[180px] rounded-lg bg-elevated p-1 shadow-lg ring-1 ring-inset ring-hairline">
                    <NewMenuItem
                      icon={Plus}
                      label="Plan (services)"
                      onClick={() => {
                        setNewMenuOpen(false);
                        setCreateSubtype('plan');
                      }}
                    />
                    <NewMenuItem
                      icon={Package}
                      label="Bundle"
                      onClick={() => {
                        setNewMenuOpen(false);
                        setCreateSubtype('bundle');
                      }}
                    />
                    <NewMenuItem
                      icon={Ticket}
                      label="Membership"
                      onClick={() => {
                        setNewMenuOpen(false);
                        setCreateSubtype('membership');
                      }}
                    />
                  </div>
                </>
              )}
            </div>
          )}
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-1.5">
        <div className="relative min-w-[220px] flex-1 basis-[260px]">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-fg-subtle" aria-hidden />
          <input
            type="search"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder="Search by patient name or plan ID…"
            aria-label="Search plans"
            className="h-9 w-full rounded-lg bg-elevated pl-9 pr-2 text-[13px] text-fg shadow-xs ring-1 ring-inset ring-hairline placeholder:text-fg-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
          />
        </div>
        <FilterPill
          icon={Tag}
          label="Type"
          value={typeFilter || undefined}
          onClear={() => setTypeFilter('')}
          popover={
            <RadioPicker
              title="Type"
              options={[
                { id: 'plan', name: 'Plan' },
                { id: 'bundle', name: 'Bundle' },
                { id: 'membership', name: 'Membership' },
              ]}
              value={typeFilter}
              onChange={setTypeFilter}
            />
          }
        />
        <FilterPill
          icon={Tag}
          label="Status"
          value={statusFilter === '1' ? 'Active' : statusFilter === '0' ? 'Inactive' : undefined}
          onClear={() => setStatusFilter('')}
          popover={
            <RadioPicker
              title="Status"
              options={[
                { id: '1', name: 'Active' },
                { id: '0', name: 'Inactive' },
              ]}
              value={statusFilter}
              onChange={setStatusFilter}
            />
          }
        />
        {Object.keys(locationOptions).length > 0 && (
          <FilterPill
            icon={MapPin}
            label="Centre"
            value={locationOptions[locationFilter] ?? undefined}
            onClear={() => setLocationFilter('')}
            popoverWidth="min-w-[280px]"
            popover={
              <RadioPicker
                title="Centre"
                options={Object.entries(locationOptions).map(([id, name]) => ({ id, name }))}
                value={locationFilter}
                onChange={setLocationFilter}
              />
            }
          />
        )}
        <FilterPill
          icon={CalendarRange}
          label="Created"
          value={createdAt || undefined}
          onClear={() => {
            setCreatedFrom('');
            setCreatedTo('');
          }}
          popoverWidth="min-w-[280px]"
          popover={
            <div className="p-3">
              <div className="mb-2 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Created between</div>
              <div className="flex items-center gap-2">
                <input
                  type="date"
                  value={createdFrom}
                  onChange={(e) => setCreatedFrom(e.target.value)}
                  className="h-8 flex-1 rounded-md bg-elevated px-2 text-[12.5px] ring-1 ring-inset ring-hairline focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
                  aria-label="From date"
                />
                <span className="text-fg-subtle">→</span>
                <input
                  type="date"
                  value={createdTo}
                  onChange={(e) => setCreatedTo(e.target.value)}
                  className="h-8 flex-1 rounded-md bg-elevated px-2 text-[12.5px] ring-1 ring-inset ring-hairline focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
                  aria-label="To date"
                />
              </div>
            </div>
          }
        />
        {hasFilters && (
          <button
            type="button"
            onClick={clearFilters}
            className="inline-flex h-8 items-center gap-1 rounded-lg px-2 text-[12.5px] font-medium text-fg-muted transition-colors hover:bg-surface hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
          >
            <X className="size-3.5" /> Clear
          </button>
        )}
      </div>

      <Card className="overflow-hidden">
        <div className="hidden md:block">
          <Table>
            <TableHeader>
              <TableRow className="border-b border-hairline">
                {perms.delete !== false && (
                  <TableHead className="w-[44px] pl-4">
                    <input
                      type="checkbox"
                      aria-label="Select all on page"
                      checked={allSelectedOnPage}
                      ref={(el) => {
                        if (el) el.indeterminate = !allSelectedOnPage && someSelectedOnPage;
                      }}
                      onChange={togglePageSelection}
                      className="size-3.5 rounded border-hairline text-brand-blue focus:ring-2 focus:ring-accent-cyan/50"
                    />
                  </TableHead>
                )}
                <TableHead className="w-[100px] pl-4 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Plan #</TableHead>
                <TableHead className="text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Patient & plan</TableHead>
                <TableHead className="hidden w-[160px] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle xl:table-cell">Centre</TableHead>
                <TableHead className="w-[110px] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Type</TableHead>
                <TableHead className="w-[110px] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle text-right">Total</TableHead>
                <TableHead className="hidden w-[100px] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle text-right lg:table-cell">Balance</TableHead>
                <TableHead className="w-[110px] pl-2 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Status</TableHead>
                <TableHead className="w-[130px] pr-3 text-end" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {list.isLoading && Array.from({ length: 6 }).map((_, i) => (
                <TableRow key={`sk-${i}`}>
                  {perms.delete !== false && <TableCell className="pl-4"><Skeleton className="size-3.5" /></TableCell>}
                  <TableCell className="py-2.5 pl-4"><Skeleton className="h-4 w-16" /></TableCell>
                  <TableCell className="py-2.5"><Skeleton className="h-4 w-full max-w-[280px]" /></TableCell>
                  <TableCell className="hidden py-2.5 xl:table-cell"><Skeleton className="h-4 w-24" /></TableCell>
                  <TableCell className="py-2.5"><Skeleton className="h-5 w-16 rounded-full" /></TableCell>
                  <TableCell className="py-2.5"><Skeleton className="ml-auto h-4 w-20" /></TableCell>
                  <TableCell className="hidden py-2.5 lg:table-cell"><Skeleton className="ml-auto h-4 w-16" /></TableCell>
                  <TableCell className="py-2.5"><Skeleton className="h-5 w-20 rounded-full" /></TableCell>
                  <TableCell />
                </TableRow>
              ))}
              {!list.isLoading && rows.map((row) => {
                const balanceNum = Number(String(row.balance).replace(/,/g, ''));
                const isSelected = selected.has(row.id);
                return (
                  <TableRow
                    key={row.id}
                    className={cn('group cursor-pointer', isSelected && 'bg-surface')}
                    onClick={() => setDetailId(row.id)}
                  >
                    {perms.delete !== false && (
                      <TableCell className="pl-4" onClick={(e) => e.stopPropagation()}>
                        <input
                          type="checkbox"
                          aria-label={`Select plan #${row.package_id}`}
                          checked={isSelected}
                          onChange={() => toggleRowSelection(row.id)}
                          className="size-3.5 rounded border-hairline text-brand-blue focus:ring-2 focus:ring-accent-cyan/50"
                        />
                      </TableCell>
                    )}
                    <TableCell className="py-2.5 pl-4 nums-tabular text-[12.5px] text-fg-muted">
                      #{row.package_id}
                    </TableCell>
                    <TableCell className="py-2.5">
                      <div className="min-w-0">
                        <div className="truncate text-[13px] font-medium text-fg">{row.name}</div>
                        <div className="mt-0.5 truncate text-[11.5px] text-fg-subtle">
                          {row.plan_name}
                          {row.membership_info && <> · <span className="text-brand-blue">{row.membership_info}</span></>}
                        </div>
                      </div>
                    </TableCell>
                    <TableCell className="hidden py-2.5 text-[12.5px] text-fg-muted xl:table-cell">
                      <div className="truncate">{row.city_name}</div>
                      <div className="truncate text-[11px] text-fg-subtle">{row.location_name}</div>
                    </TableCell>
                    <TableCell className="py-2.5">
                      <Badge variant={typeTone(row.plan_type)}>{row.plan_type}</Badge>
                    </TableCell>
                    <TableCell className="py-2.5 text-right nums-tabular text-[13px] font-medium text-fg">
                      {row.total}
                    </TableCell>
                    <TableCell
                      className={cn(
                        'hidden py-2.5 text-right nums-tabular text-[12.5px] lg:table-cell',
                        balanceNum > 0 ? 'text-warning font-medium' : 'text-fg-subtle',
                      )}
                    >
                      {row.balance}
                    </TableCell>
                    <TableCell className="py-2.5 pl-2">
                      {row.active ? (
                        <Badge variant="success" dot>Active</Badge>
                      ) : (
                        <Badge variant="neutral" dot>Inactive</Badge>
                      )}
                    </TableCell>
                    <TableCell className="py-2.5 pr-3 text-end" onClick={(e) => e.stopPropagation()}>
                      <div className="flex items-center justify-end gap-0.5">
                        <RowIconAction icon={Eye} label="View detail" onClick={() => setDetailId(row.id)} />
                        {perms.edit !== false && row.plan_type !== 'membership' && (
                          <RowIconAction
                            icon={Pencil}
                            label="Edit plan"
                            onClick={() => setEditPackageId(row.id)}
                          />
                        )}
                        {(perms.active || perms.inactive) && (
                          <RowIconAction
                            icon={row.active ? PowerOff : Power}
                            label={row.active ? 'Deactivate' : 'Activate'}
                            onClick={() => toggleStatus.mutate({ id: row.id, active: row.active ? 0 : 1 })}
                          />
                        )}
                        {perms.delete !== false && (
                          <RowIconAction
                            icon={Trash2}
                            label="Delete"
                            destructive
                            onClick={() => setDeleteTarget(row)}
                          />
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </div>

        <div className="divide-y divide-hairline/70 md:hidden">
          {list.isLoading && Array.from({ length: 4 }).map((_, i) => (
            <div key={`m-sk-${i}`} className="p-4"><Skeleton className="h-14 w-full" /></div>
          ))}
          {!list.isLoading && rows.map((row) => (
            <button
              key={row.id}
              type="button"
              onClick={() => setDetailId(row.id)}
              className="flex w-full items-start gap-3 p-4 text-left transition-colors hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"
            >
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <span className="truncate text-[14px] font-medium text-fg">{row.name}</span>
                  <Badge variant={typeTone(row.plan_type)}>{row.plan_type}</Badge>
                </div>
                <div className="mt-0.5 truncate text-[12px] text-fg-subtle">{row.plan_name}</div>
                <div className="mt-1 flex flex-wrap items-center gap-2 text-[12px] text-fg-subtle">
                  <span className="nums-tabular text-fg">{row.total}</span>
                  {Number(String(row.balance).replace(/,/g, '')) > 0 && (
                    <span className="nums-tabular text-warning">Balance: {row.balance}</span>
                  )}
                  <span>{row.active ? 'Active' : 'Inactive'}</span>
                </div>
              </div>
            </button>
          ))}
        </div>

        {!list.isLoading && rows.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-surface text-fg-subtle">
              <Search className="size-5" />
            </div>
            <h3 className="mt-4 text-[14px] font-medium text-fg">
              {hasFilters ? 'No plans match your filters' : 'No plans yet'}
            </h3>
            <p className="mt-1 max-w-xs text-[12.5px] text-fg-muted">
              {hasFilters
                ? 'Try clearing filters or adjusting your search.'
                : 'Plans sold to patients will appear here. Create flow is in the legacy admin while the SPA catches up.'}
            </p>
            {hasFilters && (
              <Button size="sm" variant="secondary" className="mt-4" onClick={clearFilters}>
                Clear filters
              </Button>
            )}
          </div>
        )}

        {list.error && (
          <div className="border-t border-hairline bg-danger-soft px-4 py-3 text-[12.5px] text-danger">
            Couldn't load plans: {(list.error as Error).message}
          </div>
        )}

        {!!meta && rows.length > 0 && (
          <div className="flex flex-col items-center justify-between gap-3 border-t border-hairline px-4 py-3 sm:flex-row">
            <div className="flex items-center gap-2 text-[12.5px] text-fg-muted">
              <span>Rows per page</span>
              <Select value={String(perPage)} onChange={(e) => setPerPage(Number(e.target.value))} className="h-8 w-20">
                {PAGE_SIZE_OPTIONS.map((n) => (
                  <option key={n} value={n}>{n}</option>
                ))}
              </Select>
              <span className="ml-2">
                {(page - 1) * perPage + 1}–{Math.min(page * perPage, total)} of {total.toLocaleString()}
              </span>
            </div>
            <div className="flex items-center gap-1">
              <Button variant="secondary" size="sm" disabled={page <= 1 || list.isFetching} onClick={() => setPage((p) => Math.max(1, p - 1))}>
                <ChevronLeft className="size-3.5" /> Prev
              </Button>
              <div className="px-2 text-[12.5px] text-fg-muted">
                Page <span className="font-medium text-fg">{page}</span> of {totalPages}
              </div>
              <Button variant="secondary" size="sm" disabled={page >= totalPages || list.isFetching} onClick={() => setPage((p) => p + 1)}>
                Next <ChevronRight className="size-3.5" />
              </Button>
            </div>
          </div>
        )}
      </Card>

      <PlanCreateDialog
        open={createSubtype === 'plan' || createSubtype === 'bundle'}
        onOpenChange={(o) => !o && setCreateSubtype(null)}
        subtype={createSubtype === 'bundle' ? 'bundle' : 'plan'}
      />
      <PlanMembershipCreateDialog
        open={createSubtype === 'membership'}
        onOpenChange={(o) => !o && setCreateSubtype(null)}
      />
      {editPackageId != null && (
        <PlanCreateDialog
          open
          onOpenChange={(o) => !o && setEditPackageId(null)}
          editPackageId={editPackageId}
        />
      )}

      <PlanDetailDialog
        planId={detailId ?? undefined}
        open={detailId !== null}
        onOpenChange={(o) => !o && setDetailId(null)}
        canViewSmsLogs={perms.sms_log !== false}
        canEditCash={perms.plans_cash_edit !== false}
        canEditSoldBy={perms.plans_edit_sold_by !== false}
        canRefund={perms.edit !== false}
        canViewLog={perms.log !== false}
        cashPermissions={{
          paymentMode: perms.plans_cash_edit_payment_mode !== false,
          amount: perms.plans_cash_edit_amount !== false,
          date: perms.plans_cash_edit_date !== false,
        }}
      />

      <Dialog open={deleteTarget !== null} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <span className="flex size-8 items-center justify-center rounded-full bg-danger-soft text-danger">
                <AlertTriangle className="size-4" />
              </span>
              Delete plan?
            </DialogTitle>
            <DialogDescription>
              {deleteTarget && (
                <>
                  This will permanently remove plan <span className="font-medium text-fg">#{deleteTarget.package_id}</span>
                  {' '}for <span className="font-medium text-fg">{deleteTarget.name}</span>.
                  The server blocks deletion if any invoice or cash advance still references this plan.
                </>
              )}
            </DialogDescription>
          </DialogHeader>
          {deleteRow.error && (
            <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
              {(deleteRow.error as Error).message}
            </div>
          )}
          <DialogFooter>
            <Button variant="secondary" size="sm" onClick={() => setDeleteTarget(null)} disabled={deleteRow.isPending}>
              Cancel
            </Button>
            <Button
              variant="danger"
              size="sm"
              onClick={() => deleteTarget && deleteRow.mutate(deleteTarget.id)}
              disabled={deleteRow.isPending}
            >
              {deleteRow.isPending && <Loader2 className="size-4 animate-spin" />}
              Delete
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={bulkOpen} onOpenChange={setBulkOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <span className="flex size-8 items-center justify-center rounded-full bg-danger-soft text-danger">
                <AlertTriangle className="size-4" />
              </span>
              Delete {selected.size} plans?
            </DialogTitle>
            <DialogDescription>
              This will permanently delete the selected plans. The server skips
              any plan that still has invoices or cash advances referencing it.
            </DialogDescription>
          </DialogHeader>
          {bulkDelete.error && (
            <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
              {(bulkDelete.error as Error).message}
            </div>
          )}
          <DialogFooter>
            <Button variant="secondary" size="sm" onClick={() => setBulkOpen(false)} disabled={bulkDelete.isPending}>
              Cancel
            </Button>
            <Button
              variant="danger"
              size="sm"
              onClick={() => bulkDelete.mutate(Array.from(selected))}
              disabled={bulkDelete.isPending || selected.size === 0}
            >
              {bulkDelete.isPending && <Loader2 className="size-4 animate-spin" />}
              Delete {selected.size}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function NewMenuItem({
  icon: Icon,
  label,
  onClick,
}: {
  icon: typeof Plus;
  label: string;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-[12.5px] transition-colors hover:bg-surface"
    >
      <Icon className="size-3.5 text-fg-subtle" />
      <span>{label}</span>
    </button>
  );
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
