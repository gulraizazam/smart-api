import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, Loader2 } from 'lucide-react';
import { api, ApiError } from '@/lib/api';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Refund / settle dialog for a plan.
 *
 * Source:
 *   GET  /api/packages/refund/{id}           — form data + computed refundable
 *   POST /api/packages/refund/update         — commit refund / case settlement
 *
 * Business rules baked into the server (PlanRefundService):
 *   - refundable_amount = cash_received − consumed_services − already_refunded
 *                         − documentation_charges
 *   - case_setteled = "1" triggers a settlement invoice and locks the plan
 *     against further mutation (is_setteled=1 on a new package_advances row)
 *   - Unchecking case_setteled on a previously-settled plan deletes the
 *     settlement rows (reversible — see PlanRefundService::processRefund)
 *
 * We surface the computed refundable amount prominently so operators don't
 * guess. Backend still hard-validates on submit — this is a UX signal only.
 */

type PaymentModesDict = Record<string, string>;

type RefundData = {
  id: number;
  refundable_amount: number;
  cash_amount: number;
  is_adjustment_amount: number;
  return_tax_amount: number | string;
  date_backend: string;
  paymentmodes: PaymentModesDict;
  refunded_amount: number | string;
  record_id: number | null;
  package_setteled_amount: number;
  patient_name: string;
  patient_id: number;
  plan: string;
  created_date: string;
  refund_note: string;
  payment_method_id: number;
};

interface Props {
  planId: number | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function PlanRefundDialog({ planId, open, onOpenChange }: Props) {
  const qc = useQueryClient();
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [paymentMode, setPaymentMode] = useState('');
  const [createdAt, setCreatedAt] = useState('');
  const [caseSettled, setCaseSettled] = useState(false);

  const data = useQuery({
    queryKey: ['plans', 'refund-form', planId],
    queryFn: () => api.get<RefundData>(`/api/packages/refund/${planId}`),
    enabled: open && !!planId,
  });

  useEffect(() => {
    const d = data.data;
    if (!d) return;
    setAmount(String(d.refunded_amount ?? ''));
    setNote(d.refund_note ?? '');
    setPaymentMode(String(d.payment_method_id ?? ''));
    setCreatedAt(d.created_date ?? new Date().toISOString().slice(0, 10));
    setCaseSettled(Number(d.package_setteled_amount) > 0);
  }, [data.data]);

  const save = useMutation({
    mutationFn: () =>
      api.post<{ success?: boolean; message?: string }>('/api/packages/refund/update', {
        package_id: planId,
        record_id: data.data?.record_id,
        refund_amount: Number(amount || 0),
        refund_note: note,
        payment_mode_id: Number(paymentMode),
        created_at: createdAt,
        case_setteled: caseSettled ? '1' : '0',
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['plans'] });
      onOpenChange(false);
    },
  });

  const d = data.data;
  const refundable = Number(d?.refundable_amount ?? 0);
  const overRefundable = Number(amount || 0) > refundable && !caseSettled;
  const fmt = (n: number) => n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  return (
    <Dialog open={open} onOpenChange={(o) => !o && !save.isPending && onOpenChange(false)}>
      <DialogContent className="max-w-lg" aria-describedby={undefined}>
        <DialogHeader>
          <DialogTitle>Refund / Settle</DialogTitle>
        </DialogHeader>

        {data.isLoading ? (
          <Skeleton className="h-40 w-full" />
        ) : d ? (
          <div className="space-y-3">
            {/* Context */}
            <div className="rounded-lg bg-surface p-3 text-[12.5px]">
              <div className="text-fg-subtle">
                <span className="font-medium text-fg">{d.patient_name}</span> · {d.plan}
              </div>
              <div className="mt-2 grid grid-cols-3 gap-2">
                <Stat label="Cash received" value={fmt(Number(d.cash_amount))} />
                <Stat label="Refundable" value={fmt(refundable)} tone="success" />
                <Stat label="Already settled" value={fmt(Number(d.package_setteled_amount))} tone="warning" />
              </div>
            </div>

            <div>
              <Label htmlFor="refund-amount">Refund amount</Label>
              <Input
                id="refund-amount"
                type="number"
                inputMode="decimal"
                min="0"
                step="1"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                disabled={save.isPending}
              />
              {overRefundable && (
                <div className="mt-1 text-[11.5px] text-warning">
                  Amount exceeds computed refundable of {fmt(refundable)}. Server may reject unless Case Settle is checked.
                </div>
              )}
            </div>

            <div>
              <Label htmlFor="refund-note">Note (required)</Label>
              <Input
                id="refund-note"
                type="text"
                value={note}
                onChange={(e) => setNote(e.target.value)}
                placeholder="Reason for refund…"
                disabled={save.isPending}
              />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="refund-payment-mode">Return via</Label>
                <Select
                  id="refund-payment-mode"
                  value={paymentMode}
                  onChange={(e) => setPaymentMode(e.target.value)}
                  disabled={save.isPending}
                >
                  <option value="">Select…</option>
                  {Object.entries(d.paymentmodes).map(([id, name]) => (
                    <option key={id} value={id}>{name}</option>
                  ))}
                </Select>
              </div>
              <div>
                <Label htmlFor="refund-date">Date</Label>
                <Input
                  id="refund-date"
                  type="date"
                  value={createdAt}
                  onChange={(e) => setCreatedAt(e.target.value)}
                  min={d.date_backend}
                  disabled={save.isPending}
                />
              </div>
            </div>

            <label className="flex items-start gap-2 rounded-lg bg-surface p-3 text-[12.5px] cursor-pointer">
              <input
                type="checkbox"
                checked={caseSettled}
                onChange={(e) => setCaseSettled(e.target.checked)}
                disabled={save.isPending}
                className="mt-0.5 size-3.5 rounded border-hairline text-brand-blue focus:ring-2 focus:ring-accent-cyan/50"
              />
              <div>
                <div className="font-medium text-fg">Case settle</div>
                <div className="text-fg-subtle text-[11.5px]">
                  Locks the plan against further changes and generates a settlement invoice.
                  Unchecking reverses a prior settlement.
                </div>
              </div>
            </label>

            {save.error && (
              <div role="alert" className="flex items-start gap-2 rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
                <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                <span>{save.error instanceof ApiError ? save.error.message : (save.error as Error).message}</span>
              </div>
            )}
          </div>
        ) : null}

        <DialogFooter>
          <Button variant="secondary" size="sm" onClick={() => onOpenChange(false)} disabled={save.isPending}>
            Cancel
          </Button>
          <Button
            size="sm"
            variant={caseSettled ? 'primary' : 'danger'}
            onClick={() => save.mutate()}
            disabled={
              save.isPending ||
              data.isLoading ||
              !note.trim() ||
              !paymentMode ||
              !createdAt ||
              !amount
            }
          >
            {save.isPending && <Loader2 className="size-4 animate-spin" />}
            {caseSettled ? 'Refund & Settle' : 'Refund'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function Stat({ label, value, tone = 'default' }: { label: string; value: string; tone?: 'default' | 'success' | 'warning' }) {
  return (
    <div>
      <div className="text-[10.5px] font-semibold uppercase tracking-wider text-fg-subtle">{label}</div>
      <div
        className={
          tone === 'success'
            ? 'mt-0.5 nums-tabular text-[13px] font-medium text-success'
            : tone === 'warning'
              ? 'mt-0.5 nums-tabular text-[13px] font-medium text-warning'
              : 'mt-0.5 nums-tabular text-[13px] font-medium text-fg'
        }
      >
        {value}
      </div>
    </div>
  );
}
