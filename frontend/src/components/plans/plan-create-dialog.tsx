import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AlertTriangle, Lock, Loader2, Plus, Search, Trash2 } from 'lucide-react';
import {
  addBundleRow,
  addServiceRow,
  deleteServiceRow,
  fetchAppointmentsForPlan,
  fetchBundlesForLocation,
  fetchCustomDiscountInfo,
  fetchDiscountInfo,
  fetchPlanCreateData,
  fetchPlanEditData,
  fetchServiceInfo,
  fetchServicesForLocation,
  refundVoucher,
  reserveVoucher,
  savePlan,
  searchPatients,
  updatePlan,
  type PatientSearchResult,
  type StagedPlanRow,
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
 * Plan create dialog (plan subtype only — bundle/membership follow).
 *
 * Flow:
 *   1. Dialog opens → GET /api/packages/create fetches random_id + lookups.
 *   2. User picks patient (async search) → centre → appointment.
 *   3. Row builder: service + discount → Add row → POST savepackagesservice
 *      stages a package_bundles row (random_id, no package_id).
 *   4. Repeat. Running total comes from summing staged rows.
 *   5. Optional payment mode + cash amount.
 *   6. Submit → POST /api/packages/savepackages { package_bundles: [ids...] }
 *      server binds rows to a new package inside a transaction.
 *   7. If user cancels, we POST deletepackagesservice for each staged row to
 *      avoid orphans (legacy has a cleanup job as a backstop).
 *
 * Deferred for follow-up PRs: voucher discounts (client ledger complexity),
 * custom discount percentage entry, student-verification document upload,
 * "create new appointment from consultation" (the `.N` suffix path).
 */

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Pre-fill patient (used from patient-detail page). */
  lockedPatient?: PatientSearchResult;
  /** Subtype: 'plan' (services) or 'bundle' (pre-configured groupings).
   *  Membership has its own dialog (different payload shape). */
  subtype?: 'plan' | 'bundle';
  /** When set, dialog opens in edit mode and preloads the existing plan. */
  editPackageId?: number;
};

