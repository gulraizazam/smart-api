import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { FileText, History, MessageSquare, Pencil, RotateCcw, UserCog } from 'lucide-react';
import { api } from '@/lib/api';
import { cn } from '@/lib/cn';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { PlanSmsLogsDialog } from './plan-sms-logs-dialog';
import { PlanCashEditDialog, type PlanCashEditPermissions } from './plan-cash-edit-dialog';
import { PlanSoldByDialog } from './plan-sold-by-dialog';
import { PlanRefundDialog } from './plan-refund-dialog';

/**
 * Read-only plan detail (legacy parity). Source: `GET /api/plans/display/{id}`.
 *
 * Layout mirrors the Blade display modal:
 *   - Summary strip (patient, centre, membership, totals)
 *   - Services table: Service, Type, Regular, Discount (name + value),
 *                     Subtotal, Tax, Total, Consumed, Consumed At, Sold By
 *   - Payment history
 *   - Footer actions: SMS logs, Print/PDF, Close
 *
 * Create/edit stays in the legacy admin until the follow-up PR.
 */

type Named = { id: number; name: string };

type PackageAdvance = {
  id: number;
  cash_flow: 'in' | 'out';
  cash_amount: number;
  is_refund: boolean;
  is_adjustment: boolean;
  is_cancel: boolean;
  is_setteled: boolean;
  payment_mode_id: number;
  created_at_formated: string;
  paymentmode?: Named | null;
};

type PackageServiceLine = {
  id: number;
  service_id: number;
  is_consumed: boolean;
  consumed_at: string | null;
  orignal_price: number;
  price: number;
  actual_price: number;
  tax_percentage: number;
  tax_price: number;
  tax_including_price: number;
  service?: (Named & { color?: string | null }) | null;
  sold_by?: Named | null;
};

type PackageBundle = {
  id: number;
  service_price: number;
  net_amount: number;
  tax_percentage: number;
  tax_price: number;
  tax_including_price: number;
  source_type: string | null;
  packageservice: PackageServiceLine[];
  bundle?: Named | null;
  service?: Named | null;
  discount?: (Named & { type?: string; amount?: string | number }) | null;
};

type DisplayData = {
  package: {
    id: number;
    plan_name: string | null;
    name: string;
    total_price: number;
    plan_type: 'plan' | 'bundle' | 'membership' | string;
    active: boolean | number;
    is_refund: boolean | number;
    session_count?: number;
    created_at?: string;
    patient_id?: number;
    location_id?: number;
    user?: Named | null;
    location?: Named | null;
    appointment?: { id: number } | null;
  };
  packagebundles: PackageBundle[];
  packageservices: PackageServiceLine[];
  packageadvances: PackageAdvance[];
  paymentmodes: Record<string, string>;
  grand_total: number;
  membership: string | null;
};

interface Props {
  planId?: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Permissions surfaced from the parent datatable response. */
  canViewSmsLogs?: boolean;
  canPrint?: boolean;
  canEditCash?: boolean;
  canEditSoldBy?: boolean;
  canRefund?: boolean;
  canViewLog?: boolean;
  cashPermissions?: PlanCashEditPermissions;
}

type FlatServiceRow = PackageServiceLine & {
  bundleDiscount: PackageBundle['discount'] | null;
  sourceType: string | null;
};

