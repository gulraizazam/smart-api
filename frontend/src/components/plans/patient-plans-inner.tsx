import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { CalendarRange, ChevronDown, Eye, MapPin, Package, Plus, Tag, Ticket, X } from 'lucide-react';
import { api } from '@/lib/api';
import { cn } from '@/lib/cn';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { FilterPill, RadioPicker } from '@/components/filters/filter-pill';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { PlanDetailDialog } from './plan-detail-dialog';
import { PlanCreateDialog } from './plan-create-dialog';
import { PlanMembershipCreateDialog } from './plan-membership-create-dialog';

/**
 * Patient-scoped plans view used inside the Patient Detail page.
 *
 * Pulls from:
 *   GET  /api/plans-optimized/statistics/{patient_id}
 *   POST /api/plans-optimized/datatable/{patient_id}
 *   GET  /api/plans-optimized/lookup-data/{patient_id}    (filter options)
 *
 * Read-only. Row click opens the shared PlanDetailDialog. Create/edit
 * stays on the legacy admin until a follow-up PR.
 */

type PlanRow = {
  id: number;
  package_id: string;
  plan_name: string;
  total: string;
  balance: string;
  cash_receive: string;
  active: boolean;
  status: 'Active' | 'Inactive';
  plan_type: 'plan' | 'bundle' | 'membership';
  date: string;
  created_at: string;
  membership_info: string | null;
};

type Stats = {
  total_plans: number;
  active_plans: number;
  total_amount: string;
  cash_received: string;
  refunded_plans: number;
};

type DatatableResp = {
  data: PlanRow[];
  meta: { total: number };
  filter_values?: {
    locations?: Record<string, string>;
    plans?: Record<string, string>;
  };
};

type LookupResp = {
  locations?: Record<string, string>;
  packages?: Record<string, string>;
  statuses?: Record<string, string>;
};

