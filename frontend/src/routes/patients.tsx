import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router';
import { useMutation, useQuery, useQueryClient, keepPreviousData } from '@tanstack/react-query';
import {
  AlertTriangle,
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  Check,
  ChevronLeft,
  ChevronRight,
  Eye,
  Loader2,
  Pencil,
  Phone,
  Plus,
  Search,
  Sparkles,
  Tag,
  Trash2,
  Users,
  X,
} from 'lucide-react';
import { ComboPicker, FilterPill, RadioPicker } from '@/components/filters/filter-pill';
import { api, ApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { formatPhone, formatTableDate, initials } from '@/lib/format';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Select } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import { PatientFormDialog } from '@/components/patients/patient-form-dialog';
import type { PatientDatatableResponse } from '@/components/patients/types';

type SortField = 'name' | 'created_at' | 'phone';
type SortDir = 'asc' | 'desc';

const PAGE_SIZE_OPTIONS = [10, 25, 50, 100];

export default function PatientsPage() {
  const navigate = useNavigate();
  const qc = useQueryClient();

  const [searchInput, setSearchInput] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'inactive'>('all');
  /* Gender filter — server accepts `gender` as the raw enum value (1=Male,
     2=Female) per config/constants.gender_array. Empty string = no filter. */
  const [genderFilter, setGenderFilter] = useState<string>('');
  /* Membership filter — filtered by MembershipType id. The lookup options
     arrive on `filter_values.memberships` in the datatable response; we
     cache the first non-empty response so the pill still has labels after
     query-key changes. */
  const [membershipFilter, setMembershipFilter] = useState<string>('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(25);
  const [sortField, setSortField] = useState<SortField>('created_at');
  const [sortDir, setSortDir] = useState<SortDir>('desc');
  const [addOpen, setAddOpen] = useState(false);
  const [editPatientId, setEditPatientId] = useState<number | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<{ id: number; name: string } | null>(null);

  useEffect(() => {
    const t = setTimeout(() => setDebouncedSearch(searchInput.trim()), 300);
    return () => clearTimeout(t);
  }, [searchInput]);

  useEffect(() => {
    setPage(1);
  }, [debouncedSearch, statusFilter, genderFilter, membershipFilter, perPage]);

  const patients = useQuery({
    queryKey: ['patients', 'datatable', page, perPage, debouncedSearch, statusFilter, genderFilter, membershipFilter, sortField, sortDir],
    placeholderData: keepPreviousData,
    queryFn: () =>
      api.post<PatientDatatableResponse>(
        '/api/patients/datatable',
        {
          pagination: { page, perpage: perPage },
          query: {
            search: {
              // Backend filter keys are unprefixed — see
              // PatientService::applyOptimizedFilters. Earlier versions of
              // the SPA used `search_*` keys which silently no-op'd; this
              // is the canonical wire format.
              ...(debouncedSearch ? { name: debouncedSearch } : {}),
              ...(statusFilter !== 'all' ? { status: statusFilter === 'active' ? '1' : '0' } : {}),
              ...(genderFilter ? { gender: genderFilter } : {}),
              ...(membershipFilter ? { membership: membershipFilter } : {}),
            },
            sort: { field: sortField, sort: sortDir },
          },
        },
        { raw: true },
      ),
  });

  const onSort = (field: SortField) => {
    if (sortField === field) {
      setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortField(field);
      setSortDir('asc');
    }
  };

  const rows = patients.data?.data ?? [];
  const meta = patients.data?.meta;
  const total = meta?.total ?? 0;
  const totalPages = Math.max(1, Math.ceil(total / perPage));
  const hasFilters = !!(debouncedSearch || statusFilter !== 'all' || genderFilter || membershipFilter);

  /* MembershipType lookup — the datatable response ships a { name: id } map
     under filter_values.memberships. Cache the first non-empty map so the
     pill keeps resolving labels while a filter is in flight. */
  const filterValues = patients.data?.filter_values as
    | { memberships?: Record<string, number | string> }
    | undefined;
  const membershipOptions = Object.entries(filterValues?.memberships ?? {}).map(
    ([name, id]) => ({ id: String(id), name }),
  );
  const membershipLabel = membershipOptions.find((o) => o.id === membershipFilter)?.name;
  const canViewContact = patients.data?.permissions?.contact !== false;
  const canEdit = patients.data?.permissions?.edit !== false;
  const canDelete = patients.data?.permissions?.delete !== false;

  const clearFilters = () => {
    setSearchInput('');
    setStatusFilter('all');
    setGenderFilter('');
    setMembershipFilter('');
  };

  // DELETE — server returns an error if the patient has child records
  // (PatientService::delete line 351). We surface the message directly.
  const deleteMut = useMutation({
    mutationFn: (id: number) => api.delete(`/api/patients/${id}`),
    onSuccess: () => {
      setDeleteTarget(null);
      qc.invalidateQueries({ queryKey: ['patients', 'datatable'] });
    },
  });

  return (
    <div className="mx-auto max-w-[1400px] space-y-3 px-4 py-4 sm:px-6 lg:px-8">
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex items-baseline gap-2.5">
          <h1 className="text-2xl font-semibold tracking-tight">Patients</h1>
          {total > 0 && (
            <span className="text-[13px] text-fg-subtle nums-tabular">
              <span className="font-medium text-fg-muted">{total.toLocaleString()}</span> total
            </span>
          )}
        </div>
        <div className="ml-auto flex items-center gap-1.5">
          <div className="hidden h-5 w-px bg-hairline mr-1 sm:block" aria-hidden />
          <Button size="sm" onClick={() => setAddOpen(true)}>
            <Plus className="size-3.5" />
            <span className="hidden xs:inline sm:inline">Add patient</span>
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
            aria-label="Search patients"
            className="h-9 w-full rounded-lg bg-elevated pl-9 pr-2 text-[13px] text-fg shadow-xs ring-1 ring-inset ring-hairline placeholder:text-fg-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
          />
        </div>
        <FilterPill
          icon={Tag}
          label="Status"
          value={statusFilter === 'active' ? 'Active' : statusFilter === 'inactive' ? 'Inactive' : undefined}
          onClear={() => setStatusFilter('all')}
          popover={
            <RadioPicker
              title="Status"
              options={[
                { id: 'active', name: 'Active' },
                { id: 'inactive', name: 'Inactive' },
              ]}
              value={statusFilter === 'all' ? '' : statusFilter}
              onChange={(v) => setStatusFilter((v || 'all') as typeof statusFilter)}
            />
          }
        />
        <FilterPill
          icon={Users}
          label="Gender"
          value={genderFilter === '1' ? 'Male' : genderFilter === '2' ? 'Female' : undefined}
          onClear={() => setGenderFilter('')}
          popover={
            <RadioPicker
              title="Gender"
              options={[
                { id: '1', name: 'Male' },
                { id: '2', name: 'Female' },
              ]}
              value={genderFilter}
              onChange={setGenderFilter}
            />
          }
        />
        <FilterPill
          icon={Sparkles}
          label="Membership"
          value={membershipLabel}
          onClear={() => setMembershipFilter('')}
          popover={
            <ComboPicker
              title="Membership"
              options={membershipOptions}
              value={membershipFilter}
              onChange={setMembershipFilter}
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
        {/* Desktop table */}
        <div className="hidden md:block">
          <Table>
            <TableHeader>
              <TableRow className="border-b border-hairline">
                <TableHead className="w-[34%] pl-4">
                  <SortHeader label="Patient" field="name" current={sortField} dir={sortDir} onSort={onSort} />
                </TableHead>
                <TableHead className="w-[20%]">Phone</TableHead>
                <TableHead className="w-[12%]">Status</TableHead>
                <TableHead className="hidden w-[18%] lg:table-cell">Membership</TableHead>
                <TableHead className="w-[14%]">
                  <SortHeader label="Added" field="created_at" current={sortField} dir={sortDir} onSort={onSort} />
                </TableHead>
                <TableHead className="w-[100px] pr-3 text-end" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {patients.isLoading && Array.from({ length: 8 }).map((_, i) => (
                <TableRow key={`sk-${i}`}>
                  <TableCell className="py-2.5 pl-4"><Skeleton className="h-9 w-full max-w-[220px]" /></TableCell>
                  <TableCell className="py-2.5"><Skeleton className="h-4 w-32" /></TableCell>
                  <TableCell className="py-2.5"><Skeleton className="h-5 w-16 rounded-full" /></TableCell>
                  <TableCell className="hidden py-2.5 lg:table-cell"><Skeleton className="h-5 w-24 rounded-full" /></TableCell>
                  <TableCell className="py-2.5"><Skeleton className="h-4 w-16" /></TableCell>
                  <TableCell />
                </TableRow>
              ))}
              {!patients.isLoading && rows.map((p) => (
                <TableRow
                  key={p.id}
                  className="group cursor-pointer"
                  onClick={() => navigate(`/patients/${p.id}`)}
                >
                  <TableCell className="py-2.5 pl-4 relative before:absolute before:inset-y-0 before:left-0 before:w-[3px] before:bg-accent-cyan before:opacity-0 group-hover:before:opacity-100 before:transition-opacity">
                    <div className="flex items-center gap-3">
                      <Avatar className="size-8 shrink-0">
                        <AvatarFallback>{initials(p.name)}</AvatarFallback>
                      </Avatar>
                      <div className="min-w-0">
                        <div className="truncate text-[13px] font-medium text-fg">{p.name || 'Unnamed patient'}</div>
                        <div className="mt-0.5 text-[11px] text-fg-subtle nums-tabular">C-{p.id}</div>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell className="py-2.5 text-[12.5px] text-fg-muted nums-tabular">
                    {canViewContact ? (
                      p.phone ? (
                        <span className="inline-flex items-center gap-1">
                          <Phone className="size-3" /> {formatPhone(p.phone)}
                        </span>
                      ) : '—'
                    ) : (
                      <span className="text-fg-subtle italic">hidden</span>
                    )}
                  </TableCell>
                  <TableCell className="py-2.5">
                    {p.active ? (
                      <Badge variant="success" dot>Active</Badge>
                    ) : (
                      <Badge variant="danger" dot>Inactive</Badge>
                    )}
                  </TableCell>
                  <TableCell className="hidden py-2.5 lg:table-cell">
                    {p.membership?.code ? (
                      <Badge variant={p.membership.type?.toLowerCase().includes('gold') ? 'warning' : 'brand'}>
                        {p.membership.type || 'Member'}
                      </Badge>
                    ) : (
                      <span className="text-[12px] text-fg-subtle">—</span>
                    )}
                  </TableCell>
                  <TableCell className="py-2.5 text-[12.5px] text-fg-subtle">
                    {(() => {
                      const d = formatTableDate(p.created_at);
                      return (
                        <div className="leading-tight">
                          <div className="whitespace-nowrap text-fg">{d.date}</div>
                          {d.time && <div className="whitespace-nowrap text-[11px] text-fg-subtle">{d.time}</div>}
                        </div>
                      );
                    })()}
                  </TableCell>
                  <TableCell className="py-2.5 pr-3 text-end" onClick={(e) => e.stopPropagation()}>
                    <div className="flex items-center justify-end gap-0.5">
                      <RowIconAction icon={Eye} label="View patient" onClick={() => navigate(`/patients/${p.id}`)} />
                      {canEdit && <RowIconAction icon={Pencil} label="Edit" onClick={() => setEditPatientId(p.id)} />}
                      {canDelete && <RowIconAction icon={Trash2} label="Delete" destructive onClick={() => setDeleteTarget({ id: p.id, name: p.name })} />}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>

        {/* Mobile cards */}
        <div className="divide-y divide-hairline/70 md:hidden">
          {patients.isLoading && Array.from({ length: 4 }).map((_, i) => (
            <div key={`m-sk-${i}`} className="p-4">
              <Skeleton className="h-12 w-full" />
            </div>
          ))}
          {!patients.isLoading && rows.map((p) => (
            <button
              key={p.id}
              type="button"
              onClick={() => navigate(`/patients/${p.id}`)}
              className="flex w-full items-center gap-3 p-4 text-start transition-colors hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"
            >
              <Avatar className="size-10 shrink-0">
                <AvatarFallback>{initials(p.name)}</AvatarFallback>
              </Avatar>
              <div className="min-w-0 flex-1">
                <div className="flex items-center justify-between gap-2">
                  <div className="truncate text-[14px] font-medium text-fg">{p.name || 'Unnamed patient'}</div>
                  {p.active ? (
                    <Badge variant="success" dot>Active</Badge>
                  ) : (
                    <Badge variant="danger" dot>Inactive</Badge>
                  )}
                </div>
                <div className="mt-0.5 flex items-center gap-2 text-[12px] text-fg-subtle">
                  <span className="nums-tabular">C-{p.id}</span>
                  {canViewContact && p.phone && (
                    <span className="inline-flex items-center gap-1 nums-tabular">
                      · <Phone className="size-3" /> {formatPhone(p.phone)}
                    </span>
                  )}
                </div>
              </div>
            </button>
          ))}
        </div>

        {!patients.isLoading && rows.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-surface text-fg-subtle">
              <Search className="size-5" />
            </div>
            <h3 className="mt-4 text-[14px] font-medium text-fg">
              {hasFilters ? 'No patients match your filters' : 'No patients yet'}
            </h3>
            <p className="mt-1 max-w-xs text-[12.5px] text-fg-muted">
              {hasFilters ? 'Try clearing filters or adjusting your search.' : 'Add your first patient to get started.'}
            </p>
            {hasFilters && (
              <Button size="sm" variant="secondary" className="mt-4" onClick={clearFilters}>
                Clear filters
              </Button>
            )}
          </div>
        )}

        {patients.error && (
          <div className="border-t border-hairline bg-danger-soft px-4 py-3 text-[12.5px] text-danger">
            Couldn't load patients: {(patients.error as Error).message}
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
              <Button variant="secondary" size="sm" disabled={page <= 1 || patients.isFetching} onClick={() => setPage((p) => Math.max(1, p - 1))}>
                <ChevronLeft className="size-3.5" /> Prev
              </Button>
              <div className="px-2 text-[12.5px] text-fg-muted">
                Page <span className="font-medium text-fg">{page}</span> of {totalPages}
              </div>
              <Button variant="secondary" size="sm" disabled={page >= totalPages || patients.isFetching} onClick={() => setPage((p) => p + 1)}>
                Next <ChevronRight className="size-3.5" />
              </Button>
            </div>
          </div>
        )}
      </Card>

      {/* Add / Edit modals */}
      <PatientFormDialog mode="create" open={addOpen} onOpenChange={setAddOpen} onSaved={(id) => navigate(`/patients/${id}`)} />
      <PatientFormDialog
        mode="edit"
        patientId={editPatientId ?? undefined}
        open={editPatientId !== null}
        onOpenChange={(o) => !o && setEditPatientId(null)}
      />

      {/* Delete confirm — server may reject with a list of blocking child records */}
      <Dialog open={deleteTarget !== null} onOpenChange={(o) => !o && setDeleteTarget(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <span className="flex size-8 items-center justify-center rounded-full bg-danger-soft text-danger">
                <AlertTriangle className="size-4" />
              </span>
              Delete patient?
            </DialogTitle>
            <DialogDescription>
              {deleteTarget?.name && <span className="font-medium text-fg">{deleteTarget.name}</span>} will be soft-deleted.{' '}
              <span className="font-medium text-danger">This cannot be undone.</span>{' '}
              The server will reject this if related leads or appointments exist — you'll see the reason here.
            </DialogDescription>
          </DialogHeader>
          {deleteMut.error && (
            <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
              {(deleteMut.error as ApiError).message}
            </div>
          )}
          <DialogFooter>
            <Button variant="secondary" size="sm" onClick={() => setDeleteTarget(null)} disabled={deleteMut.isPending}>
              Cancel
            </Button>
            <Button variant="danger" size="sm" onClick={() => deleteTarget && deleteMut.mutate(deleteTarget.id)} disabled={deleteMut.isPending}>
              {deleteMut.isPending && <Loader2 className="size-4 animate-spin" />}
              Delete
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {(deleteMut.isSuccess || deleteMut.isError) && (
        <FlashFeedback type={deleteMut.isSuccess ? 'success' : 'danger'} onDismiss={() => deleteMut.reset()}>
          {deleteMut.isSuccess ? 'Patient deleted' : null}
        </FlashFeedback>
      )}
    </div>
  );
}

function SortHeader({
  label,
  field,
  current,
  dir,
  onSort,
}: {
  label: string;
  field: SortField;
  current: SortField;
  dir: SortDir;
  onSort: (f: SortField) => void;
}) {
  const active = current === field;
  const Icon = !active ? ArrowUpDown : dir === 'asc' ? ArrowUp : ArrowDown;
  return (
    <button
      type="button"
      onClick={() => onSort(field)}
      className={cn(
        'group/sort -mx-1 inline-flex items-center gap-1 rounded px-1 py-0.5 transition-colors',
        'hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue',
        active ? 'text-fg' : 'text-fg-subtle',
      )}
    >
      <span className="text-[11px] font-semibold uppercase tracking-wide">{label}</span>
      <Icon className={cn('size-3 transition-opacity', active ? 'opacity-100' : 'opacity-0 group-hover/sort:opacity-60')} />
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

function FlashFeedback({ type, children, onDismiss }: { type: 'success' | 'danger'; children: React.ReactNode; onDismiss: () => void }) {
  useEffect(() => {
    const t = setTimeout(onDismiss, 3000);
    return () => clearTimeout(t);
  }, [onDismiss]);
  if (!children) return null;
  return (
    <div
      role="status"
      className={cn(
        'fixed bottom-4 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2 rounded-lg px-3 py-2 text-[13px] shadow-md ring-1 ring-inset',
        type === 'success' ? 'bg-success-soft text-success ring-success/20' : 'bg-danger-soft text-danger ring-danger/20',
      )}
    >
      <Check className="size-3.5" /> {children}
    </div>
  );
}