export function PlanDetailDialog({
  planId,
  open,
  onOpenChange,
  canViewSmsLogs = true,
  canPrint = true,
  canEditCash = true,
  canEditSoldBy = true,
  canRefund = true,
  canViewLog = true,
  cashPermissions,
}: Props) {
  const [smsOpen, setSmsOpen] = useState(false);
  const [cashEditId, setCashEditId] = useState<number | null>(null);
  const [soldByServiceId, setSoldByServiceId] = useState<number | null>(null);
  const [refundOpen, setRefundOpen] = useState(false);

  const detail = useQuery({
    queryKey: ['plans', 'detail', planId],
    queryFn: () => api.get<DisplayData>(`/api/plans/display/${planId}`),
    enabled: open && !!planId,
  });

  const d = detail.data;
  const pkg = d?.package;
  const advances = d?.packageadvances ?? [];

  // Flatten packagebundles → per-service rows so we can show bundle-level
  // discount alongside each service line (legacy render order).
  const flatServices = useMemo<FlatServiceRow[]>(() => {
    if (!d) return [];
    const byServiceId = new Map<number, FlatServiceRow>();
    for (const pb of d.packagebundles) {
      for (const svc of pb.packageservice ?? []) {
        byServiceId.set(svc.id, {
          ...svc,
          bundleDiscount: pb.discount ?? null,
          sourceType: pb.source_type ?? null,
        });
      }
    }
    // Ensure flat service list still surfaces rows that weren't nested.
    for (const svc of d.packageservices) {
      if (!byServiceId.has(svc.id)) {
        byServiceId.set(svc.id, { ...svc, bundleDiscount: null, sourceType: null });
      }
    }
    return [...byServiceId.values()];
  }, [d]);

  const cashIn = advances.filter((a) => a.cash_flow === 'in').reduce((s, a) => s + Number(a.cash_amount || 0), 0);
  const cashOut = advances.filter((a) => a.cash_flow === 'out').reduce((s, a) => s + Number(a.cash_amount || 0), 0);
  const balance = Number(d?.grand_total || 0) - cashIn + cashOut;

  const fmt = (n: number) => n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  const handlePrint = () => {
    if (!planId) return;
    window.open(`/api/packages/pdf/${planId}`, '_blank', 'noopener');
  };

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-5xl" aria-describedby={undefined}>
          <DialogHeader>
            <DialogTitle className="flex flex-wrap items-center gap-2">
              <span>Plan #{pkg?.name ?? planId}</span>
              {pkg?.plan_type && (
                <Badge variant={pkg.plan_type === 'membership' ? 'brand' : pkg.plan_type === 'bundle' ? 'warning' : 'neutral'}>
                  {String(pkg.plan_type)}
                </Badge>
              )}
              {pkg?.is_refund ? <Badge variant="danger">Refunded</Badge> : null}
              {d?.membership && (
                <Badge variant="brand" title={d.membership}>
                  <span className="truncate max-w-[200px]">{d.membership}</span>
                </Badge>
              )}
            </DialogTitle>
          </DialogHeader>

          <div className="max-h-[68vh] space-y-4 overflow-y-auto pr-1">
            {detail.isLoading && <Skeleton className="h-40 w-full" />}
            {detail.error && (
              <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
                Couldn't load plan: {(detail.error as Error).message}
              </div>
            )}
            {d && pkg && (
              <>
                {/* Summary grid */}
                <section className="grid grid-cols-2 gap-3 rounded-lg bg-surface p-3 text-[12.5px] sm:grid-cols-4">
                  <SummaryCell label="Patient" value={pkg.user?.name ?? '—'} />
                  <SummaryCell label="Centre" value={pkg.location?.name ?? '—'} />
                  <SummaryCell label="Sessions" value={pkg.session_count != null ? String(pkg.session_count) : '—'} />
                  <SummaryCell label="Plan name" value={pkg.plan_name ?? '—'} />
                  <SummaryCell label="Total" value={fmt(Number(d.grand_total))} highlight />
                  <SummaryCell label="Cash received" value={fmt(cashIn)} tone="success" />
                  <SummaryCell label="Settled / refunded" value={fmt(cashOut)} tone="warning" />
                  <SummaryCell label="Balance" value={fmt(balance)} tone={balance > 0 ? 'warning' : 'default'} />
                </section>

                {/* Services */}
                <section>
                  <h3 className="mb-2 text-[10.5px] font-semibold uppercase tracking-wider text-fg-subtle">
                    Services <span className="text-fg-muted">· {flatServices.length}</span>
                  </h3>
                  {flatServices.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-hairline px-3 py-4 text-center text-[12.5px] text-fg-muted">
                      No services on this plan.
                    </div>
                  ) : (
                    <div className="overflow-x-auto rounded-lg ring-1 ring-inset ring-hairline">
                      <table className="w-full min-w-[900px] border-separate border-spacing-0 text-[12.5px]">
                        <thead>
                          <tr className="bg-surface">
                            <Th>Service</Th>
                            <Th className="w-[90px]">Type</Th>
                            <Th className="w-[90px] text-right">Regular</Th>
                            <Th className="w-[150px]">Discount</Th>
                            <Th className="w-[90px] text-right">Subtotal</Th>
                            <Th className="w-[80px] text-right">Tax</Th>
                            <Th className="w-[100px] text-right">Total</Th>
                            <Th className="w-[100px]">Consumed</Th>
                            <Th className="w-[110px]">Sold by</Th>
                          </tr>
                        </thead>
                        <tbody className="bg-elevated">
                          {flatServices.map((s) => {
                            const discountDelta = Number(s.orignal_price) - Number(s.price);
                            return (
                              <tr key={s.id} className="border-t border-hairline/70">
                                <Td>
                                  <div className="truncate text-fg">{s.service?.name ?? `Service #${s.service_id}`}</div>
                                </Td>
                                <Td>
                                  {s.sourceType ? (
                                    <Badge variant={s.sourceType === 'membership' ? 'brand' : s.sourceType === 'bundle' ? 'warning' : 'neutral'}>
                                      {s.sourceType}
                                    </Badge>
                                  ) : (
                                    <span className="text-fg-subtle">—</span>
                                  )}
                                </Td>
                                <Td className="text-right nums-tabular text-fg-muted">{fmt(Number(s.orignal_price))}</Td>
                                <Td>
                                  {s.bundleDiscount?.name ? (
                                    <div className="min-w-0">
                                      <div className="truncate text-fg">{s.bundleDiscount.name}</div>
                                      {discountDelta > 0 && (
                                        <div className="text-[11px] text-success nums-tabular">−{fmt(discountDelta)}</div>
                                      )}
                                    </div>
                                  ) : discountDelta > 0 ? (
                                    <span className="nums-tabular text-success">−{fmt(discountDelta)}</span>
                                  ) : (
                                    <span className="text-fg-subtle">—</span>
                                  )}
                                </Td>
                                <Td className="text-right nums-tabular">{fmt(Number(s.actual_price ?? s.price))}</Td>
                                <Td className="text-right nums-tabular text-fg-muted">
                                  {s.tax_percentage ? `${fmt(Number(s.tax_price))}` : '—'}
                                </Td>
                                <Td className="text-right nums-tabular font-medium text-fg">{fmt(Number(s.tax_including_price ?? s.price))}</Td>
                                <Td>
                                  {s.is_consumed ? (
                                    <div>
                                      <Badge variant="success">Consumed</Badge>
                                      {s.consumed_at && (
                                        <div className="mt-0.5 text-[11px] text-fg-subtle">
                                          {new Date(s.consumed_at).toLocaleDateString()}
                                        </div>
                                      )}
                                    </div>
                                  ) : (
                                    <Badge variant="neutral">Pending</Badge>
                                  )}
                                </Td>
                                <Td>
                                  <div className="flex items-center gap-1">
                                    <span className="truncate text-fg-muted">{s.sold_by?.name ?? '—'}</span>
                                    {canEditSoldBy && !s.is_consumed && (
                                      <button
                                        type="button"
                                        aria-label="Reassign sold by"
                                        title="Reassign sold by"
                                        onClick={() => setSoldByServiceId(s.id)}
                                        className="ml-auto inline-flex size-6 items-center justify-center rounded-md text-fg-subtle transition-colors hover:bg-surface hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
                                      >
                                        <UserCog className="size-3" />
                                      </button>
                                    )}
                                  </div>
                                </Td>
                              </tr>
                            );
                          })}
                        </tbody>
                      </table>
                    </div>
                  )}
                </section>

                {/* Payment history */}
                <section>
                  <h3 className="mb-2 text-[10.5px] font-semibold uppercase tracking-wider text-fg-subtle">
                    Payment history <span className="text-fg-muted">· {advances.length}</span>
                  </h3>
                  {advances.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-hairline px-3 py-4 text-center text-[12.5px] text-fg-muted">
                      No payments recorded.
                    </div>
                  ) : (
                    <ul className="divide-y divide-hairline/60 rounded-lg ring-1 ring-inset ring-hairline">
                      {advances.map((a) => (
                        <li key={a.id} className="flex items-center justify-between gap-3 px-3 py-2 text-[12.5px]">
                          <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-1.5">
                              <Badge variant={a.cash_flow === 'in' ? 'success' : 'warning'}>
                                {a.cash_flow === 'in' ? 'Cash in' : 'Cash out'}
                              </Badge>
                              {a.is_refund && <Badge variant="danger">Refund</Badge>}
                              {a.is_adjustment && <Badge variant="neutral">Adjustment</Badge>}
                              {a.is_setteled && <Badge variant="brand">Settled</Badge>}
                              {a.is_cancel && <Badge variant="danger">Cancelled</Badge>}
                            </div>
                            <div className="mt-0.5 text-[11.5px] text-fg-subtle">
                              {a.paymentmode?.name ?? 'Unknown payment mode'} · {a.created_at_formated}
                            </div>
                          </div>
                          <div className="flex shrink-0 items-center gap-1">
                            <div
                              className={cn(
                                'text-right nums-tabular font-medium',
                                a.cash_flow === 'in' ? 'text-success' : 'text-warning',
                              )}
                            >
                              {a.cash_flow === 'in' ? '+' : '−'}
                              {fmt(Number(a.cash_amount))}
                            </div>
                            {canEditCash && a.cash_flow === 'in' && !a.is_cancel && !a.is_setteled && (
                              <button
                                type="button"
                                aria-label="Edit payment"
                                title="Edit payment"
                                onClick={() => setCashEditId(a.id)}
                                className="inline-flex size-6 items-center justify-center rounded-md text-fg-subtle transition-colors hover:bg-surface hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
                              >
                                <Pencil className="size-3" />
                              </button>
                            )}
                          </div>
                        </li>
                      ))}
                    </ul>
                  )}
                </section>
              </>
            )}
          </div>

          <DialogFooter className="gap-2 sm:justify-between">
            <div className="flex flex-wrap gap-2">
              {canViewSmsLogs && planId && (
                <Button type="button" variant="secondary" size="sm" onClick={() => setSmsOpen(true)}>
                  <MessageSquare className="size-3.5" />
                  SMS logs
                </Button>
              )}
              {canPrint && planId && (
                <Button type="button" variant="secondary" size="sm" onClick={handlePrint}>
                  <FileText className="size-3.5" />
                  Print / PDF
                </Button>
              )}
              {canRefund && planId && advances.some((a) => a.cash_flow === 'in' && !a.is_cancel) && (
                <Button type="button" variant="secondary" size="sm" onClick={() => setRefundOpen(true)}>
                  <RotateCcw className="size-3.5" />
                  Refund / Settle
                </Button>
              )}
              {canViewLog && planId && (
                <Button type="button" variant="secondary" size="sm" asChild>
                  <a
                    href={
                      // Build the SPA URL respecting BASE_URL (/admin-v2 in prod, / in dev).
                      (import.meta.env.BASE_URL.replace(/\/$/, '') || '') +
                      `/plans/log/${planId}`
                    }
                  >
                    <History className="size-3.5" />
                    Activity log
                  </a>
                </Button>
              )}
            </div>
            <Button type="button" variant="secondary" size="sm" onClick={() => onOpenChange(false)}>
              Close
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PlanSmsLogsDialog planId={planId} open={smsOpen} onOpenChange={setSmsOpen} />

      {planId && (
        <PlanCashEditDialog
          packageId={planId}
          advanceId={cashEditId}
          open={cashEditId !== null}
          onOpenChange={(o) => !o && setCashEditId(null)}
          permissions={cashPermissions}
        />
      )}

      {planId && pkg?.location_id != null && (
        <PlanSoldByDialog
          packageId={planId}
          packageServiceId={soldByServiceId}
          locationId={pkg.location_id}
          open={soldByServiceId !== null}
          onOpenChange={(o) => !o && setSoldByServiceId(null)}
        />
      )}

      <PlanRefundDialog
        planId={planId ?? null}
        open={refundOpen}
        onOpenChange={setRefundOpen}
      />
    </>
  );
}

function SummaryCell({
  label,
  value,
  highlight,
  tone = 'default',
}: {
  label: string;
  value: string;
  highlight?: boolean;
  tone?: 'default' | 'success' | 'warning';
}) {
  return (
    <div>
      <div className="text-[10.5px] font-semibold uppercase tracking-wider text-fg-subtle">{label}</div>
      <div
        className={cn(
          'mt-0.5 truncate',
          highlight && 'text-[14px] font-semibold',
          !highlight && 'text-[13px]',
          tone === 'success' && 'text-success',
          tone === 'warning' && 'text-warning',
          tone === 'default' && 'text-fg',
        )}
      >
        {value}
      </div>
    </div>
  );
}

function Th({ children, className }: { children: React.ReactNode; className?: string }) {
  return (
    <th
      className={cn(
        'sticky top-0 border-b border-hairline px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-fg-subtle',
        className,
      )}
    >
      {children}
    </th>
  );
}

function Td({ children, className }: { children: React.ReactNode; className?: string }) {
  return <td className={cn('px-3 py-2 align-top', className)}>{children}</td>;
}
