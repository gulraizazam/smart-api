import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2 } from 'lucide-react';
import { api, ApiError } from '@/lib/api';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Three small action dialogs for the patient profile:
 *   • AssignMembershipDialog  — POST /api/patients/assignmembership { patient_id, code }
 *   • AssignVoucherDialog     — POST /api/patients/assignvoucher    { patient_id, voucher_id, amount }
 *   • AddReferralDialog       — POST /api/patients/{id}/addreferral { code }
 *
 * Server enforces business rules (existing membership conflict, Gold-only
 * referral, max 2 per code, expiry); we surface the message verbatim.
 */

type RefreshKeys = (string | number)[][];

function useDialogMutation(opts: {
  fn: () => Promise<unknown>;
  refreshKeys: RefreshKeys;
  onSuccess: () => void;
}) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: opts.fn,
    onSuccess: () => {
      opts.refreshKeys.forEach((key) => qc.invalidateQueries({ queryKey: key }));
      opts.onSuccess();
    },
  });
}

interface BaseProps {
  patientId: number | string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function AssignMembershipDialog({ patientId, open, onOpenChange }: BaseProps) {
  const [code, setCode] = useState('');
  useEffect(() => { if (open) setCode(''); }, [open]);

  const submit = useDialogMutation({
    fn: () => api.post('/api/patients/assignmembership', { patient_id: patientId, code }),
    refreshKeys: [['patients', 'detail', patientId], ['patients', 'datatable']],
    onSuccess: () => onOpenChange(false),
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-sm">
        <DialogHeader>
          <DialogTitle>Assign membership</DialogTitle>
          <DialogDescription>
            Enter a membership code to assign it to this patient. Server will reject if the
            patient already has an active membership.
          </DialogDescription>
        </DialogHeader>

        {submit.error && (
          <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
            {(submit.error as ApiError).message}
          </div>
        )}

        <div className="space-y-1">
          <Label className="text-[11.5px]">Membership code</Label>
          <Input
            value={code}
            onChange={(e) => setCode(e.target.value.toUpperCase())}
            placeholder="e.g. CA4850"
            className="h-9 nums-tabular tracking-wide"
            autoFocus
          />
        </div>

        <DialogFooter>
          <Button variant="secondary" size="sm" onClick={() => onOpenChange(false)} disabled={submit.isPending}>
            Cancel
          </Button>
          <Button size="sm" onClick={() => submit.mutate()} disabled={!code.trim() || submit.isPending}>
            {submit.isPending && <Loader2 className="size-4 animate-spin" />}
            Assign
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface AssignVoucherDialogProps extends BaseProps {
  /** Optional pre-fetched voucher list — pass `[]` if none */
  vouchers?: Array<{ id: number; name: string }>;
}

export function AssignVoucherDialog({ patientId, vouchers = [], open, onOpenChange }: AssignVoucherDialogProps) {
  const [voucherId, setVoucherId] = useState('');
  const [amount, setAmount] = useState('');
  useEffect(() => { if (open) { setVoucherId(''); setAmount(''); } }, [open]);

  const submit = useDialogMutation({
    fn: () =>
      api.post('/api/patients/assignvoucher', {
        patient_id: patientId,
        voucher_id: Number(voucherId),
        amount: Number(amount),
      }),
    refreshKeys: [['patients', 'detail', patientId]],
    onSuccess: () => onOpenChange(false),
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-sm">
        <DialogHeader>
          <DialogTitle>Assign voucher</DialogTitle>
          <DialogDescription>Pick a voucher and the amount to credit.</DialogDescription>
        </DialogHeader>

        {submit.error && (
          <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
            {(submit.error as ApiError).message}
          </div>
        )}

        <div className="space-y-2">
          <div className="space-y-1">
            <Label className="text-[11.5px]">Voucher</Label>
            <select
              value={voucherId}
              onChange={(e) => setVoucherId(e.target.value)}
              className="h-9 w-full rounded-lg bg-elevated px-3 text-[13px] text-fg ring-1 ring-inset ring-hairline focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
            >
              <option value="">Select…</option>
              {vouchers.map((v) => (
                <option key={v.id} value={v.id}>{v.name}</option>
              ))}
            </select>
            {vouchers.length === 0 && (
              <p className="text-[11px] text-fg-subtle">
                Voucher catalog isn't wired yet — type the voucher id directly:
              </p>
            )}
            {vouchers.length === 0 && (
              <Input
                value={voucherId}
                onChange={(e) => setVoucherId(e.target.value)}
                placeholder="Voucher ID"
                className="h-9 nums-tabular"
              />
            )}
          </div>
          <div className="space-y-1">
            <Label className="text-[11.5px]">Amount</Label>
            <Input
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              placeholder="e.g. 5000"
              inputMode="numeric"
              className="h-9 nums-tabular"
            />
          </div>
        </div>

        <DialogFooter>
          <Button variant="secondary" size="sm" onClick={() => onOpenChange(false)} disabled={submit.isPending}>
            Cancel
          </Button>
          <Button size="sm" onClick={() => submit.mutate()} disabled={!voucherId || !amount || submit.isPending}>
            {submit.isPending && <Loader2 className="size-4 animate-spin" />}
            Assign
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export function AddReferralDialog({ patientId, open, onOpenChange }: BaseProps) {
  const [code, setCode] = useState('');
  const [debouncedCode, setDebouncedCode] = useState('');
  useEffect(() => { if (open) setCode(''); }, [open]);

  // Debounce the code input so the lookup endpoint isn't hit on every
  // keystroke. 250ms is short enough to feel live but cheap enough to
  // not hammer the server while the user is typing.
  useEffect(() => {
    const t = setTimeout(() => setDebouncedCode(code.trim()), 250);
    return () => clearTimeout(t);
  }, [code]);

  type CountResponse = { code: string; count: number; max: number; limit_reached: boolean };
  const countQuery = useQuery({
    queryKey: ['patients', 'referral-count', debouncedCode],
    queryFn: () =>
      api.get<CountResponse>(
        `/api/patients/membership/referral-count?code=${encodeURIComponent(debouncedCode)}`,
      ),
    enabled: open && debouncedCode.length > 0,
    staleTime: 5_000,
  });

  const limitReached = countQuery.data?.limit_reached === true;

  const submit = useDialogMutation({
    fn: () => api.post(`/api/patients/${patientId}/addreferral`, { code }),
    refreshKeys: [
      ['patients', 'detail', patientId],
      ['patients', 'referral-count', debouncedCode],
    ],
    onSuccess: () => onOpenChange(false),
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-sm">
        <DialogHeader>
          <DialogTitle>Add Gold referral</DialogTitle>
          <DialogDescription>
            Referrals are only valid for active Gold memberships. Each code allows up to two
            referrals.
          </DialogDescription>
        </DialogHeader>

        {submit.error && (
          <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
            {(submit.error as ApiError).message}
          </div>
        )}

        <div className="space-y-1">
          <Label className="text-[11.5px]">Parent membership code</Label>
          <Input
            value={code}
            onChange={(e) => setCode(e.target.value.toUpperCase())}
            placeholder="e.g. CA4850"
            className="h-9 nums-tabular tracking-wide"
            autoFocus
          />
          {/* Live count hint: starts blank, fills in as soon as the
              debounced lookup returns. Red copy + disabled submit when
              the code already has 2 referrals, so the user never wastes
              a click submitting a guaranteed-failure request. */}
          {debouncedCode.length > 0 && countQuery.data && (
            <p
              className={
                limitReached
                  ? 'mt-1 text-[11.5px] font-medium text-danger'
                  : 'mt-1 text-[11.5px] text-fg-subtle'
              }
            >
              {limitReached
                ? `Limit reached — ${countQuery.data.count} of ${countQuery.data.max} referrals used.`
                : `${countQuery.data.count} of ${countQuery.data.max} referrals used.`}
            </p>
          )}
        </div>

        <DialogFooter>
          <Button variant="secondary" size="sm" onClick={() => onOpenChange(false)} disabled={submit.isPending}>
            Cancel
          </Button>
          <Button
            size="sm"
            onClick={() => submit.mutate()}
            disabled={!code.trim() || submit.isPending || limitReached}
          >
            {submit.isPending && <Loader2 className="size-4 animate-spin" />}
            Add referral
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
