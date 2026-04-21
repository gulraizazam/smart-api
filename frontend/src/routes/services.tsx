import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient, keepPreviousData } from '@tanstack/react-query';
import {
  AlertTriangle,
  ChevronLeft,
  ChevronRight,
  Clock,
  Copy,
  FileDown,
  ListOrdered,
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
import { ServiceFormDialog } from '@/components/services/service-form-dialog';
import { ServiceSortDialog } from '@/components/services/service-sort-dialog';
import { ServiceInstructionsDialog } from '@/components/services/service-instructions-dialog';

type ServiceRow = {
  id: number;
  name: string;
  slug: string;
  parent_id: number | null;
  is_parent: boolean;
  end_node: number;
  complimentory: number;
  active: number;
  duration: string | null;
  price: number;
  color: string | null;
  description: string | null;
  tax_treatment_type_id: number | null;
  sort_no: number;
  sort_number: number;
};

type DatatableResponse = {
  data: ServiceRow[];
  permissions?: {
    edit?: boolean;
    delete?: boolean;
    active?: boolean;
    inactive?: boolean;
    duplicate?: boolean;
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

export default function ServicesPage() {
  const qc = useQueryClient();
  const [searchInput, setSearchInput] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [addOpen, setAddOpen] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [duplicateId, setDuplicateId] = useState<number | null>(null);
  const [sortOpen, setSortOpen] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<ServiceRow | null>(null);
  const [instructionsFor, setInstructionsFor] = useState<ServiceRow | null>(null);
  /* Bulk-delete selection — parity with the legacy Blade datatable which
     exposes row checkboxes + a "Delete selected" button. State is scoped
     to the current page only; selection is cleared on pagination/filter
     changes to avoid deleting rows the user can't see. */
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());

  useEffect(() => {
    const t = setTimeout(() => setDebouncedSearch(searchInput.trim()), 300);
    return () => clearTimeout(t);
  }, [searchInput]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, statusFilter, perPage]);

  // Clear selection when the visible page changes — stale ids would either
  // silently bulk-delete off-screen rows or point at rows no longer here.
  useEffect(() => {
    setSelectedIds(new Set());
  }, [page, perPage, debouncedSearch, statusFilter]);

  const list = useQuery({
    queryKey: ['services', 'datatable', page, perPage, debouncedSearch, statusFilter],
    placeholderData: keepPreviousData,
    queryFn: () =>
      api.post<DatatableResponse>(
        '/api/services/datatable',
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
      api.post('/api/services/status', { id, status: active }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['services', 'datatable'] }),
  });

  const deleteRow = useMutation({
    mutationFn: (id: number) => api.delete(`/api/services/${id}`),
    onSuccess: () => {
      setDeleteTarget(null);
      qc.invalidateQueries({ queryKey: ['services', 'datatable'] });
    },
  });

  /* Bulk delete — the legacy datatable endpoint doubles as a bulk-delete
     action: when the `delete` query param carries a comma-separated id
     list, ServicesController::datatable (Api) iterates the ids, calls
     deleteService() for each, and then returns the refreshed list. Per-id
     failures are swallowed server-side, so the mutation resolves as long
     as the endpoint responds. */
  const bulkDelete = useMutation({
    mutationFn: (ids: number[]) =>
      api.post(
        '/api/services/datatable',
        {
          pagination: { page: 1, perpage: perPage },
          query: { search: { delete: ids.join(',') } },
        },
        { raw: true },
      ),
    onSuccess: () => {
      setSelectedIds(new Set());
      setBulkConfirmOpen(false);
      qc.invalidateQueries({ queryKey: ['services', 'datatable'] });
    },
  });
  const [bulkConfirmOpen, setBulkConfirmOpen] = useState(false);

  const rows = list.data?.data ?? [];
  const meta = list.data?.meta;
  const total = meta?.total ?? 0;
  const totalPages = Math.max(1, Math.ceil(total / perPage));
  const perms = list.data?.permissions ?? {};
  const hasFilters = !!(debouncedSearch || statusFilter);

  const clearFilters = () => {
    setSearchInput('');
    setStatusFilter('');
  };

  // Only offer bulk-delete on rows whose `delete` permission is granted.
  // The datatable response carries permissions at the collection level
  // (perms.delete), so we gate on that rather than per-row for simplicity.
  const selectableRows = perms.delete === false ? [] : rows;
  const allVisibleSelected =
    selectableRows.length > 0 && selectableRows.every((r) => selectedIds.has(r.id));
  const someVisibleSelected =
    selectableRows.some((r) => selectedIds.has(r.id)) && ! allVisibleSelected;

  const toggleRow = (id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });
  };
  const toggleAllVisible = () => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (allVisibleSelected) {
        selectableRows.forEach((r) => next.delete(r.id));
      } else {
        selectableRows.forEach((r) => next.add(r.id));
      }
      return next;
    });
  };

  return (
    <div className="mx-auto max-w-[1400px] space-y-3 px-4 py-4 sm:px-6 lg:px-8">
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex items-baseline gap-2.5">
          <h1 className="text-2xl font-semibold tracking-tight">Services</h1>
          {total > 0 && (
            <span className="text-[13px] text-fg-subtle nums-tabular">
              <span className="font-medium text-fg-muted">{total.toLocaleString()}</span> total
            </span>
          )}
        </div>
        <div className="ml-auto flex items-center gap-1.5">
          {/* Feature-parity: legacy Blade exposes "Export to PDF" on the
              services list. The endpoint (`/admin/services/export-pdf`)
              lives on the legacy web route group, so we open it in a new
              tab rather than round-tripping through the SPA. */}
          <a
            href="/admin/services/export-pdf"
            target="_blank"
            rel="noreferrer"
            className="hidden sm:inline-flex h-9 items-center gap-1.5 rounded-lg border border-hairline bg-elevated px-3 text-[12.5px] font-medium text-fg-muted shadow-xs transition-colors hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
          >
            <FileDown className="size-3.5" /> Export PDF
          </a>
          <Button variant="secondary" size="sm" onClick={() => setSortOpen(true)} className="hidden sm:inline-flex">
            <ListOrdered className="size-3.5" /> Reorder
          </Button>
          <Button variant="secondary" size="icon" aria-label="Reorder" className="sm:hidden" onClick={() => setSortOpen(true)}>
            <ListOrdered className="size-4" />
          </Button>
          <Button size="sm" onClick={() => setAddOpen(true)}>
            <Plus className="size-3.5" />
            <span className="hidden xs:inline sm:inline">Add service</span>
            <span className="xs:hidden sm:hidden">Add</span>
          </Button>
        </div>
      </div>

      {/* Command rail — same shape as Leads. Filter pills flow inline
          with the search pill; shared primitives come from
          '@/components/filters/filter-pill'. */}
      <div className="flex flex-wrap items-center gap-1.5">
        <div className="relative min-w-[220px] flex-1 basis-[260px]">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-fg-subtle" aria-hidden />
          <input
            type="search"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder="Search by name…"
            aria-label="Search services"
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

      {selectedIds.size > 0 && perms.delete !== false && (
        <div className="flex items-center justify-between rounded-lg bg-accent-cyan/10 px-3 py-2 text-[13px] ring-1 ring-inset ring-accent-cyan/30">
          <span className="font-medium text-fg">
            {selectedIds.size} selected
          </span>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setSelectedIds(new Set())}
              className="text-[12.5px] font-medium text-fg-muted hover:text-fg"
            >
              Clear
            </button>
            <Button
              variant="danger"
              size="sm"
              onClick={() => setBulkConfirmOpen(true)}
            >
              <Trash2 className="size-3.5" /> Delete selected
            </Button>
          </div>
        </div>
      )}

      <Card className="overflow-hidden">
        <div className="hidden md:block">
          <Table>
            <TableHeader>
              {/* Column widths — Name is auto (consumes the slack so
                  long service names breathe). Duration/Price/Status
                  are content-hugging fixed widths; the previous
                  percentage-based layout left huge dead space next to
                  narrow numeric values. Actions column is fixed to fit
                  four 28px icon buttons with gap. */}
              <TableRow className="border-b border-hairline">
                {perms.delete !== false && (
                  <TableHead className="w-[40px] pl-4">
                    <input
                      type="checkbox"
                      aria-label="Select all visible"
                      className="size-3.5 rounded border-hairline text-accent-cyan focus:ring-accent-cyan/50"
                      checked={allVisibleSelected}
                      ref={(el) => {
                        if (el) el.indeterminate = someVisibleSelected;
                      }}
                      onChange={toggleAllVisible}
                      disabled={selectableRows.length === 0}
                    />
                  </TableHead>
                )}
                <TableHead className={cn('text-[11px] font-semibold uppercase tracking-wide text-fg-subtle', perms.delete === false && 'pl-4')}>Name</TableHead>
                <TableHead className="hidden w-[110px] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle lg:table-cell">Duration</TableHead>
                <TableHead className="w-[120px] pr-6 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle text-right">Price</TableHead>
                <TableHead className="w-[110px] pl-2 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Status</TableHead>
                <TableHead className="w-[160px] pr-3 text-end" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {list.isLoading && Array.from({ length: 6 }).map((_, i) => (
                <TableRow key={`sk-${i}`}>
                  {perms.delete !== false && (
                    <TableCell className="py-2.5 pl-4"><Skeleton className="size-3.5" /></TableCell>
                  )}
                  <TableCell className={cn('py-2.5', perms.delete === false && 'pl-4')}><Skeleton className="h-4 w-full max-w-[280px]" /></TableCell>
                  <TableCell className="hidden py-2.5 lg:table-cell"><Skeleton className="h-4 w-16" /></TableCell>
                  <TableCell className="py-2.5 pr-6"><Skeleton className="ml-auto h-4 w-20" /></TableCell>
                  <TableCell className="py-2.5 pl-2"><Skeleton className="h-5 w-20 rounded-full" /></TableCell>
                  <TableCell />
                </TableRow>
              ))}
              {!list.isLoading && rows.map((row) => (
                <TableRow key={row.id} data-selected={selectedIds.has(row.id) || undefined} className="data-[selected]:bg-accent-cyan/5">
                  {perms.delete !== false && (
                    <TableCell className="py-2.5 pl-4">
                      <input
                        type="checkbox"
                        aria-label={`Select ${row.name}`}
                        className="size-3.5 rounded border-hairline text-accent-cyan focus:ring-accent-cyan/50"
                        checked={selectedIds.has(row.id)}
                        onChange={() => toggleRow(row.id)}
                      />
                    </TableCell>
                  )}
                  <TableCell className={cn(
                    'py-2.5',
                    perms.delete === false && 'pl-4',
                    !row.is_parent && (perms.delete === false ? 'pl-10' : 'pl-6'),
                  )}>
                    <div className="flex items-center gap-2">
                      <ColorSwatch color={row.color} variant={row.is_parent ? 'filled' : 'ring'} />
                      <div className="min-w-0">
                        <div className="flex items-center gap-2">
                          <button
                            type="button"
                            onClick={() => setInstructionsFor(row)}
                            title="View instructions"
                            className="truncate text-left text-[13px] font-medium text-fg transition-colors hover:text-accent-cyan hover:underline focus-visible:outline-none focus-visible:text-accent-cyan focus-visible:underline"
                          >
                            {row.name}
                          </button>
                          {row.is_parent && <Badge variant="neutral">Parent</Badge>}
                          {!!row.complimentory && <Badge variant="brand">Comp.</Badge>}
                        </div>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell className="hidden py-2.5 text-[12.5px] text-fg-muted lg:table-cell">
                    {row.duration && !row.is_parent ? (
                      <span className="inline-flex items-center gap-1 nums-tabular">
                        <Clock className="size-3" /> {row.duration}
                      </span>
                    ) : (
                      '—'
                    )}
                  </TableCell>
                  <TableCell className="py-2.5 pr-6 text-right nums-tabular text-[12.5px] text-fg-muted">
                    {row.is_parent ? '—' : formatPrice(row.price)}
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
                      {perms.duplicate && (
                        <RowIconAction icon={Copy} label="Duplicate" onClick={() => setDuplicateId(row.id)} />
                      )}
                      {perms.edit !== false && (
                        <RowIconAction icon={Pencil} label="Edit" onClick={() => setEditId(row.id)} />
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
              ))}
            </TableBody>
          </Table>
        </div>

        <div className="divide-y divide-hairline/70 md:hidden">
          {list.isLoading && Array.from({ length: 4 }).map((_, i) => (
            <div key={`m-sk-${i}`} className="p-4"><Skeleton className="h-12 w-full" /></div>
          ))}
          {!list.isLoading && rows.map((row) => (
            <div key={row.id} className={cn('flex items-center gap-3 p-4', !row.is_parent && 'pl-8')}>
              <ColorSwatch color={row.color} variant={row.is_parent ? 'filled' : 'ring'} />
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => setInstructionsFor(row)}
                    className="truncate text-left text-[14px] font-medium text-fg transition-colors hover:text-accent-cyan focus-visible:outline-none focus-visible:text-accent-cyan"
                  >
                    {row.name}
                  </button>
                  {row.is_parent && <Badge variant="neutral">Parent</Badge>}
                </div>
                <div className="mt-0.5 flex flex-wrap items-center gap-2 text-[11.5px] text-fg-subtle">
                  {row.is_parent ? (
                    <span>{row.active ? 'Active' : 'Inactive'}</span>
                  ) : (
                    <>
                      {row.duration && (
                        <span className="inline-flex items-center gap-1 nums-tabular">
                          <Clock className="size-3" /> {row.duration}
                        </span>
                      )}
                      <span className="nums-tabular">{formatPrice(row.price)}</span>
                      <span>{row.active ? 'Active' : 'Inactive'}</span>
                    </>
                  )}
                </div>
              </div>
              <div className="flex shrink-0 items-center gap-0.5">
                <RowIconAction
                  icon={row.active ? PowerOff : Power}
                  label={row.active ? 'Deactivate' : 'Activate'}
                  onClick={() => toggleStatus.mutate({ id: row.id, active: row.active ? 0 : 1 })}
                />
                <RowIconAction icon={Pencil} label="Edit" onClick={() => setEditId(row.id)} />
                <RowIconAction icon={Trash2} label="Delete" destructive onClick={() => setDeleteTarget(row)} />
              </div>
            </div>
          ))}
        </div>

        {!list.isLoading && rows.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-surface text-fg-subtle">
              <Search className="size-5" />
            </div>
            <h3 className="mt-4 text-[14px] font-medium text-fg">
              {hasFilters ? 'No services match your filters' : 'No services yet'}
            </h3>
            <p className="mt-1 max-w-xs text-[12.5px] text-fg-muted">
              {hasFilters
                ? 'Try clearing filters or adjusting your search.'
                : 'Add a parent category, then add child services under it.'}
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
            Couldn’t load services: {(list.error as Error).message}
          </div>
        )}

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

      <ServiceFormDialog mode="create" open={addOpen} onOpenChange={setAddOpen} />
      <ServiceFormDialog
        mode="edit"
        serviceId={editId ?? undefined}
        open={editId !== null}
        onOpenChange={(o) => !o && setEditId(null)}
      />
      <ServiceFormDialog
        mode="duplicate"
        serviceId={duplicateId ?? undefined}
        open={duplicateId !== null}
        onOpenChange={(o) => !o && setDuplicateId(null)}
      />
      <ServiceSortDialog open={sortOpen} onOpenChange={setSortOpen} />
      <ServiceInstructionsDialog
        serviceId={instructionsFor?.id ?? null}
        serviceName={instructionsFor?.name ?? ''}
        open={instructionsFor !== null}
        onOpenChange={(o) => !o && setInstructionsFor(null)}
      />

      <Dialog open={deleteTarget !== null} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <span className="flex size-8 items-center justify-center rounded-full bg-danger-soft text-danger">
                <AlertTriangle className="size-4" />
              </span>
              Delete service?
            </DialogTitle>
            <DialogDescription>
              {deleteTarget && (
                <>
                  This will permanently remove <span className="font-medium text-fg">{deleteTarget.name}</span>.{' '}
                  <span className="font-medium text-danger">This cannot be undone.</span>{' '}
                  The server blocks deletion if any child services, packages, discounts, doctor allocations,
                  appointments, staff targets, or paid invoices reference this service.
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

      {/* Bulk-delete confirmation. Message pluralises and shows the count
          so the user can eyeball "did I accidentally select too many?". */}
      <Dialog open={bulkConfirmOpen} onOpenChange={(o) => !o && setBulkConfirmOpen(false)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <span className="flex size-8 items-center justify-center rounded-full bg-danger-soft text-danger">
                <AlertTriangle className="size-4" />
              </span>
              Delete {selectedIds.size} service{selectedIds.size === 1 ? '' : 's'}?
            </DialogTitle>
            <DialogDescription>
              This will permanently remove {selectedIds.size} service{selectedIds.size === 1 ? '' : 's'}.{' '}
              <span className="font-medium text-danger">This cannot be undone.</span>{' '}
              Services that can't be deleted (because they're referenced by packages, appointments, invoices, etc.) are skipped silently — the rest are removed.
            </DialogDescription>
          </DialogHeader>
          {bulkDelete.error && (
            <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
              {(bulkDelete.error as Error).message}
            </div>
          )}
          <DialogFooter>
            <Button variant="secondary" size="sm" onClick={() => setBulkConfirmOpen(false)} disabled={bulkDelete.isPending}>
              Cancel
            </Button>
            <Button
              variant="danger"
              size="sm"
              onClick={() => bulkDelete.mutate(Array.from(selectedIds))}
              disabled={bulkDelete.isPending || selectedIds.size === 0}
            >
              {bulkDelete.isPending && <Loader2 className="size-4 animate-spin" />}
              Delete {selectedIds.size}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function ColorSwatch({
  color,
  variant = 'filled',
}: {
  color: string | null;
  /** `filled` — solid dot (use for parent categories; strong anchor).
   *  `ring`   — hollow outline (use for child services; lighter so long
   *  child lists don't feel visually heavy). Both use the same service
   *  color so the relationship to the parent stays readable. */
  variant?: 'filled' | 'ring';
}) {
  const c = color || '#cbd5e1';
  return variant === 'filled' ? (
    <span
      aria-hidden
      className="inline-block size-3 shrink-0 rounded-full ring-1 ring-inset ring-hairline"
      style={{ backgroundColor: c }}
    />
  ) : (
    <span
      aria-hidden
      className="inline-block size-2.5 shrink-0 rounded-full bg-transparent"
      style={{ boxShadow: `inset 0 0 0 1.5px ${c}` }}
    />
  );
}

function formatPrice(price: number | string): string {
  const n = Number(price);
  if (!Number.isFinite(n) || n === 0) return '—';
  return n.toLocaleString();
}

function RowIconAction({
  icon: Icon,
  label,
  onClick,
  destructive,
}: {
  icon: typeof Pencil;
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
