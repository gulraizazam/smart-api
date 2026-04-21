import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2 } from 'lucide-react';
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
 * Edit an existing payment (package_advances row).
 *
 * Source:
 *   GET  /api/packages/edit_cash/{advance_id}/{package_id}  — form data
 *   PUT  /api/packages/edit_cash/store                       — save
 *
 * Per-field permission gates mirror the legacy Blade (plane-edit.blade.php):
 *   plans_cash_edit_payment_mode | plans_cash_edit_amount | plans_cash_edit_date
 * If a gate is false, the field is rendered read-only instead of hidden — the
 * user still sees the current value so an audit conversation makes sense.
 */

type PaymentMode = { id: number; name: string; type?: string };
type PackAdvInfo = {
  id: number;
  cash_amount: number | string;
  payment_mode_id: number;
  created_at: string;
};

type EditResp = {
  pack_adv_info: PackAdvInfo;
  package_id: number;
  paymentmodes: PaymentMode[];
};

export interface PlanCashEditPermissions {
  paymentMode?: boolean;
  amount?: boolean;
  date?: boolean;
}

interface Props {
  packageId: number;
  advanceId: number | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  permissions?: PlanCashEditPermissions;
}

export function PlanCashEditDialog({
  packageId,
  advanceId,
  open,
  onOpenChange,
  permissions = { paymentMode: true, amount: true, date: true },
}: Props) {
  const qc = useQueryClient();
  const [paymentMode, setPaymentMode] = useState<string>('');
  const [amount, setAmount] = useState<string>('');
  const [createdAt, setCreatedAt] = useState<string>('');

  const data = useQuery({
    queryKey: ['plans', 'cash-edit', advanceId, packageId],
    queryFn: () => api.get<EditResp>(`/api/packages/edit_cash/${advanceId}/${packageId}`),
    enabled: open && !!advanceId,
  });

  useEffect(() => {
    if (!data.data) return;
    const adv = data.data.pack_adv_info;
    setPaymentMode(String(adv.payment_mode_id));
    setAmount(String(adv.cash_amount ?? ''));
    // server returns ISO timestamp; keep YYYY-MM-DD for <input type=date>.
    setCreatedAt((adv.created_at ?? '').slice(0, 10));
  }, [data.data]);

  const save = useMutation({
    mutationFn: () =>
      api.put('/api/packages/edit_cash/store', {
        package_advances_id: advanceId,
        package_id: packageId,
        payment_mode_id: Number(paymentMode),
        cash_amount: Number(amount),
        created_at: createdAt,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['plans', 'detail', packageId] });
      qc.invalidateQueries({ queryKey: ['plans', 'global'] });
      qc.invalidateQueries({ queryKey: ['plans', 'patient-list'] });
      onOpenChange(false);
    },
  });

  const paymentModes = data.data?.paymentmodes ?? [];

  return (
    <Dialog open={open} onOpenChange={(o) => !o && !save.isPending && onOpenChange(false)}>
      <DialogContent className="max-w-md" aria-describedby={undefined}>
        <DialogHeader>
          <DialogTitle>Edit payment</DialogTitle>
        </DialogHeader>

        {data.isLoading ? (
          <Skeleton className="h-28 w-full" />
        ) : (
          <div className="space-y-3">
            <div>
              <Label htmlFor="payment-mode">Payment mode</Label>
              <Select
                id="payment-mode"
                value={paymentMode}
                onChange={(e) => setPaymentMode(e.target.value)}
                disabled={!permissions.paymentMode || save.isPending}
              >
                <option value="">Select…</option>
                {paymentModes.map((pm) => (
                  <option key={pm.id} value={pm.id}>{pm.name}</option>
                ))}
              </Select>
            </div>

            <div>
              <Label htmlFor="cash-amount">Amount</Label>
              <Input
                id="cash-amount"
                type="number"
                inputMode="decimal"
                min="0"
                step="0.01"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                readOnly={!permissions.amount}
                disabled={save.isPending}
              />
            </div>

            <div>
              <Label htmlFor="cash-date">Date</Label>
              <Input
                id="cash-date"
                type="date"
                value={createdAt}
                onChange={(e) => setCreatedAt(e.target.value)}
                readOnly={!permissions.date}
                disabled={save.isPending}
              />
            </div>

            {save.error && (
              <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
                {save.error instanceof ApiError ? save.error.message : (save.error as Error).message}
              </div>
            )}
          </div>
        )}

        <DialogFooter>
          <Button variant="secondary" size="sm" onClick={() => onOpenChange(false)} disabled={save.isPending}>
            Cancel
          </Button>
          <Button
            size="sm"
            onClick={() => save.mutate()}
            disabled={save.isPending || data.isLoading || !paymentMode || !amount || !createdAt}
          >
            {save.isPending && <Loader2 className="size-4 animate-spin" />}
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