export function PlanCreateDialog({
  open,
  onOpenChange,
  lockedPatient,
  subtype = 'plan',
  editPackageId,
}: Props) {
  const qc = useQueryClient();
  const isEdit = editPackageId != null;

  // Lookups
  const formData = useQuery({
    queryKey: ['plans', 'create', 'form-data', lockedPatient?.id],
    queryFn: () => fetchPlanCreateData(lockedPatient?.id),
    enabled: open && !isEdit,
    staleTime: 60_000,
  });

  const editData = useQuery({
    queryKey: ['plans', 'edit', 'form-data', editPackageId],
    queryFn: () => fetchPlanEditData(editPackageId!),
    enabled: open && isEdit,
  });

  const randomId = isEdit
    ? editData.data?.package.random_id ?? ''
    : formData.data?.random_id ?? '';
  const locations = formData.data?.locations ?? {};
  const paymentmodes = formData.data?.paymentmodes ?? {};
  const discountCatalog = formData.data?.discounts ?? [];
  const settled = isEdit && editData.data?.is_settled === true;

  // Header state
  const [patient, setPatient] = useState<PatientSearchResult | null>(lockedPatient ?? null);
  const [locationId, setLocationId] = useState<string>('');
  const [appointmentId, setAppointmentId] = useState<string>('');

  // Prefill from edit payload
  useEffect(() => {
    if (!isEdit || !editData.data) return;
    const pkg = editData.data.package;
    setLocationId(String(pkg.location_id));
    if (pkg.appointment_id) setAppointmentId(`${pkg.appointment_id}.A`);
    setStagedRows(editData.data.packagebundles ?? []);
  }, [isEdit, editData.data]);

  useEffect(() => {
    if (lockedPatient) setPatient(lockedPatient);
  }, [lockedPatient]);

  useEffect(() => {
    // Auto-select last consultation location if backend suggests it.
    if (formData.data?.last_consultation_location_id && !locationId) {
      setLocationId(String(formData.data.last_consultation_location_id));
    }
  }, [formData.data, locationId]);

  const appointments = useQuery({
    queryKey: ['plans', 'create', 'appointments', patient?.id, locationId],
    queryFn: () => fetchAppointmentsForPlan(patient!.id, Number(locationId)),
    enabled: !!patient && !!locationId,
  });

  const services = useQuery({
    queryKey: ['plans', 'create', 'services', locationId, subtype],
    queryFn: () =>
      subtype === 'bundle'
        ? fetchBundlesForLocation(Number(locationId))
        : fetchServicesForLocation(Number(locationId)),
    enabled: !!locationId && !settled,
  });

  // Row builder state
  const [draftServiceId, setDraftServiceId] = useState<string>('');
  const [draftDiscountId, setDraftDiscountId] = useState<string>('');
  const [stagedRows, setStagedRows] = useState<StagedPlanRow[]>([]);

  // Custom discount inline form (revealed when getdiscountinfo returns custom_checked=1).
  const [customDiscountOpen, setCustomDiscountOpen] = useState(false);
  const [customDiscountType, setCustomDiscountType] = useState<'Fixed' | 'Percentage'>('Percentage');
  const [customDiscountValue, setCustomDiscountValue] = useState('');

  // Pending-voucher ledger — tracks voucher_id + amount per staged row so
  // we can refund on delete/cancel. Keyed by staged row id.
  const [voucherLedger, setVoucherLedger] = useState<
    Map<number, { voucher_id: number; amount: number }>
  >(new Map());

  // Payment
  const [paymentMode, setPaymentMode] = useState<string>('');
  const [cashAmount, setCashAmount] = useState<string>('');

  // Compute the net amount the legacy backend expects alongside the
  // discount_id. The discount endpoint returns the net_amount and
  // discount_price; if no discount, fall back to service price.
  const addRow = useMutation({
    mutationFn: async () => {
      if (!patient || !locationId || !draftServiceId) {
        throw new Error('Patient, centre, and service are required.');
      }
      const serviceId = Number(draftServiceId);
      const discountId = draftDiscountId ? Number(draftDiscountId) : null;

      if (subtype === 'bundle') {
        // Bundle price is read from the catalog; server re-validates on add.
        const bundle = (services.data ?? []).find((s) => s.id === serviceId);
        const netAmount = Number(bundle?.price ?? 0);
        // Keep the return shape consistent with the plan path below so
        // onSuccess can destructure uniformly — bundles never carry a
        // voucher, so it's always null here.
        const row = await addBundleRow({
          bundle_id: serviceId,
          location_id: Number(locationId),
          net_amount: netAmount,
          random_id: randomId,
        });
        return { row, voucher: null };
      }

      // Plan subtype — server computes tax/net via getserviceinfo/getdiscountinfo.
      let netAmount = 0;
      let discountPrice = 0;
      let isVoucher = false;

      if (discountId) {
        const info = await fetchDiscountInfo({
          service_id: serviceId,
          discount_id: discountId,
          patient_id: patient.id,
          location_id: Number(locationId),
        });
        if (info?.custom_checked) {
          // Custom discount requires extra form input — reveal inline form
          // and abort. The submit happens in handleAddCustomDiscount below.
          setCustomDiscountOpen(true);
          throw new Error('CUSTOM_DISCOUNT_FORM_OPEN');
        }
        if (info) {
          netAmount = Number(info.net_amount || 0);
          discountPrice = Number(info.discount_price || 0);
          isVoucher = info.discount_is_voucher === true;
        }

        // Vouchers must be reserved server-side BEFORE creating the package_bundles
        // row — otherwise the ledger (user_vouchers.amount) could drift.
        if (isVoucher && discountPrice > 0) {
          await reserveVoucher({
            voucher_id: discountId,
            patient_id: patient.id,
            amount: discountPrice,
          });
        }
      } else {
        const info = await fetchServiceInfo({
          service_id: serviceId,
          location_id: Number(locationId),
        });
        netAmount = Number(info?.net_amount ?? info?.service_price ?? 0);
      }

      const row = await addServiceRow({
        bundle_id: serviceId,
        discount_id: discountId,
        discount_price: discountPrice,
        net_amount: netAmount,
        random_id: randomId,
        location_id: Number(locationId),
        user_id: patient.id,
        plan_type: 'plan',
      });

      return { row, voucher: isVoucher && discountId ? { voucher_id: discountId, amount: discountPrice } : null };
    },
    onSuccess: ({ row, voucher }) => {
      setStagedRows((prev) => [...prev, row]);
      if (voucher) {
        setVoucherLedger((prev) => new Map(prev).set(row.id, voucher));
      }
      setDraftServiceId('');
      setDraftDiscountId('');
    },
  });

  // Separate mutation for custom-discount Add. Runs after the user enters a
  // value in the inline form; calls getdiscountinfo_custom to compute + validate,
  // then stages the row via savepackagesservice.
  const addCustomDiscountRow = useMutation({
    mutationFn: async () => {
      if (!patient || !locationId || !draftServiceId || !draftDiscountId) {
        throw new Error('Patient, centre, service, and discount are required.');
      }
      const value = Number(customDiscountValue);
      if (!value || value <= 0) throw new Error('Enter a discount value greater than zero.');

      const info = await fetchCustomDiscountInfo({
        service_id: Number(draftServiceId),
        discount_id: Number(draftDiscountId),
        discount_value: value,
        discount_type: customDiscountType,
        patient_id: patient.id,
        location_id: Number(locationId),
      });
      if (!info) {
        throw new Error('Discount value is outside the allowed range for this service.');
      }

      const netAmount = Number(info.net_amount || 0);
      const row = await addServiceRow({
        bundle_id: Number(draftServiceId),
        discount_id: Number(draftDiscountId),
        discount_price: value,
        net_amount: netAmount,
        random_id: randomId,
        location_id: Number(locationId),
        user_id: patient.id,
        plan_type: 'plan',
      });
      return row;
    },
    onSuccess: (row) => {
      setStagedRows((prev) => [...prev, row]);
      setDraftServiceId('');
      setDraftDiscountId('');
      setCustomDiscountOpen(false);
      setCustomDiscountValue('');
    },
  });

  const removeRow = useMutation({
    mutationFn: async (row: StagedPlanRow) => {
      // Refund the voucher reservation first (if any) — don't block delete
      // on a voucher-refund network hiccup; worst case the voucher ledger
      // is briefly inconsistent and a manual reconciliation is needed.
      const voucher = voucherLedger.get(row.id);
      if (voucher && patient) {
        try {
          await refundVoucher({
            voucher_id: voucher.voucher_id,
            patient_id: patient.id,
            amount: voucher.amount,
          });
        } catch {
          /* log only; still proceed with row delete */
        }
      }
      await deleteServiceRow(row.id, randomId);
      return row.id;
    },
    onSuccess: (rowId) => {
      setStagedRows((prev) => prev.filter((r) => r.id !== rowId));
      setVoucherLedger((prev) => {
        const next = new Map(prev);
        next.delete(rowId);
        return next;
      });
    },
  });

  const total = useMemo(
    () => stagedRows.reduce((sum, r) => sum + Number(r.tax_including_price || 0), 0),
    [stagedRows],
  );

  const cashNumber = Number(cashAmount || 0);
  const balance = total - cashNumber;
  const overpaid = cashNumber > total + 0.01;

  const save = useMutation({
    mutationFn: async () => {
      if (!patient || !locationId || !appointmentId) {
        throw new Error('Patient, centre, and appointment are required.');
      }
      if (stagedRows.length === 0) {
        throw new Error('Add at least one service.');
      }
      if (cashNumber > 0 && !paymentMode) {
        throw new Error('Payment mode is required when a cash amount is entered.');
      }

      if (isEdit) {
        if (settled) {
          throw new Error('This plan is settled and cannot be modified.');
        }
        const resp = await updatePlan({
          random_id: randomId,
          patient_id: patient.id,
          location_id: Number(locationId),
          appointment_id: appointmentId,
          total: total.toFixed(2),
          package_bundles: stagedRows.map((r) => r.id),
          payment_mode_id: paymentMode ? Number(paymentMode) : undefined,
          cash_amount: cashNumber || undefined,
        });
        if (!resp.status) {
          throw new Error(resp.message ?? 'Update failed.');
        }
        return resp;
      }

      const resp = await savePlan({
        random_id: randomId,
        patient_id: patient.id,
        location_id: Number(locationId),
        appointment_id: appointmentId,
        plan_type: subtype,
        total: total.toFixed(2),
        package_bundles: stagedRows.map((r) => r.id),
        payment_mode_id: paymentMode ? Number(paymentMode) : undefined,
        cash_amount: cashNumber || undefined,
      });
      if (!resp.status) {
        throw new Error(resp.message ?? 'Save failed.');
      }
      return resp;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['plans'] });
      // Clear local state; backend has committed the rows into a real package.
      // Voucher reservations transfer to the package on save — no refund needed.
      setStagedRows([]);
      setDraftServiceId('');
      setDraftDiscountId('');
      setCashAmount('');
      setPaymentMode('');
      setAppointmentId('');
      setVoucherLedger(new Map());
      setCustomDiscountOpen(false);
      setCustomDiscountValue('');
      onOpenChange(false);
    },
  });

  // Cancel handler: if rows are staged (create only), fire deletes so they
  // don't orphan. Also refund every pending voucher reservation. In edit
  // mode the rows already belong to a saved package, so we leave them alone.
  const handleClose = async () => {
    if (save.isPending || addRow.isPending || removeRow.isPending) return;
    if (!isEdit && stagedRows.length > 0 && randomId) {
      if (voucherLedger.size > 0 && patient) {
        await Promise.allSettled(
          Array.from(voucherLedger.values()).map((v) =>
            refundVoucher({
              voucher_id: v.voucher_id,
              patient_id: patient.id,
              amount: v.amount,
            }),
          ),
        );
      }
      await Promise.allSettled(stagedRows.map((r) => deleteServiceRow(r.id, randomId)));
    }
    setVoucherLedger(new Map());
    setCustomDiscountOpen(false);
    setCustomDiscountValue('');
    setStagedRows([]);
    setDraftServiceId('');
    setDraftDiscountId('');
    setCashAmount('');
    setPaymentMode('');
    setAppointmentId('');
    if (!lockedPatient) setPatient(null);
    setLocationId('');
    onOpenChange(false);
  };

  const fmt = (n: number) =>
    n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  return (
    <Dialog open={open} onOpenChange={(o) => !o && handleClose()}>
      <DialogContent className="max-w-4xl" aria-describedby={undefined}>
        <DialogHeader>
          <DialogTitle>
            {isEdit ? `Edit plan #${editData.data?.package.name ?? editPackageId}` : subtype === 'bundle' ? 'New bundle' : 'New plan'}
          </DialogTitle>
        </DialogHeader>

        {settled && (
          <div
            role="alert"
            className="mb-2 flex items-start gap-2 rounded-lg bg-warning-soft px-3 py-2 text-[12.5px] text-warning ring-1 ring-inset ring-warning/20"
          >
            <Lock className="mt-0.5 size-3.5 shrink-0" />
            <span>This plan is settled. Mutations are blocked server-side; the form is read-only.</span>
          </div>
        )}

        {(isEdit ? editData.isLoading : formData.isLoading) ? (
          <Skeleton className="h-60 w-full" />
        ) : (isEdit ? editData.error : formData.error) ? (
          <ErrorBanner message={((isEdit ? editData.error : formData.error) as Error).message} />
        ) : (
          <div className="max-h-[70vh] space-y-4 overflow-y-auto pr-1">
            {/* Header: patient / centre / appointment */}
            <section className="grid grid-cols-1 gap-3 md:grid-cols-3">
              <div>
                <Label htmlFor="plan-patient">Patient</Label>
                {lockedPatient ? (
                  <div className="mt-1 flex items-center gap-2 rounded-md bg-surface px-3 py-2 text-[13px] text-fg">
                    {lockedPatient.name}
                    {lockedPatient.phone && (
                      <span className="text-[12px] text-fg-subtle">· {lockedPatient.phone}</span>
                    )}
                  </div>
                ) : (
                  <PatientPicker
                    id="plan-patient"
                    value={patient}
                    onChange={setPatient}
                  />
                )}
              </div>

              <div>
                <Label htmlFor="plan-centre">Centre</Label>
                <Select
                  id="plan-centre"
                  value={locationId}
                  onChange={(e) => {
                    setLocationId(e.target.value);
                    setAppointmentId('');
                    setDraftServiceId('');
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
                <Label htmlFor="plan-appointment">Appointment</Label>
                <Select
                  id="plan-appointment"
                  value={appointmentId}
                  onChange={(e) => setAppointmentId(e.target.value)}
                  disabled={!patient || !locationId || appointments.isLoading}
                >
                  <option value="">Select appointment…</option>
                  {(appointments.data ?? []).map((a) => (
                    <option key={a.id} value={a.id}>{a.label}</option>
                  ))}
                </Select>
                {appointments.isError && (
                  <div className="mt-1 text-[11.5px] text-warning">
                    Couldn't load appointments for this centre.
                  </div>
                )}
              </div>
            </section>

            {/* Row builder — hidden on a settled plan */}
            {!settled && (
              <section className="rounded-lg bg-surface p-3">
                <div className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-fg-subtle">
                  {subtype === 'bundle' ? 'Add bundle' : 'Add service'}
                </div>
                <div
                  className={
                    'grid grid-cols-1 gap-2 ' +
                    (subtype === 'bundle' ? 'md:grid-cols-[1fr_auto]' : 'md:grid-cols-[1fr_1fr_auto]')
                  }
                >
                  <Select
                    aria-label={subtype === 'bundle' ? 'Bundle' : 'Service'}
                    value={draftServiceId}
                    onChange={(e) => setDraftServiceId(e.target.value)}
                    disabled={!locationId || services.isLoading}
                  >
                    <option value="">
                      {subtype === 'bundle' ? 'Select bundle…' : 'Select service…'}
                    </option>
                    {(services.data ?? []).map((s) => (
                      <option key={s.id} value={s.id}>{s.name}</option>
                    ))}
                  </Select>
                  {subtype !== 'bundle' && (
                    <Select
                      aria-label="Discount"
                      value={draftDiscountId}
                      onChange={(e) => setDraftDiscountId(e.target.value)}
                      disabled={!draftServiceId}
                    >
                      <option value="">No discount</option>
                      {discountCatalog.map((d) => (
                        <option key={d.id} value={d.id}>{d.name}</option>
                      ))}
                    </Select>
                  )}
                  <Button
                    size="sm"
                    onClick={() => addRow.mutate()}
                    disabled={!draftServiceId || addRow.isPending}
                  >
                    {addRow.isPending ? <Loader2 className="size-3.5 animate-spin" /> : <Plus className="size-3.5" />}
                    Add row
                  </Button>
                </div>
                {addRow.error && (addRow.error as Error).message !== 'CUSTOM_DISCOUNT_FORM_OPEN' && (
                  <div className="mt-2 text-[12px] text-danger">
                    {addRow.error instanceof ApiError ? addRow.error.message : (addRow.error as Error).message}
                  </div>
                )}

                {/* Custom discount inline form — only shows when the discount's
                    slug === 'custom' and getdiscountinfo flagged it. */}
                {customDiscountOpen && (
                  <div className="mt-3 rounded-md bg-elevated p-3 ring-1 ring-inset ring-accent-cyan/30">
                    <div className="mb-2 flex items-center justify-between">
                      <div className="text-[11px] font-semibold uppercase tracking-wider text-fg-subtle">
                        Custom discount
                      </div>
                      <button
                        type="button"
                        onClick={() => {
                          setCustomDiscountOpen(false);
                          setCustomDiscountValue('');
                        }}
                        className="text-fg-subtle hover:text-fg"
                        aria-label="Cancel custom discount"
                      >
                        ×
                      </button>
                    </div>
                    <div className="grid grid-cols-1 gap-2 md:grid-cols-[auto_1fr_auto]">
                      <Select
                        aria-label="Custom discount type"
                        value={customDiscountType}
                        onChange={(e) => setCustomDiscountType(e.target.value as 'Fixed' | 'Percentage')}
                      >
                        <option value="Percentage">%</option>
                        <option value="Fixed">Flat</option>
                      </Select>
                      <Input
                        type="number"
                        inputMode="decimal"
                        min="0"
                        step="0.01"
                        value={customDiscountValue}
                        onChange={(e) => setCustomDiscountValue(e.target.value)}
                        placeholder={customDiscountType === 'Percentage' ? '0–100' : 'Amount'}
                        autoFocus
                      />
                      <Button
                        size="sm"
                        onClick={() => addCustomDiscountRow.mutate()}
                        disabled={!customDiscountValue || addCustomDiscountRow.isPending}
                      >
                        {addCustomDiscountRow.isPending ? <Loader2 className="size-3.5 animate-spin" /> : <Plus className="size-3.5" />}
                        Apply
                      </Button>
                    </div>
                    {formData.data?.range && (
                      <div className="mt-1 text-[11px] text-fg-subtle">
                        Allowed range: {formData.data.range[0]}–{formData.data.range[1]}
                      </div>
                    )}
                    {addCustomDiscountRow.error && (
                      <div className="mt-2 text-[12px] text-danger">
                        {addCustomDiscountRow.error instanceof ApiError
                          ? addCustomDiscountRow.error.message
                          : (addCustomDiscountRow.error as Error).message}
                      </div>
                    )}
                  </div>
                )}
              </section>
            )}

            {/* Staged rows */}
            {stagedRows.length > 0 && (
              <section>
                <div className="overflow-x-auto rounded-lg ring-1 ring-inset ring-hairline">
                  <table className="w-full min-w-[700px] border-separate border-spacing-0 text-[12.5px]">
                    <thead>
                      <tr className="bg-surface">
                        <Th>Service</Th>
                        <Th className="w-[90px] text-right">Price</Th>
                        <Th className="w-[140px]">Discount</Th>
                        <Th className="w-[90px] text-right">Net</Th>
                        <Th className="w-[80px] text-right">Tax</Th>
                        <Th className="w-[100px] text-right">Total</Th>
                        <Th className="w-[40px]" />
                      </tr>
                    </thead>
                    <tbody className="bg-elevated">
                      {stagedRows.map((r) => (
                        <tr key={r.id} className="border-t border-hairline/70">
                          <Td>{r.service_name}</Td>
                          <Td className="text-right nums-tabular text-fg-muted">{fmt(Number(r.service_price))}</Td>
                          <Td>
                            {r.discount_name ? (
                              <div>
                                <div className="truncate">{r.discount_name}</div>
                                {Number(r.discount_price) > 0 && (
                                  <div className="text-[11px] text-success nums-tabular">
                                    −{fmt(Number(r.discount_price))}
                                  </div>
                                )}
                              </div>
                            ) : (
                              <span className="text-fg-subtle">—</span>
                            )}
                          </Td>
                          <Td className="text-right nums-tabular">{fmt(Number(r.net_amount))}</Td>
                          <Td className="text-right nums-tabular text-fg-muted">
                            {Number(r.tax_price) > 0 ? fmt(Number(r.tax_price)) : '—'}
                          </Td>
                          <Td className="text-right nums-tabular font-medium text-fg">
                            {fmt(Number(r.tax_including_price))}
                          </Td>
                          <Td>
                            <button
                              type="button"
                              aria-label="Remove row"
                              onClick={() => removeRow.mutate(r)}
                              disabled={removeRow.isPending}
                              className="inline-flex size-6 items-center justify-center rounded-md text-fg-subtle transition-colors hover:bg-surface hover:text-danger focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50 disabled:opacity-50"
                            >
                              <Trash2 className="size-3" />
                            </button>
                          </Td>
                        </tr>
                      ))}
                      <tr className="border-t-2 border-hairline bg-surface">
                        <Td className="font-medium text-fg" colSpan={5}>
                          Total
                        </Td>
                        <Td className="text-right nums-tabular text-[13px] font-semibold text-fg">
                          {fmt(total)}
                        </Td>
                        <Td />
                      </tr>
                    </tbody>
                  </table>
                </div>
              </section>
            )}

            {/* Payment */}
            {stagedRows.length > 0 && (
              <section className="rounded-lg bg-surface p-3">
                <div className="mb-2 text-[11px] font-semibold uppercase tracking-wider text-fg-subtle">
                  Initial payment (optional)
                </div>
                <div className="grid grid-cols-1 gap-2 md:grid-cols-3">
                  <div>
                    <Label htmlFor="plan-payment-mode">Mode</Label>
                    <Select
                      id="plan-payment-mode"
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
                    <Label htmlFor="plan-cash">Amount</Label>
                    <Input
                      id="plan-cash"
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
                {overpaid && (
                  <div className="mt-2 text-[11.5px] text-warning">
                    Cash amount exceeds plan total. The server may reject this.
                  </div>
                )}
              </section>
            )}

            {save.error && (
              <ErrorBanner
                message={save.error instanceof ApiError ? save.error.message : (save.error as Error).message}
              />
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
              settled ||
              !patient ||
              !locationId ||
              !appointmentId ||
              stagedRows.length === 0
            }
          >
            {save.isPending && <Loader2 className="size-4 animate-spin" />}
            {isEdit ? 'Update plan' : subtype === 'bundle' ? 'Save bundle' : 'Save plan'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

// ── Patient picker (async search) ───────────────────────

function PatientPicker({
  id,
  value,
  onChange,
}: {
  id: string;
  value: PatientSearchResult | null;
  onChange: (p: PatientSearchResult | null) => void;
}) {
  const [query, setQuery] = useState('');
  const [debounced, setDebounced] = useState('');
  const [open, setOpen] = useState(false);

  useEffect(() => {
    const t = setTimeout(() => setDebounced(query.trim()), 250);
    return () => clearTimeout(t);
  }, [query]);

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
        {value.phone && <span className="text-[12px] text-fg-subtle">· {value.phone}</span>}
        <button
          type="button"
          aria-label="Clear patient"
          onClick={() => {
            onChange(null);
            setQuery('');
          }}
          className="ml-auto text-fg-subtle hover:text-fg"
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
          id={id}
          type="search"
          value={query}
          onChange={(e) => {
            setQuery(e.target.value);
            setOpen(true);
          }}
          onFocus={() => setOpen(true)}
          onBlur={() => setTimeout(() => setOpen(false), 150)}
          placeholder="Search patient name or phone…"
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

function Th({ children, className }: { children?: React.ReactNode; className?: string }) {
  return (
    <th
      className={
        'sticky top-0 border-b border-hairline px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-fg-subtle ' +
        (className ?? '')
      }
    >
      {children}
    </th>
  );
}

function Td({ children, className, colSpan }: { children?: React.ReactNode; className?: string; colSpan?: number }) {
  return (
    <td className={'px-3 py-2 align-top ' + (className ?? '')} colSpan={colSpan}>
      {children}
    </td>
  );
}

function ErrorBanner({ message }: { message: string }) {
  return (
    <div role="alert" className="flex items-start gap-2 rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
      <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
      <span>{message}</span>
    </div>
  );
}