export function PatientPlansInner({
  patientId,
  patientName,
}: {
  patientId: number;
  patientName?: string;
}) {
  const [detailId, setDetailId] = useState<number | null>(null);
  const [createSubtype, setCreateSubtype] = useState<'plan' | 'bundle' | 'membership' | null>(null);
  const [newMenuOpen, setNewMenuOpen] = useState(false);
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [planIdFilter, setPlanIdFilter] = useState<string>('');
  const [locationFilter, setLocationFilter] = useState<string>('');
  const [createdFrom, setCreatedFrom] = useState<string>('');
  const [createdTo, setCreatedTo] = useState<string>('');

  const createdAt = createdFrom && createdTo ? `${createdFrom} - ${createdTo}` : '';

  useEffect(() => {
    // reserved for future pagination reset on filter change
  }, [statusFilter, planIdFilter, locationFilter, createdAt]);

  const stats = useQuery({
    queryKey: ['plans', 'patient-stats', patientId],
    queryFn: () => api.get<Stats>(`/api/plans-optimized/statistics/${patientId}`),
  });

  const lookup = useQuery({
    queryKey: ['plans', 'patient-lookup', patientId],
    queryFn: () => api.get<LookupResp>(`/api/plans-optimized/lookup-data/${patientId}`),
  });

  const list = useQuery({
    queryKey: ['plans', 'patient-list', patientId, statusFilter, planIdFilter, locationFilter, createdAt],
    queryFn: () =>
      api.post<DatatableResp>(
        `/api/plans-optimized/datatable/${patientId}`,
        {
          pagination: { page: 1, perpage: 50 },
          query: {
            search: {
              status: statusFilter || undefined,
              package_id: planIdFilter || undefined,
              location_id: locationFilter || undefined,
              created_at: createdAt || undefined,
            },
          },
        },
        { raw: true },
      ),
  });

  const rows = list.data?.data ?? [];
  const locationOptions = lookup.data?.locations ?? list.data?.filter_values?.locations ?? {};
  const planOptions = lookup.data?.packages ?? {};
  const hasFilters = !!(statusFilter || planIdFilter || locationFilter || createdAt);

  const typeTone = (t: PlanRow['plan_type']) =>
    t === 'membership' ? 'brand' : t === 'bundle' ? 'warning' : 'neutral';

  const clearFilters = () => {
    setStatusFilter('');
    setPlanIdFilter('');
    setLocationFilter('');
    setCreatedFrom('');
    setCreatedTo('');
  };

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-end">
        <div className="relative">
          <Button size="sm" onClick={() => setNewMenuOpen((v) => !v)}>
            <Plus className="size-3.5" /> New
            <ChevronDown className="size-3.5" />
          </Button>
          {newMenuOpen && (
            <>
              <div
                className="fixed inset-0 z-10"
                onClick={() => setNewMenuOpen(false)}
              />
              <div className="absolute right-0 z-20 mt-1 w-[180px] rounded-lg bg-elevated p-1 shadow-lg ring-1 ring-inset ring-hairline">
                {[
                  { id: 'plan', label: 'Plan (services)', icon: Plus },
                  { id: 'bundle', label: 'Bundle', icon: Package },
                  { id: 'membership', label: 'Membership', icon: Ticket },
                ].map((opt) => (
                  <button
                    key={opt.id}
                    type="button"
                    onClick={() => {
                      setNewMenuOpen(false);
                      setCreateSubtype(opt.id as 'plan' | 'bundle' | 'membership');
                    }}
                    className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-[12.5px] transition-colors hover:bg-surface"
                  >
                    <opt.icon className="size-3.5 text-fg-subtle" />
                    <span>{opt.label}</span>
                  </button>
                ))}
              </div>
            </>
          )}
        </div>
      </div>

      {/* Stats strip */}
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
        <StatCell label="Plans" value={stats.data ? String(stats.data.total_plans) : '…'} loading={stats.isLoading} />
        <StatCell label="Active" value={stats.data ? String(stats.data.active_plans) : '…'} loading={stats.isLoading} />
        <StatCell label="Total" value={stats.data?.total_amount ?? '…'} loading={stats.isLoading} highlight />
        <StatCell label="Cash received" value={stats.data?.cash_received ?? '…'} loading={stats.isLoading} tone="success" />
        <StatCell label="Refunded" value={stats.data ? String(stats.data.refunded_plans) : '…'} loading={stats.isLoading} tone="warning" />
      </div>

      {/* Filter shelf */}
      <div className="flex flex-wrap items-center gap-1.5">
        {Object.keys(planOptions).length > 0 && (
          <FilterPill
            icon={Tag}
            label="Plan"
            value={planOptions[planIdFilter] ?? undefined}
            onClear={() => setPlanIdFilter('')}
            popoverWidth="min-w-[260px]"
            popover={
              <RadioPicker
                title="Plan"
                options={Object.entries(planOptions).map(([id, name]) => ({ id, name }))}
                value={planIdFilter}
                onChange={setPlanIdFilter}
              />
            }
          />
        )}
        {Object.keys(locationOptions).length > 0 && (
          <FilterPill
            icon={MapPin}
            label="Centre"
            value={locationOptions[locationFilter] ?? undefined}
            onClear={() => setLocationFilter('')}
            popoverWidth="min-w-[260px]"
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
        {list.isLoading && (
          <div className="p-4">
            <Skeleton className="h-40 w-full" />
          </div>
        )}
        {!list.isLoading && rows.length === 0 && (
          <div className="flex flex-col items-center justify-center py-10 text-center text-[12.5px] text-fg-muted">
            {hasFilters ? 'No plans match your filters.' : 'No plans recorded for this patient.'}
          </div>
        )}
        {!list.isLoading && rows.length > 0 && (
          <Table>
            <TableHeader>
              <TableRow className="border-b border-hairline">
                <TableHead className="w-[80px] pl-4 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">#</TableHead>
                <TableHead className="text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Plan</TableHead>
                <TableHead className="w-[100px] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Type</TableHead>
                <TableHead className="w-[110px] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle text-right">Total</TableHead>
                <TableHead className="hidden w-[100px] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle text-right sm:table-cell">Balance</TableHead>
                <TableHead className="w-[110px] text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">Status</TableHead>
                <TableHead className="w-[40px] pr-3" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((r) => {
                const balanceNum = Number(String(r.balance).replace(/,/g, ''));
                return (
                  <TableRow key={r.id} className="group cursor-pointer" onClick={() => setDetailId(r.id)}>
                    <TableCell className="py-2.5 pl-4 nums-tabular text-[12.5px] text-fg-muted">#{r.package_id}</TableCell>
                    <TableCell className="py-2.5">
                      <div className="truncate text-[13px] text-fg">{r.plan_name}</div>
                      {r.membership_info && (
                        <div className="mt-0.5 truncate text-[11.5px] text-brand-blue">{r.membership_info}</div>
                      )}
                    </TableCell>
                    <TableCell className="py-2.5">
                      <Badge variant={typeTone(r.plan_type)}>{r.plan_type}</Badge>
                    </TableCell>
                    <TableCell className="py-2.5 text-right nums-tabular text-[13px] font-medium text-fg">{r.total}</TableCell>
                    <TableCell
                      className={cn(
                        'hidden py-2.5 text-right nums-tabular text-[12.5px] sm:table-cell',
                        balanceNum > 0 ? 'text-warning font-medium' : 'text-fg-subtle',
                      )}
                    >
                      {r.balance}
                    </TableCell>
                    <TableCell className="py-2.5">
                      {r.active ? <Badge variant="success" dot>Active</Badge> : <Badge variant="neutral" dot>Inactive</Badge>}
                    </TableCell>
                    <TableCell className="py-2.5 pr-3 text-end" onClick={(e) => e.stopPropagation()}>
                      <button
                        type="button"
                        aria-label="View plan detail"
                        title="View detail"
                        onClick={() => setDetailId(r.id)}
                        className="inline-flex size-7 items-center justify-center rounded-md text-fg-subtle transition-colors hover:bg-surface hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
                      >
                        <Eye className="size-3.5" />
                      </button>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        )}
      </Card>

      <PlanDetailDialog
        planId={detailId ?? undefined}
        open={detailId !== null}
        onOpenChange={(o) => !o && setDetailId(null)}
      />

      <PlanCreateDialog
        open={createSubtype === 'plan' || createSubtype === 'bundle'}
        onOpenChange={(o) => !o && setCreateSubtype(null)}
        lockedPatient={{ id: patientId, name: patientName ?? `Patient #${patientId}` }}
        subtype={createSubtype === 'bundle' ? 'bundle' : 'plan'}
      />
      <PlanMembershipCreateDialog
        open={createSubtype === 'membership'}
        onOpenChange={(o) => !o && setCreateSubtype(null)}
        lockedPatient={{ id: patientId, name: patientName ?? `Patient #${patientId}` }}
      />
    </div>
  );
}

function StatCell({
  label,
  value,
  loading,
  tone = 'default',
  highlight,
}: {
  label: string;
  value: string;
  loading?: boolean;
  tone?: 'default' | 'success' | 'warning';
  highlight?: boolean;
}) {
  return (
    <div className="rounded-lg bg-elevated px-3 py-2 ring-1 ring-inset ring-hairline">
      <div className="text-[10.5px] font-semibold uppercase tracking-wider text-fg-subtle">{label}</div>
      {loading ? (
        <Skeleton className="mt-1 h-5 w-16" />
      ) : (
        <div
          className={cn(
            'mt-0.5 nums-tabular truncate',
            highlight ? 'text-[15px] font-semibold' : 'text-[13px] font-medium',
            tone === 'success' && 'text-success',
            tone === 'warning' && 'text-warning',
            tone === 'default' && 'text-fg',
          )}
        >
          {value}
        </div>
      )}
    </div>
  );
}
