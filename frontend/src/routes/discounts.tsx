import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient, keepPreviousData } from '@tanstack/react-query';
import {
  AlertTriangle,
  ChevronLeft,
  ChevronRight,
  Clock,
  MapPin,
  Loader2,
  Pencil,
  Plus,
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
import { DiscountFormDialog } from '@/components/discounts/discount-form-dialog';
import { DiscountAllocationDialog } from '@/components/discounts/discount-allocation-dialog';

/**
 * Discount datatable row shape. `amount` is a decimal string ("15.00"),
 * `start`/`end` are pre-formatted for display ("January 01,2026") — the
 * edit endpoint returns raw YYYY-MM-DD values for form prefill.
 * `active` is a boolean in the response despite being tinyint in the DB.
 */
type DiscountRow = {
  id: number;
  name: string;
  type: 'Fixed' | 'Percentage' | 'Configurable';
  amount: string;
  discount_type: string; // "Treatment" | "Consultancy"
  start: string | null;
  end: string | null;
  active: boolean;
  /** Server-side `withCount('allocations')`. Zero = needs allocation before
   *  the discount is usable on a plan. */
  allocations_count?: number;
  /** Server-side `withCount('packageBundles as plans_count')`. Non-zero
   *  means delete will be blocked — the discount is applied to live plans. */
  plans_count?: number;
};

type DatatableResponse = {
  data: DiscountRow[];
  permissions?: {
    edit?: boolean;
    delete?: boolean;
    active?: boolean;
    inactive?: boolean;
    allocate?: boolean;
  };
  filter_values?: {
    status?: string[];
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

export default function DiscountsPage() {
  const qc = useQueryClient();
  const [searchInput, setSearchInput] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [addOpen, setAddOpen] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [allocateId, setAllocateId] = useState<number | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<DiscountRow | null>(null);
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [bulkOpen, setBulkOpen] = useState(false);

  useEffect(() => {
    const t = setTimeout(() => setDebouncedSearch(searchInput.trim()), 300);
    return () => clearTimeout(t);
  }, [searchInput]);

  useEffect(() => {
    setPage(1);
    setSelected(new Set());
  }, [debouncedSearch, statusFilter, perPage]);

  const list = useQuery({
    queryKey: ['discounts', 'datatable', page, perPage, debouncedSearch, statusFilter],
    placeholderData: keepPreviousData,
    queryFn: () =>
      api.post<DatatableResponse>(
        '/api/discounts/datatable',
        {
          pagination: { page, perpage: perPage },
          query: {
            search: {
              name: debouncedSearch || undefined,
              status: statusFilter || undefined,
            },
          },
        },
        { raw: true },
      ),
  });

  const toggleStatus = useMutation({
    mutationFn: ({ id, active }: { id: number; active: 0 | 1 }) =>
      api.post('/api/discounts/status', { id, status: active }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['discounts', 'datatable'] }),
  });

  const deleteRow = useMutation({
    mutationFn: (id: number) => api.delete(`/api/discounts/${id}`),
    onSuccess: () => {
      setDeleteTarget(null);
      qc.invalidateQueries({ queryKey: ['discounts', 'datatable'] });
    },
  });

  const bulkDelete = useMutation({
    mutationFn: (ids: number[]) =>
      api.post(
        '/api/discounts/datatable',
        { query: { search: { delete: ids.join(',') } } },
        { raw: true },
      ),
    onSuccess: () => {
      setBulkOpen(false);
      setSelected(new Set());
      qc.invalidateQueries({ queryKey: ['discounts', 'datatable'] });
    },
  });

  const rows = list.data?.data ?? [];
  const meta = list.data?.meta;
  const total = meta?.total ?? 0;
  const totalPages = Math.max(1, Math.ceil(total / perPage));
  const perms = list.data?.permissions ?? {};
  const hasFilters = !!(debouncedSearch || statusFilter);

  const visibleIds = useMemo(() => rows.map((r) => r.id), [rows]);
  const allSelectedOnPage = visibleIds.length > 0 && visibleIds.every((id) => selected.has(id));
  const someSelectedOnPage = visibleIds.some((id) => selected.has(id));

  const togglePageSelection = () => {
    setSelected((prev) => {
      const next = new Set(prev);
      if (allSelectedOnPage) visibleIds.forEach((id) => next.delete(id));
      else visibleIds.forEach((id) => next.add(id));
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
  };

  const typeBadgeTone = (t: DiscountRow['type']) =>
    t === 'Configurable' ? 'brand' : t === 'Percentage' ? 'success' : 'warning';

  /**
   * "end" arrives pre-formatted ("January 01,2026"). Parse defensively; if it
   * falls inside the next 7 days, surface an expiring-soon chip.
   */
  const daysUntil = (formatted: string | null): number | null => {
    if (!formatted) return null;
    const t = Date.parse(formatted);
    if (Number.isNaN(t)) return null;
    const diffMs = t - Date.now();
    return Math.ceil(diffMs / 86_400_000);
  };

  return (
    <div className="mx-auto max-w-[1400px] space-y-3 px-4 py-4 sm:px-6 lg:px-8">
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex items-baseline gap-2.5">
          <h1 className="text-2xl font-semibold tracking-tight">Discounts</h1>
          {total > 0 && (
            <span className="text-[13px] text-fg-subtle nums-tabular">
              <span className="font-medium text-fg-muted">{total.toLocaleString()}</span>{' '}
              total
            </span>
          )}
        </div>
        <div className="ml-auto flex items-center gap-2">
          {selected.size > 0 && perms.delete !== false && (
            <>
              <span className="text-[12.5px] text-fg-muted">{selected.size}</span>
              <Button size="sm" variant="danger" onClick={() => setBulkOpen(true)}>
                <Trash2 className="size-3.5" />
                <span className="hidden sm:inline">Delete selected</span>
              </Button>
            </>
          )}
          <Button size="sm" onClick={() => setAddOpen(true)}>
            <Plus className="size-3.5" />
            <span className="hidden xs:inline">Add discount</span>
            <span className="xs:hidden">Add</span>
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-1.5">
        <div className="relative min-w-[220px] flex-1 basis-[260px]">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-fg-subtle" aria-hidden />
          <input
            type="search"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder="Search by name…"
            aria-label="Search discounts"
            className="h-9 w-full rounded-lg bg-elevated pl-9 pr-2 text-[13px] text-fg shadow-xs ring-1 ring-inset ring-hairline placeholder:text-fg-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
          />
        </div>
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
                <TableHead className="pl-4 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Name</TableHead>
                <TableHead className="w-[130px] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Type</TableHead>
                <TableHead className="w-[110px] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle text-right">Amount</TableHead>
                <TableHead className="hidden w-[200px] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle lg:table-cell">Validity</TableHead>
                <TableHead className="w-[110px] pl-2 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Status</TableHead>
                <TableHead className="w-[170px] pr-3 text-end" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {list.isLoading && Array.from({ length: 6 }).map((_, i) => (
                <TableRow key={`sk-${i}`}>
                  {perms.delete !== false && <TableCell className="pl-4"><Skeleton className="size-3.5" /></TableCell>}
                  <TableCell className="py-2.5 pl-4"><Skeleton className="h-4 w-full max-w-[280px]" /></TableCell>
                  <TableCell className="py-2.5"><Skeleton className="h-5 w-20 rounded-full" /></TableCell>
                  <TableCell className="py-2.5"><Skeleton className="ml-auto h-4 w-16" /></TableCell>
                  <TableCell className="hidden py-2.5 lg:table-cell"><Skeleton className="h-4 w-32" /></TableCell>
                  <TableCell className="py-2.5"><Skeleton className="h-5 w-20 rounded-full" /></TableCell>
                  <TableCell />
                </TableRow>
              ))}
              {!list.isLoading && rows.map((row) => {
                const daysLeft = row.active ? daysUntil(row.end) : null;
                const expiringSoon = daysLeft !== null && daysLeft >= 0 && daysLeft <= 7;
                const isSelected = selected.has(row.id);
                const notAllocated = typeof row.allocations_count === 'number' && row.allocations_count === 0;
                const plansUsing = row.plans_count ?? 0;
                const inUse = plansUsing > 0;
                return (
                <TableRow key={row.id} className={cn(isSelected && 'bg-surface')}>
                  {perms.delete !== false && (
                    <TableCell className="pl-4">
                      <input
                        type="checkbox"
                        aria-label={`Select ${row.name}`}
                        checked={isSelected}
                        onChange={() => toggleRowSelection(row.id)}
                        className="size-3.5 rounded border-hairline text-brand-blue focus:ring-2 focus:ring-accent-cyan/50"
                      />
                    </TableCell>
                  )}
                  <TableCell className="py-2.5 pl-4">
                    <div className="flex items-center gap-2">
                      <div className="truncate text-[13px] font-medium text-fg">{row.name}</div>
                      {notAllocated && (
                        <Badge variant="warning" title="No centre or service allocated yet">
                          Not allocated
                        </Badge>
                      )}
                      {inUse && (
                        <Badge
                          variant="brand"
                          title={`Applied to ${plansUsing} distinct ${plansUsing === 1 ? 'plan' : 'plans'}. Delete is blocked while in use.`}
                        >
                          Used in {plansUsing} {plansUsing === 1 ? 'plan' : 'plans'}
                        </Badge>
                      )}
                      {expiringSoon && (
                        <Badge variant="warning" title={`Expires ${row.end}`}>
                          <Clock className="size-3" />
                          {daysLeft === 0 ? 'Today' : `${daysLeft}d`}
                        </Badge>
                      )}
                    </div>
                    <div className="mt-0.5 text-[11.5px] text-fg-subtle">{row.discount_type}</div>
                  </TableCell>
                  <TableCell className="py-2.5">
                    <Badge variant={typeBadgeTone(row.type)}>{row.type}</Badge>
                  </TableCell>
                  <TableCell className="py-2.5 text-right nums-tabular text-[13px] font-medium text-fg">
                    {row.type === 'Configurable'
                      ? '—'
                      : row.type === 'Percentage'
                        ? `${Number(row.amount).toFixed(0)}%`
                        : Number(row.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                  </TableCell>
                  <TableCell className="hidden py-2.5 text-[12.5px] text-fg-muted lg:table-cell">
                    {(row.start || row.end) ? `${row.start ?? '—'} → ${row.end ?? '—'}` : '—'}
                  </TableCell>
                  <TableCell className="py-2.5 pl-2">
                    {row.active ? (
                      <Badge variant="success" dot>Active</Badge>
                    ) : (
                      <Badge variant="neutral" dot>Inactive</Badge>
                    )}
                  </TableCell>
                  <TableCell className="py-2.5 pr-3 text-end">
                    <div className="flex items-center justify-end gap-0.5">
                      {(perms.active || perms.inactive) && (
                        <RowIconAction
                          icon={row.active ? PowerOff : Power}
                          label={row.active ? 'Deactivate' : 'Activate'}
                          onClick={() => toggleStatus.mutate({ id: row.id, active: row.active ? 0 : 1 })}
                        />
                      )}
                      <RowIconAction
                        icon={MapPin}
                        label="Allocate"
                        attention={notAllocated}
                        onClick={() => setAllocateId(row.id)}
                      />
                      {perms.edit !== false && (
                        <RowIconAction icon={Pencil} label="Edit" onClick={() => setEditId(row.id)} />
                      )}
                      {perms.delete !== false && (
                        <RowIconAction
                          icon={Trash2}
                          label={inUse ? `Can't delete — applied to ${plansUsing} ${plansUsing === 1 ? 'plan' : 'plans'}` : 'Delete'}
                          destructive
                          disabled={inUse}
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
          {!list.isLoading && rows.map((row) => {
            const notAllocated = typeof row.allocations_count === 'number' && row.allocations_count === 0;
            const plansUsing = row.plans_count ?? 0;
            const inUse = plansUsing > 0;
            return (
            <div key={row.id} className="p-4">
              {/* Content block — full-width so chips wrap cleanly. */}
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-1.5">
                  <div className="truncate text-[14px] font-medium text-fg">{row.name}</div>
                  <Badge variant={typeBadgeTone(row.type)}>{row.type}</Badge>
                  {notAllocated && <Badge variant="warning">Not allocated</Badge>}
                  {inUse && <Badge variant="brand">Used in {plansUsing} {plansUsing === 1 ? 'plan' : 'plans'}</Badge>}
                </div>
                <div className="mt-1 flex flex-wrap items-center gap-2 text-[12px] text-fg-subtle">
                  <span>{row.discount_type}</span>
                  {row.type !== 'Configurable' && (
                    <span className="nums-tabular text-fg">
                      {row.type === 'Percentage' ? `${Number(row.amount).toFixed(0)}%` : Number(row.amount).toLocaleString()}
                    </span>
                  )}
                  {(row.start || row.end) && <span className="whitespace-nowrap">{row.start ?? '—'} → {row.end ?? '—'}</span>}
                  <span>{row.active ? 'Active' : 'Inactive'}</span>
                </div>

                {/* Mobile-friendly action row — labeled buttons, ≥44px tap
                    targets (WCAG AA). Each action stays visually distinct
                    so operators don't mis-tap on a phone. */}
                <div className="mt-3 grid grid-cols-4 gap-1.5">
                  <MobileActionBtn
                    icon={row.active ? PowerOff : Power}
                    label={row.active ? 'Off' : 'On'}
                    onClick={() => toggleStatus.mutate({ id: row.id, active: row.active ? 0 : 1 })}
                  />
                  <MobileActionBtn
                    icon={MapPin}
                    label="Allocate"
                    attention={notAllocated}
                    onClick={() => setAllocateId(row.id)}
                  />
                  <MobileActionBtn icon={Pencil} label="Edit" onClick={() => setEditId(row.id)} />
                  <MobileActionBtn
                    icon={Trash2}
                    label="Delete"
                    destructive
                    disabled={inUse}
                    onClick={() => setDeleteTarget(row)}
                  />
                </div>
              </div>
            </div>
            );
          })}
        </div>

        {!list.isLoading && rows.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-surface text-fg-subtle">
              <Search className="size-5" />
            </div>
            <h3 className="mt-4 text-[14px] font-medium text-fg">
              {hasFilters ? 'No discounts match your filters' : 'No discounts yet'}
            </h3>
            <p className="mt-1 max-w-xs text-[12.5px] text-fg-muted">
              {hasFilters
                ? 'Try clearing filters or adjusting your search.'
                : 'Create a discount to offer to patients — fixed amount, percentage, or configurable Buy/Get.'}
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
            Couldn’t load discounts: {(list.error as Error).message}
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

      <DiscountFormDialog
        mode="create"
        open={addOpen}
        onOpenChange={setAddOpen}
        onCreated={(id) => {
          // Hand-off: as soon as the discount is saved, open the allocation
          // dialog for it. A brand-new discount is inert until allocated;
          // auto-advancing saves operators an extra round of "find the row,
          // click the Allocate icon."
          setAllocateId(id);
        }}
      />
      <DiscountFormDialog
        mode="edit"
        discountId={editId ?? undefined}
        open={editId !== null}
        onOpenChange={(o) => !o && setEditId(null)}
      />
      <DiscountAllocationDialog
        discountId={allocateId ?? undefined}
        open={allocateId !== null}
        onOpenChange={(o) => !o && setAllocateId(null)}
      />

      <Dialog open={deleteTarget !== null} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <span className="flex size-8 items-center justify-center rounded-full bg-danger-soft text-danger">
                <AlertTriangle className="size-4" />
              </span>
              Delete discount?
            </DialogTitle>
            <DialogDescription>
              {deleteTarget && (
                <>
                  This will permanently remove <span className="font-medium text-fg">{deleteTarget.name}</span>.
                  The server blocks deletion if any location allocation or package still references this discount.
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
              Delete {selected.size} discounts?
            </DialogTitle>
            <DialogDescription>
              This will permanently delete the selected discounts. Allocations
              (service + location bindings) are removed along with them.
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

      {/* Bottom-anchored status toast — both success and error paths. The
          delete dialog closes on success (deleteRow.onSuccess clears target),
          so operators need this to confirm the action actually happened.
          Errors keep the dialog open + show inline, but a toast also fires
          here for visibility at the datatable level. */}
      {(deleteRow.isSuccess || deleteRow.isError) && (
        <FlashFeedback
          type={deleteRow.isSuccess ? 'success' : 'danger'}
          onDismiss={() => deleteRow.reset()}
        >
          {deleteRow.isSuccess
            ? 'Discount deleted.'
            : (deleteRow.error as Error | undefined)?.message ?? 'Delete failed.'}
        </FlashFeedback>
      )}
      {(bulkDelete.isSuccess || bulkDelete.isError) && (
        <FlashFeedback
          type={bulkDelete.isSuccess ? 'success' : 'danger'}
          onDismiss={() => bulkDelete.reset()}
        >
          {bulkDelete.isSuccess
            ? 'Selected discounts deleted.'
            : (bulkDelete.error as Error | undefined)?.message ?? 'Bulk delete failed.'}
        </FlashFeedback>
      )}
    </div>
  );
}

function FlashFeedback({
  type,
  children,
  onDismiss,
}: {
  type: 'success' | 'danger';
  children: React.ReactNode;
  onDismiss: () => void;
}) {
  useEffect(() => {
    const t = setTimeout(onDismiss, 3500);
    return () => clearTimeout(t);
  }, [onDismiss]);
  return (
    <div
      role="status"
      className={cn(
        'fixed bottom-4 left-1/2 z-50 -translate-x-1/2 rounded-lg px-4 py-2 text-[13px] shadow-md ring-1 ring-inset',
        type === 'success'
          ? 'bg-success-soft text-success ring-success/20'
          : 'bg-danger-soft text-danger ring-danger/20',
      )}
    >
      {children}
    </div>
  );
}

function RowIconAction({
  icon: Icon,
  label,
  onClick,
  destructive,
  attention,
  disabled,
}: {
  icon: typeof Pencil;
  label: string;
  onClick: () => void;
  destructive?: boolean;
  /** Accent the icon + ring it to nudge the operator toward this action
   *  (used for "needs allocation" on newly-created discounts). */
  attention?: boolean;
  disabled?: boolean;
}) {
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      onClick={onClick}
      disabled={disabled}
      className={cn(
        'inline-flex size-7 items-center justify-center rounded-md transition-colors',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50',
        disabled
          ? 'cursor-not-allowed text-fg-subtle/50'
          : 'hover:bg-surface ' +
            (attention
              ? 'text-warning ring-1 ring-inset ring-warning/40 hover:text-warning'
              : 'text-fg-subtle ' + (destructive ? 'hover:text-danger' : 'hover:text-fg')),
      )}
    >
      <Icon className="size-3.5" />
    </button>
  );
}

/**
 * Mobile card action button — icon + label, ≥44px tap height.
 * Used in the md:hidden card layout where row icons alone are too small
 * to tap reliably on a phone.
 */
function MobileActionBtn({
  icon: Icon,
  label,
  onClick,
  destructive,
  attention,
  disabled,
}: {
  icon: typeof Pencil;
  label: string;
  onClick: () => void;
  destructive?: boolean;
  attention?: boolean;
  disabled?: boolean;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      className={cn(
        'flex h-11 flex-col items-center justify-center gap-0.5 rounded-lg ring-1 ring-inset text-[11px] font-medium transition-colors',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50',
        disabled
          ? 'cursor-not-allowed bg-surface text-fg-subtle/50 ring-hairline/60'
          : attention
            ? 'bg-warning-soft text-warning ring-warning/30 active:bg-warning-soft/80'
            : destructive
              ? 'bg-elevated text-fg-subtle ring-hairline active:bg-danger-soft active:text-danger'
              : 'bg-elevated text-fg-subtle ring-hairline active:bg-surface active:text-fg',
      )}
    >
      <Icon className="size-3.5" />
      <span>{label}</span>
    </button>
  );
}
