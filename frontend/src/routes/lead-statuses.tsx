import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient, keepPreviousData } from '@tanstack/react-query';
import {
  AlertTriangle,
  ChevronLeft,
  ChevronRight,
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
import { LeadStatusFormDialog } from '@/components/lead-statuses/lead-status-form-dialog';

/** LeadStatusResource → datatable row shape. Booleans and parent_id are
 *  pre-transformed for display ("Yes"/"No" and parent name). The edit
 *  endpoint uses LeadStatusFormResource with raw values, so the dialog
 *  handles its own deserialisation — this type is for the list only. */
type LeadStatusRow = {
  id: number;
  name: string;
  parent_id: string; // parent NAME (or "-")
  is_default: 'Yes' | 'No';
  is_booked?: 'Yes' | 'No';
  is_arrived: 'Yes' | 'No';
  is_converted: 'Yes' | 'No';
  is_junk: 'Yes' | 'No';
  is_comment: 'Yes' | 'No';
  sort_no: number;
  active: boolean | number;
};

type DatatableResponse = {
  data: LeadStatusRow[];
  permissions?: {
    edit?: boolean;
    delete?: boolean;
    active?: boolean;
    inactive?: boolean;
  };
  filter_values?: {
    parents?: Record<string, string>;
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

export default function LeadStatusesPage() {
  const qc = useQueryClient();
  const [searchInput, setSearchInput] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [addOpen, setAddOpen] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<LeadStatusRow | null>(null);

  useEffect(() => {
    const t = setTimeout(() => setDebouncedSearch(searchInput.trim()), 300);
    return () => clearTimeout(t);
  }, [searchInput]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, statusFilter, perPage]);

  const list = useQuery({
    queryKey: ['lead-statuses', 'datatable', page, perPage, debouncedSearch, statusFilter],
    placeholderData: keepPreviousData,
    queryFn: () =>
      api.post<DatatableResponse>(
        '/api/lead_statuses/datatable',
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
      api.post('/api/lead_statuses/status', { id, status: active }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['lead-statuses', 'datatable'] }),
  });

  const deleteRow = useMutation({
    mutationFn: (id: number) => api.delete(`/api/lead_statuses/${id}`),
    onSuccess: () => {
      setDeleteTarget(null);
      qc.invalidateQueries({ queryKey: ['lead-statuses', 'datatable'] });
    },
  });

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

  return (
    <div className="mx-auto max-w-[1400px] space-y-3 px-4 py-4 sm:px-6 lg:px-8">
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex items-baseline gap-2.5">
          <h1 className="text-2xl font-semibold tracking-tight">Lead Statuses</h1>
          {total > 0 && (
            <span className="text-[13px] text-fg-subtle nums-tabular">
              <span className="font-medium text-fg-muted">{total.toLocaleString()}</span> total
            </span>
          )}
        </div>
        <div className="ml-auto">
          <Button size="sm" onClick={() => setAddOpen(true)}>
            <Plus className="size-3.5" />
            <span className="hidden xs:inline sm:inline">Add status</span>
            <span className="xs:hidden sm:hidden">Add</span>
          </Button>
        </div>
      </div>

      {/* Command rail — same shape as Leads: search pill on the left,
          filter pills flow inline. Shared primitives come from
          '@/components/filters/filter-pill'. */}
      <div className="flex flex-wrap items-center gap-1.5">
        <div className="relative min-w-[220px] flex-1 basis-[260px]">
          <Search className="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-fg-subtle" aria-hidden />
          <input
            type="search"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder="Search by name…"
            aria-label="Search lead statuses"
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
                <TableHead className="w-[26%] pl-4 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Name</TableHead>
                <TableHead className="hidden w-[18%] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle lg:table-cell">Parent</TableHead>
                <TableHead className="w-[36%] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Flags</TableHead>
                <TableHead className="w-[12%] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Status</TableHead>
                <TableHead className="w-[140px] pr-3 text-end" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {list.isLoading && Array.from({ length: 6 }).map((_, i) => (
                <TableRow key={`sk-${i}`}>
                  <TableCell className="py-2.5 pl-4"><Skeleton className="h-4 w-full max-w-[200px]" /></TableCell>
                  <TableCell className="hidden py-2.5 lg:table-cell"><Skeleton className="h-4 w-20" /></TableCell>
                  <TableCell className="py-2.5"><Skeleton className="h-5 w-full max-w-[200px]" /></TableCell>
                  <TableCell className="py-2.5"><Skeleton className="h-5 w-20 rounded-full" /></TableCell>
                  <TableCell />
                </TableRow>
              ))}
              {!list.isLoading && rows.map((row) => (
                <TableRow key={row.id}>
                  <TableCell className="py-2.5 pl-4">
                    <div className="text-[13px] font-medium text-fg">{row.name}</div>
                  </TableCell>
                  <TableCell className="hidden py-2.5 text-[12.5px] text-fg-muted lg:table-cell">
                    {row.parent_id}
                  </TableCell>
                  <TableCell className="py-2.5">
                    <FlagBadges row={row} />
                  </TableCell>
                  <TableCell className="py-2.5">
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
            <div key={row.id} className="p-4">
              <div className="flex items-center justify-between gap-2">
                <div className="min-w-0">
                  <div className="truncate text-[14px] font-medium text-fg">{row.name}</div>
                  <div className="mt-0.5 text-[11.5px] text-fg-subtle">Parent: {row.parent_id}</div>
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
              <div className="mt-2">
                <FlagBadges row={row} />
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
              {hasFilters ? 'No lead statuses match your filters' : 'No lead statuses yet'}
            </h3>
            <p className="mt-1 max-w-xs text-[12.5px] text-fg-muted">
              {hasFilters
                ? 'Try clearing filters or adjusting your search.'
                : 'Add your first lead status to start tracking lead lifecycle stages.'}
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
            Couldn’t load lead statuses: {(list.error as Error).message}
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

      <LeadStatusFormDialog mode="create" open={addOpen} onOpenChange={setAddOpen} />
      <LeadStatusFormDialog
        mode="edit"
        statusId={editId ?? undefined}
        open={editId !== null}
        onOpenChange={(o) => !o && setEditId(null)}
      />

      <Dialog open={deleteTarget !== null} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <span className="flex size-8 items-center justify-center rounded-full bg-danger-soft text-danger">
                <AlertTriangle className="size-4" />
              </span>
              Delete lead status?
            </DialogTitle>
            <DialogDescription>
              {deleteTarget && (
                <>
                  This will permanently remove <span className="font-medium text-fg">{deleteTarget.name}</span>.
                  The server blocks deletion if any child statuses reference this one as their parent.
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
    </div>
  );
}

function FlagBadges({ row }: { row: LeadStatusRow }) {
  const flags: Array<{ key: string; label: string; on: boolean; tone: 'brand' | 'success' | 'warning' | 'danger' | 'neutral' }> = [
    { key: 'is_default', label: 'Default', on: row.is_default === 'Yes', tone: 'brand' },
    { key: 'is_booked', label: 'Booked', on: row.is_booked === 'Yes', tone: 'warning' },
    { key: 'is_arrived', label: 'Arrived', on: row.is_arrived === 'Yes', tone: 'warning' },
    { key: 'is_converted', label: 'Converted', on: row.is_converted === 'Yes', tone: 'success' },
    { key: 'is_junk', label: 'Junk', on: row.is_junk === 'Yes', tone: 'danger' },
  ];
  const active = flags.filter((f) => f.on);
  if (active.length === 0) {
    return <span className="text-[12px] text-fg-subtle">—</span>;
  }
  return (
    <div className="flex flex-wrap gap-1">
      {active.map((f) => (
        <Badge key={f.key} variant={f.tone}>{f.label}</Badge>
      ))}
    </div>
  );
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
