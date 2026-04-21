import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, Loader2, Search } from 'lucide-react';
import {
  fetchAppointmentsForPlan,
  fetchMembershipTypes,
  fetchPlanCreateData,
  saveMembershipPlan,
  searchMembershipCodes,
  searchPatients,
  type MembershipCode,
  type PatientSearchResult,
} from '@/lib/plan-api';
import { ApiError } from '@/lib/api';
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
 * Membership assign dialog.
 *
 * Different workflow from plan/bundle:
 *   - Single row (one membership per plan)
 *   - Server-pre-generated code (must be reserved at save; membership table
 *     gets patient_id set at that point)
 *   - Price = membership_type.amount; server computes tax on save
 *   - No per-row AJAX pre-stage; full payload goes through savepackages
 *
 * Legacy form-key shape (matches PlanService::storeMembershipData):
 *   package_memberships: [{
 *     membershipId, membershipCodeId,
 *     RegularPrice, Amount, Tax, DiscountName, Type, DiscountValue
 *   }]
 */

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  lockedPatient?: PatientSearchResult;
};

export function PlanMembershipCreateDialog({ open, onOpenChange, lockedPatient }: Props) {
  const qc = useQueryClient();

  const formData = useQuery({
    queryKey: ['plans', 'create', 'form-data', 'membership'],
    queryFn: () => fetchPlanCreateData(),
    enabled: open,
    staleTime: 60_000,
  });

  const randomId = formData.data?.random_id ?? '';
  const locations = formData.data?.locations ?? {};
  const paymentmodes = formData.data?.paymentmodes ?? {};

  const [patient, setPatient] = useState<PatientSearchResult | null>(lockedPatient ?? null);
  const [locationId, setLocationId] = useState<string>('');
  const [appointmentId, setAppointmentId] = useState<string>('');

  const [membershipTypeId, setMembershipTypeId] = useState<string>('');
  const [codeQuery, setCodeQuery] = useState('');
  const [debouncedCodeQuery, setDebouncedCodeQuery] = useState('');
  const [selectedCode, setSelectedCode] = useState<MembershipCode | null>(null);

  const [paymentMode, setPaymentMode] = useState<string>('');
  const [cashAmount, setCashAmount] = useState<string>('');

  useEffect(() => {
    if (lockedPatient) setPatient(lockedPatient);
  }, [lockedPatient]);

  useEffect(() => {
    const t = setTimeout(() => setDebouncedCodeQuery(codeQuery.trim()), 250);
    return () => clearTimeout(t);
  }, [codeQuery]);

  const appointments = useQuery({
    queryKey: ['plans', 'create', 'appointments', patient?.id, locationId],
    queryFn: () => fetchAppointmentsForPlan(patient!.id, Number(locationId)),
    enabled: !!patient && !!locationId,
  });

  const membershipTypes = useQuery({
    queryKey: ['plans', 'create', 'membership-types', locationId, patient?.id],
    queryFn: () => fetchMembershipTypes(Number(locationId), patient!.id),
    enabled: !!locationId && !!patient,
  });

  const codeMatches = useQuery({
    queryKey: ['plans', 'create', 'membership-codes', debouncedCodeQuery, membershipTypeId],
    queryFn: () =>
      searchMembershipCodes(debouncedCodeQuery, membershipTypeId ? Number(membershipTypeId) : undefined),
    enabled: debouncedCodeQuery.length >= 2 && !!membershipTypeId,
    staleTime: 15_000,
  });

  const selectedType = useMemo(
    () => (membershipTypes.data ?? []).find((t) => String(t.id) === membershipTypeId),
    [membershipTypes.data, membershipTypeId],
  );

  const regularPrice = Number(selectedType?.amount ?? 0);
  const total = regularPrice;
  const cashNumber = Number(cashAmount || 0);
  const balance = total - cashNumber;

  const save = useMutation({
    mutationFn: async () => {
      if (!patient || !locationId || !appointmentId || !membershipTypeId || !selectedCode) {
        throw new Error('All fields are required.');
      }
      if (cashNumber > 0 && !paymentMode) {
        throw new Error('Payment mode is required when a cash amount is entered.');
      }
      const resp = await saveMembershipPlan({
        random_id: randomId,
        patient_id: patient.id,
        location_id: Number(locationId),
        appointment_id: appointmentId,
        plan_type: 'membership',
        total: total.toFixed(2),
        package_memberships: [
          {
            membershipId: Number(membershipTypeId),
            membershipCodeId: selectedCode.id,
            RegularPrice: regularPrice.toFixed(2),
            Amount: regularPrice.toFixed(2),
            // Tax is computed server-side in addMembershipService/storeMembershipData;
            // we pass 0 here — the server's tax_price takes precedence on insert.
            Tax: '0',
          },
        ],
        payment_mode_id: paymentMode ? Number(paymentMode) : undefined,
        cash_amount: cashNumber || undefined,
      });
      if (!resp.status) throw new Error(resp.message ?? 'Save failed.');
      return resp;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['plans'] });
      setMembershipTypeId('');
      setSelectedCode(null);
      setCodeQuery('');
      setPaymentMode('');
      setCashAmount('');
      setAppointmentId('');
      onOpenChange(false);
    },
  });

  const handleClose = () => {
    if (save.isPending) return;
    setMembershipTypeId('');
    setSelectedCode(null);
    setCodeQuery('');
    setPaymentMode('');
    setCashAmount('');
    setAppointmentId('');
    if (!lockedPatient) setPatient(null);
    setLocationId('');
    onOpenChange(false);
  };

  const fmt = (n: number) =>
    n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  return (
    <Dialog open={open} onOpenChange={(o) => !o && handleClose()}>
      <DialogContent className="max-w-2xl" aria-describedby={undefined}>
        <DialogHeader>
          <DialogTitle>Assign membership</DialogTitle>
        </DialogHeader>

        {formData.isLoading ? (
          <Skeleton className="h-60 w-full" />
        ) : (
          <div className="max-h-[70vh] space-y-3 overflow-y-auto pr-1">
            {/* Patient / centre / appointment */}
            <section className="grid grid-cols-1 gap-3 md:grid-cols-3">
              <div>
                <Label htmlFor="ms-patient">Patient</Label>
                {lockedPatient ? (
                  <div className="mt-1 rounded-md bg-surface px-3 py-2 text-[13px] text-fg">
                    {lockedPatient.name}
                  </div>
                ) : (
                  <InlinePatientPicker value={patient} onChange={setPatient} />
                )}
              </div>
              <div>
                <Label htmlFor="ms-centre">Centre</Label>
                <Select
                  id="ms-centre"
                  value={locationId}
                  onChange={(e) => {
                    setLocationId(e.target.value);
                    setAppointmentId('');
                    setMembershipTypeId('');
                    setSelectedCode(null);
                  }}
                  disabled={!patient}
                >
                  <option value="">Select centre…</option>
                  {Object.entries(locations).map(([id, name]) => (
                    <option key={id} value={id}>{name}</option>
                  ))}
                </Select>
              </div>
              <div>
                <Label htmlFor="ms-appointment">Appointment</Label>
                <Select
                  id="ms-appointment"
                  value={appointmentId}
                  onChange={(e) => setAppointmentId(e.target.value)}
                  disabled={!patient || !locationId || appointments.isLoading}
                >
                  <option value="">Select appointment…</option>
                  {(appointments.data ?? []).map((a) => (
                    <option key={a.id} value={a.id}>{a.label}</option>
                  ))}
                </Select>
              </div>
            </section>

            {/* Membership type + code */}
            <section className="rounded-lg bg-surface p-3 space-y-3">
              <div>
                <Label htmlFor="ms-type">Membership type</Label>
                <Select
                  id="ms-type"
                  value={membershipTypeId}
                  onChange={(e) => {
                    setMembershipTypeId(e.target.value);
                    setSelectedCode(null);
                    setCodeQuery('');
                  }}
                  disabled={!locationId || membershipTypes.isLoading}
                >
                  <option value="">Select…</option>
                  {(membershipTypes.data ?? []).map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.name} · {fmt(Number(t.amount))}
                    </option>
                  ))}
                </Select>
              </div>

              <div>
                <Label htmlFor="ms-code">Code</Label>
                {selectedCode ? (
                  <div className="mt-1 flex items-center gap-2 rounded-md bg-elevated px-3 py-2 text-[13px] text-fg ring-1 ring-inset ring-hairline">
                    <span className="font-mono">{selectedCode.code}</span>
                    <button
                      type="button"
                      aria-label="Clear code"
                      onClick={() => setSelectedCode(null)}
                      className="ml-auto text-fg-subtle hover:text-fg"
                    >
                      ×
                    </button>
                  </div>
                ) : (
                  <div className="relative mt-1">
                    <Search className="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-fg-subtle" aria-hidden />
                    <input
                      id="ms-code"
                      type="search"
                      value={codeQuery}
                      onChange={(e) => setCodeQuery(e.target.value)}
                      placeholder="Search unassigned code…"
                      disabled={!membershipTypeId}
                      className="h-9 w-full rounded-lg bg-elevated pl-9 pr-2 text-[13px] ring-1 ring-inset ring-hairline focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50 disabled:opacity-50"
                    />
                    {debouncedCodeQuery.length >= 2 && codeMatches.data && codeMatches.data.length > 0 && (
                      <div className="absolute z-10 mt-1 w-full max-h-48 overflow-y-auto rounded-lg bg-elevated shadow-lg ring-1 ring-inset ring-hairline">
                        {codeMatches.data.map((c) => (
                          <button
                            key={c.id}
                            type="button"
                            onClick={() => {
                              setSelectedCode(c);
                              setCodeQuery('');
                            }}
                            className="flex w-full items-center justify-between px-3 py-2 text-left text-[12.5px] font-mono transition-colors hover:bg-surface"
                          >
                            <span>{c.code}</span>
                          </button>
                        ))}
                      </div>
                    )}
                  </div>
                )}
              </div>

              {selectedType && (
                <div className="flex items-center justify-between rounded-md bg-elevated px-3 py-2 text-[12.5px] ring-1 ring-inset ring-hairline">
                  <span className="text-fg-subtle">Amount</span>
                  <span className="nums-tabular font-medium text-fg">{fmt(regularPrice)}</span>
                </div>
              )}
            </section>

            {/* Payment */}
            {selectedType && selectedCode && (
              <section className="rounded-lg bg-surface p-3">
                <div className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-fg-subtle">
                  Initial payment (optional)
                </div>
                <div className="grid grid-cols-1 gap-2 md:grid-cols-3">
                  <div>
                    <Label htmlFor="ms-payment-mode">Mode</Label>
                    <Select
                      id="ms-payment-mode"
                      value={paymentMode}
                      onChange={(e) => setPaymentMode(e.target.value)}
                    >
                      <option value="">None</option>
                      {Object.entries(paymentmodes).map(([id, name]) => (
                        <option key={id} value={id}>{name}</option>
                      ))}
                    </Select>
                  </div>
                  <div>
                    <Label htmlFor="ms-cash">Amount</Label>
                    <Input
                      id="ms-cash"
                      type="number"
                      inputMode="decimal"
                      min="0"
                      step="0.01"
                      value={cashAmount}
                      onChange={(e) => setCashAmount(e.target.value)}
                      disabled={!paymentMode}
                    />
                  </div>
                  <div>
                    <Label>Balance</Label>
                    <div
                      className={
                        'mt-1 rounded-md bg-elevated px-3 py-2 text-[13px] nums-tabular ring-1 ring-inset ring-hairline ' +
                        (balance > 0 ? 'text-warning' : 'text-fg')
                      }
                    >
                      {fmt(balance)}
                    </div>
                  </div>
                </div>
              </section>
            )}

            {save.error && (
              <div role="alert" className="flex items-start gap-2 rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
                <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                <span>{save.error instanceof ApiError ? save.error.message : (save.error as Error).message}</span>
              </div>
            )}
          </div>
        )}

        <DialogFooter>
          <Button variant="secondary" size="sm" onClick={handleClose} disabled={save.isPending}>
            Cancel
          </Button>
          <Button
            size="sm"
            onClick={() => save.mutate()}
            disabled={
              save.isPending ||
              !patient ||
              !locationId ||
              !appointmentId ||
              !membershipTypeId ||
              !selectedCode
            }
          >
            {save.isPending && <Loader2 className="size-4 animate-spin" />}
            Assign membership
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// Simplified patient picker; duplicated from plan-create-dialog to avoid
// premature abstraction — if a third subtype lands, extract into its own
// component then.
function InlinePatientPicker({
  value,
  onChange,
}: {
  value: PatientSearchResult | null;
  onChange: (p: PatientSearchResult | null) => void;
}) {
  const [q, setQ] = useState('');
  const [debounced, setDebounced] = useState('');
  const [open, setOpen] = useState(false);

  useEffect(() => {
    const t = setTimeout(() => setDebounced(q.trim()), 250);
    return () => clearTimeout(t);
  }, [q]);

  const results = useQuery({
    queryKey: ['plans', 'create', 'patient-search', debounced],
    queryFn: () => searchPatients(debounced),
    enabled: debounced.length >= 2 && open,
    staleTime: 30_000,
  });

  if (value) {
    return (
      <div className="mt-1 flex items-center gap-2 rounded-md bg-elevated px-3 py-2 text-[13px] text-fg ring-1 ring-inset ring-hairline">
        <span className="truncate">{value.name}</span>
        <button
          type="button"
          onClick={() => {
            onChange(null);
            setQ('');
          }}
          className="ml-auto text-fg-subtle hover:text-fg"
          aria-label="Clear patient"
        >
          ×
        </button>
      </div>
    );
  }

  return (
    <div className="relative">
      <div className="relative mt-1">
        <Search className="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-fg-subtle" aria-hidden />
        <input
          type="search"
          value={q}
          onChange={(e) => {
            setQ(e.target.value);
            setOpen(true);
          }}
          onFocus={() => setOpen(true)}
          onBlur={() => setTimeout(() => setOpen(false), 150)}
          placeholder="Search patient…"
          autoComplete="off"
          className="h-9 w-full rounded-lg bg-elevated pl-9 pr-2 text-[13px] ring-1 ring-inset ring-hairline focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
        />
      </div>
      {open && debounced.length >= 2 && (
        <div className="absolute z-10 mt-1 w-full max-h-56 overflow-y-auto rounded-lg bg-elevated shadow-lg ring-1 ring-inset ring-hairline">
          {results.isLoading ? (
            <div className="p-3"><Skeleton className="h-8 w-full" /></div>
          ) : (results.data ?? []).length === 0 ? (
            <div className="px-3 py-2 text-[12.5px] text-fg-muted">No matches</div>
          ) : (
            (results.data ?? []).map((p) => (
              <button
                key={p.id}
                type="button"
                onClick={() => {
                  onChange(p);
                  setOpen(false);
                }}
                className="flex w-full items-center gap-2 px-3 py-2 text-left text-[12.5px] transition-colors hover:bg-surface"
              >
                <span className="truncate text-fg">{p.name}</span>
                {p.phone && <span className="text-fg-subtle">· {p.phone}</span>}
              </button>
            ))
          )}
        </div>
      )}
    </div>
  );
}
