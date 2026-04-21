import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Plus, Trash2 } from 'lucide-react';
import { api } from '@/lib/api';
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
import { Select } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
  ConfigurableRulesEditor,
  collapseRulesFromServer,
  rulesToLegacyPayload,
  EMPTY_RULES,
  type ConfigurableRulesValue,
} from './configurable-rules-editor';
import { ServicePicker, type ServiceNode } from './service-picker';

/**
 * Discount allocation manager.
 *
 * Once a Discount is created, it's inactive until allocated to
 * location+service combinations via `discount_has_locations`. This
 * dialog is the admin surface for that.
 *
 * For **simple** discounts the admin picks a location + services and
 * sets the type (Fixed/Percentage) and amount that applies there. Type
 * and amount live on the allocation — different centres can carry
 * different terms under the same named discount.
 *
 * For **Configurable** discounts the admin only picks a location —
 * the services are implicit (carried on the discount's base/get
 * service records), so the backend exposes a separate
 * `/api/discounts/allocate-configurable` endpoint that creates a
 * single pivot row with slug="configurable".
 *
 * Endpoints:
 *   GET    /api/discounts/locations/{id}       — { discount, location (tree), discount_has_location[] }
 *   GET    /api/discounts/getDservice          — services for a given location (scoped by discount type)
 *   POST   /api/discounts/saveDervice          — save simple allocation
 *   POST   /api/discounts/allocate-configurable — save configurable allocation
 *   POST   /api/discounts/deleteDservice       — delete one pivot row
 */

type LocationChild = { id: number; name: string; slug: string };
type LocationNode = { id: number; name: string; optgroup: string; children: Record<string, LocationChild> };

type AllocationsResponse = {
  discount: {
    id: number;
    name: string;
    type: 'Fixed' | 'Percentage' | 'Configurable';
    amount: string | number | null;
    active: boolean | number;
  };
  location: Record<string, LocationNode>;
  discount_has_location: Array<{
    id: number;
    discount_id: number;
    location_id: number;
    service_id: number;
    type: string | null;
    amount: string | number | null;
    slug: string;
    service?: { id: number; name: string } | null;
    location?: { id: number; name: string; city?: { id: number; name: string } | null } | null;
  }>;
};

interface Props {
  discountId?: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/**
 * Local-only draft row. Lives in component state until Save commits it
 * via the API. Kept minimal — just the shape we need to render + send.
 */
type DraftAllocation = {
  /** Random local id for react keys + remove; server never sees this. */
  key: string;
  locationId: number;
  locationName: string;
  isConfigurable: boolean;
  /** Simple only. */
  type?: 'Fixed' | 'Percentage';
  amount?: number;
  serviceIds?: number[];
  serviceCount?: number;
};

export function DiscountAllocationDialog({ discountId, open, onOpenChange }: Props) {
  const qc = useQueryClient();
  const [locationId, setLocationId] = useState<string>('');
  const [allocType, setAllocType] = useState<'Fixed' | 'Percentage'>('Percentage');
  const [allocAmount, setAllocAmount] = useState<string>('');
  const [selectedServices, setSelectedServices] = useState<Set<number>>(new Set());
  const [drafts, setDrafts] = useState<DraftAllocation[]>([]);
  // Configurable rules live on the discount record (not per-allocation),
  // but we edit them on this screen now so Simple + Configurable share the
  // same "allocation is where the discount becomes real" flow.
  const [rules, setRules] = useState<ConfigurableRulesValue>(EMPTY_RULES);

  const data = useQuery({
    queryKey: ['discounts', 'allocations', discountId],
    queryFn: () => api.get<AllocationsResponse>(`/api/discounts/locations/${discountId}`),
    enabled: open && !!discountId,
  });

  const services = useQuery({
    queryKey: ['discounts', 'services-for-location', discountId, locationId],
    queryFn: () =>
      api.get<{ services: ServiceNode[] }>(
        `/api/discounts/getDservice?discount_id=${discountId}&location_id=${locationId}`,
      ),
    enabled: open && !!discountId && !!locationId && data.data?.discount.type !== 'Configurable',
  });

  // Full discount record, including current Buy/Get rules. Used for
  // Configurable discounts so the rules editor can pre-fill; also used
  // when committing to PUT the full shape back.
  const editData = useQuery({
    queryKey: ['discounts', 'edit', discountId],
    queryFn: () =>
      api.get<{
        discount: {
          name: string;
          discount_type: string;
          start: string;
          end: string;
          active: number | boolean;
          slug?: string | null;
          customer_type_id?: number | null;
        };
        base_discount_services?: Array<{ service_id: number; sessions: number | string; is_category?: number | boolean }>;
        get_discount_services?: Array<{
          service_id: number;
          sessions: number | string;
          discount_type: string;
          discount_amount: number | string;
          same_service: number | boolean;
        }>;
        selected_roles?: number[];
      }>(`/api/discounts/${discountId}/edit`),
    enabled: open && !!discountId,
  });

  // Service catalog for the Configurable rules editor's BUY/GET pickers.
  // Backend returns a TREE (parents with their `children` eager-loaded),
  // not a flat list. We forward it directly to ServicePicker — same shape
  // the Simple allocation picker consumes.
  const rulesCatalog = useQuery({
    queryKey: ['discounts', 'configurable-services'],
    queryFn: () =>
      api.get<{ services: ServiceNode[] }>('/api/discounts/services-for-configurable'),
    enabled: open && data.data?.discount.type === 'Configurable',
  });

  // Initialize local rules state from server on Configurable open.
  useEffect(() => {
    if (!open || data.data?.discount.type !== 'Configurable') return;
    if (!editData.data) return;
    const collapsed = collapseRulesFromServer(
      editData.data.base_discount_services ?? [],
      editData.data.get_discount_services ?? [],
    );
    // For brand-new Configurable discounts (no rules yet) seed one blank
    // GET row so the form isn't empty on first open — operator can fill
    // it out directly instead of having to click "Add row" first. Deleted
    // rows stay deleted; this only fires on initial load.
    if (collapsed.get_rows.length === 0) {
      collapsed.get_rows = [
        { sessions: 1, same_service: false, services_name: undefined, disc_type: '5', configurable_amount: 5 },
      ];
    }
    setRules(collapsed);
  }, [open, editData.data, data.data?.discount.type]);

  useEffect(() => {
    if (!open) {
      setLocationId('');
      setAllocAmount('');
      setSelectedServices(new Set());
      setDrafts([]);
    }
  }, [open]);

  const isConfigurable = data.data?.discount.type === 'Configurable';

  /**
   * Add the current form values to the local draft list. Nothing hits the
   * server until Save. The form clears afterward so the operator can queue
   * another allocation without scrolling back up.
   */
  const addToDraft = () => {
    if (!discountId || !locationId) return;
    const locName = findLocationName(data.data?.location ?? {}, Number(locationId));

    if (isConfigurable) {
      // Guard against duplicating a centre that's already drafted or saved.
      const alreadyDrafted = drafts.some(
        (d) => d.locationId === Number(locationId) && d.isConfigurable,
      );
      const alreadySaved = (data.data?.discount_has_location ?? []).some(
        (a) => a.location_id === Number(locationId),
      );
      if (alreadyDrafted || alreadySaved) {
        setStageError('This centre is already allocated for this discount.');
        return;
      }
      setDrafts((prev) => [
        ...prev,
        {
          key: localId(),
          locationId: Number(locationId),
          locationName: locName,
          isConfigurable: true,
        },
      ]);
    } else {
      if (!allocAmount.trim() || Number(allocAmount) <= 0) {
        setStageError('Enter an amount for this allocation.');
        return;
      }
      if (selectedServices.size === 0) {
        setStageError('Pick at least one service.');
        return;
      }
      setDrafts((prev) => [
        ...prev,
        {
          key: localId(),
          locationId: Number(locationId),
          locationName: locName,
          isConfigurable: false,
          type: allocType,
          amount: Number(allocAmount),
          serviceIds: Array.from(selectedServices),
          serviceCount: selectedServices.size,
        },
      ]);
    }

    setStageError(null);
    // Clear the form to prepare for the next entry; keep allocType default.
    setLocationId('');
    setAllocAmount('');
    setSelectedServices(new Set());
  };

  const removeDraft = (key: string) => {
    setDrafts((prev) => prev.filter((d) => d.key !== key));
  };

  /**
   * Commit all drafts serially. Each non-configurable draft also triggers
   * the discount-sync PUT so downstream plan-pricing readers see the
   * latest type/amount. Drafts that succeed are removed; failures stop
   * the loop and remaining drafts are kept for retry.
   */
  const commitDrafts = useMutation({
    mutationFn: async () => {
      if (!discountId) throw new Error('No discount id');

      // For Configurable, allow save with no draft centres — the user may
      // be editing only the rules (which still needs to commit). Simple
      // requires at least one draft since there's nothing else to save.
      if (!isConfigurable && drafts.length === 0) {
        throw new Error('No drafts to save.');
      }

      // Gate: don't let a Configurable allocation commit without real
      // Buy/Get rules. Saving a centre with an empty rule set would
      // create allocations the plan-pricing code can't apply.
      if (isConfigurable) {
        const buyIds = Array.isArray(rules.base_service)
          ? rules.base_service
          : rules.base_service != null
            ? [rules.base_service]
            : [];
        const getRows = rules.get_rows ?? [];
        const getHasService = getRows.some(
          (r) => r.same_service || r.services_name != null,
        );
        if (buyIds.length === 0) {
          throw new Error('Pick a BUY service or category before saving.');
        }
        if (getRows.length === 0 || !getHasService) {
          throw new Error('Add at least one GET row with a service selected.');
        }
      }

      // Step 1: For Configurable, push the current rules to the discount
      // record FIRST via a PUT. That way any subsequent allocate-configurable
      // calls land against the up-to-date rule set. We fetch the discount's
      // other fields (name/valid/roles/etc) first so the PUT payload is
      // complete per UpdateDiscountRequest's required-fields rule.
      if (isConfigurable && editData.data) {
        const d = editData.data.discount;
        // PUT goes to updateConfigurableDiscount which expects the
        // `edit_`-prefixed dynamic fields — without the prefix the
        // service sees empty values and rules silently fail to save.
        const rulesPayload = rulesToLegacyPayload(rules, 'edit');
        await api.put(`/api/discounts/${discountId}`, {
          name: d.name,
          type: 'Configurable',
          amount: 0,
          discount_type: d.discount_type,
          start: d.start,
          end: d.end,
          active: d.active ? '1' : '0',
          slug: d.slug ?? 'default',
          roles: editData.data.selected_roles ?? [],
          customer_type_id: d.customer_type_id ?? null,
          ...rulesPayload,
        });
      }

      // Step 2: Walk through draft allocations serially so error-on-N stops
      // cleanly. Successful drafts are removed as they land so the operator
      // can see progress + retry only what failed.
      const remaining: DraftAllocation[] = [...drafts];
      let lastSimple: DraftAllocation | null = null;

      while (remaining.length > 0) {
        const draft = remaining[0];
        if (draft.isConfigurable) {
          await api.post('/api/discounts/allocate-configurable', {
            discount_id: discountId,
            location_id: draft.locationId,
          });
        } else {
          const resp = await api.post<{ status?: boolean; message?: string } | null>(
            '/api/discounts/saveDervice',
            {
              discount_id: discountId,
              location_id: draft.locationId,
              allocation_type: draft.type,
              allocation_amount: draft.amount,
              allocation_slug: 'custom',
              service_ids: draft.serviceIds ?? [],
            },
            { raw: true },
          );
          if (resp && resp.status === false) {
            throw new Error(resp.message ?? 'Allocation failed.');
          }
          lastSimple = draft;
        }
        remaining.shift();
        setDrafts([...remaining]);
      }

      // Step 3: For Simple, sync the parent discount row's type/amount to
      // the last draft's values so downstream plan-pricing readers see the
      // latest. (Configurable already did its PUT in step 1.)
      if (lastSimple && !isConfigurable) {
        try {
          const existing = await api.get<{
            discount: {
              name: string;
              discount_type: string;
              start: string;
              end: string;
              active: number | boolean;
              slug?: string | null;
              customer_type_id?: number | null;
            };
            selected_roles?: number[];
          }>(`/api/discounts/${discountId}/edit`);

          const d = existing.discount;
          await api.put(`/api/discounts/${discountId}`, {
            name: d.name,
            type: lastSimple.type,
            amount: lastSimple.amount,
            discount_type: d.discount_type,
            start: d.start,
            end: d.end,
            active: d.active ? '1' : '0',
            slug: d.slug ?? 'default',
            roles: existing.selected_roles ?? [],
            customer_type_id: d.customer_type_id ?? null,
          });
        } catch {
          /* non-fatal — the allocation is authoritative at runtime */
        }
      }
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['discounts', 'allocations', discountId] });
      qc.invalidateQueries({ queryKey: ['discounts', 'datatable'] });
      setDrafts([]);
      onOpenChange(false);
    },
  });

  const [stageError, setStageError] = useState<string | null>(null);

  const del = useMutation({
    mutationFn: (id: number) =>
      api.post('/api/discounts/deleteDservice', { id }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['discounts', 'allocations', discountId] }),
  });

  const existing = data.data?.discount_has_location ?? [];
  const tree = data.data?.location ?? {};

  // Flat id→name lookup over the configurable catalog — used by the
  // allocation list to render "Buy 3× X → Get 2× Y (10% off)" summaries
  // per Configurable row. Safe on Simple discounts (empty map).
  const ruleLookup = useMemo(() => {
    const m = new Map<number, string>();
    const walk = (n: { id: number; name: string; children?: typeof n[] }) => {
      m.set(n.id, n.name);
      n.children?.forEach(walk);
    };
    (rulesCatalog.data?.services ?? []).forEach(walk);
    return m;
  }, [rulesCatalog.data]);

  const rulesSummary = useMemo(
    () => (isConfigurable ? formatRulesSummary(rules, ruleLookup) : ''),
    [isConfigurable, rules, ruleLookup],
  );
  const canSave =
    !!locationId &&
    (isConfigurable
      ? true
      : selectedServices.size > 0 && allocAmount.trim() !== '' && Number(allocAmount) > 0);

  const toggleService = (id: number) => {
    const next = new Set(selectedServices);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    setSelectedServices(next);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>
            {data.data?.discount.name
              ? `“${data.data.discount.name}” — where does it apply?`
              : 'Where does this discount apply?'}
          </DialogTitle>
          <DialogDescription>
            {isConfigurable
              ? 'Define the Buy/Get rules below, then add the centres where this discount is valid. Rules apply to every centre.'
              : 'Add a centre, select the services, and set the amount for each one. Different centres can carry different terms.'}
          </DialogDescription>
        </DialogHeader>

        {data.isLoading ? (
          <Skeleton className="h-32 w-full" />
        ) : (
          <div className="space-y-6 max-h-[65vh] overflow-y-auto pr-1">
            {/* Unified allocations list — saved rows first, then pending
                drafts with a "Pending" badge. "Add to list" below pushes
                into the draft array which appears here immediately so the
                operator sees the action had an effect. */}
            <section>
              <div className="mb-3 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">
                Allocations{' '}
                <span className="text-fg-muted">
                  · {existing.length + drafts.length}
                  {drafts.length > 0 && (
                    <span className="ml-1 text-brand-blue">({drafts.length} pending)</span>
                  )}
                </span>
              </div>
              {existing.length === 0 && drafts.length === 0 ? (
                <div className="rounded-lg border border-dashed border-hairline px-3 py-6 text-center text-[12.5px] text-fg-muted">
                  No allocations yet — fill the form below and click Add to list.
                </div>
              ) : (
                <ul className="divide-y divide-hairline/60 rounded-lg ring-1 ring-inset ring-hairline">
                  {existing.map((a) => (
                    <li key={`saved-${a.id}`} className="flex items-start justify-between gap-2 px-3 py-2 text-[12.5px]">
                      <div className="min-w-0">
                        {/* Configurable rows prioritize the centre as the
                            primary identifier (service is implicit — "All
                            Services" is just a magic sentinel). Simple rows
                            keep the service-name-first layout. */}
                        <div className="truncate text-fg">
                          {a.slug === 'configurable'
                            ? a.location?.name ?? `Location #${a.location_id}`
                            : a.service?.name ?? `Service #${a.service_id}`}
                        </div>
                        <div className="text-fg-subtle">
                          {a.slug === 'configurable' ? (
                            rulesSummary || 'Configurable'
                          ) : (
                            <>
                              {a.location?.city?.name ? `${a.location.city.name} — ` : ''}
                              {a.location?.name ?? `Location #${a.location_id}`}
                              {a.type && a.amount && (
                                <>
                                  {' '}· <span className="text-fg">{a.type === 'Percentage' ? `${Number(a.amount).toFixed(0)}%` : Number(a.amount).toLocaleString()}</span>
                                </>
                              )}
                            </>
                          )}
                        </div>
                      </div>
                      <button
                        type="button"
                        aria-label={`Remove allocation ${a.id}`}
                        onClick={() => del.mutate(a.id)}
                        disabled={del.isPending}
                        className="inline-flex size-7 items-center justify-center rounded-md text-fg-subtle transition-colors hover:bg-surface hover:text-danger focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
                      >
                        <Trash2 className="size-3.5" />
                      </button>
                    </li>
                  ))}
                  {drafts.map((d) => (
                    <li
                      key={d.key}
                      className="flex items-center justify-between gap-2 bg-brand-blue-soft/30 px-3 py-2 text-[12.5px]"
                    >
                      <div className="min-w-0">
                        <div className="flex items-center gap-2">
                          <span className="truncate text-fg">{d.locationName}</span>
                          <Badge variant="brand">Pending</Badge>
                        </div>
                        <div className="text-fg-subtle">
                          {d.isConfigurable ? (
                            rulesSummary || 'Buy/Get rules not defined yet'
                          ) : (
                            <>
                              <span className="text-fg">
                                {d.type === 'Percentage'
                                  ? `${d.amount}%`
                                  : Number(d.amount).toLocaleString()}
                              </span>{' '}
                              · {d.serviceCount} {d.serviceCount === 1 ? 'service' : 'services'}
                            </>
                          )}
                        </div>
                      </div>
                      <button
                        type="button"
                        aria-label="Remove draft"
                        onClick={() => removeDraft(d.key)}
                        className="inline-flex size-7 items-center justify-center rounded-md text-fg-subtle transition-colors hover:bg-surface hover:text-danger focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
                      >
                        <Trash2 className="size-3.5" />
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </section>

            {/* Buy/Get rules for Configurable discounts — moved here from
                the create form so Simple + Configurable share the same
                "allocation screen defines how the discount applies" pattern.
                Rules are discount-wide (one set); edits here apply to every
                centre this discount is allocated to. */}
            {isConfigurable && (
              <section>
                <div className="mb-3 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">
                  Buy / Get rules
                  <span className="ml-2 font-normal normal-case text-fg-muted">
                    (applies to every centre under this discount)
                  </span>
                </div>
                {editData.isLoading || rulesCatalog.isLoading ? (
                  <Skeleton className="h-40 w-full" />
                ) : (
                  <ConfigurableRulesEditor
                    value={rules}
                    onChange={setRules}
                    catalog={rulesCatalog.data?.services ?? []}
                    disabled={commitDrafts.isPending}
                  />
                )}
              </section>
            )}

            {/* Drafts render inline inside the unified allocations list
                above (with "Pending" badges) so "Add to list" visibly
                bumps the Allocations count. No separate drafts section. */}

            {/* Add new allocation */}
            <section className="rounded-lg ring-1 ring-inset ring-hairline">
              <div className="border-b border-hairline px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">
                Add allocation
              </div>
              <div className="space-y-4 p-4">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                  <div className="sm:col-span-3">
                    <Label htmlFor="alloc-loc">Centre</Label>
                    <Select
                      id="alloc-loc"
                      value={locationId}
                      onChange={(e) => {
                        setLocationId(e.target.value);
                        setSelectedServices(new Set());
                      }}
                    >
                      <option value="">Choose centre…</option>
                      {Object.values(tree).map((region) => (
                        <optgroup key={region.id} label={region.optgroup}>
                          {Object.values(region.children).map((c) => (
                            <option key={c.id} value={c.id}>
                              {c.name.replace(/&nbsp;/g, '').trim()}
                            </option>
                          ))}
                        </optgroup>
                      ))}
                    </Select>
                  </div>

                  {!isConfigurable && (
                    <>
                      <div>
                        <Label htmlFor="alloc-type">Type <span className="text-danger">*</span></Label>
                        <Select
                          id="alloc-type"
                          value={allocType}
                          onChange={(e) => setAllocType(e.target.value as 'Fixed' | 'Percentage')}
                        >
                          <option value="Percentage">Percentage</option>
                          <option value="Fixed">Fixed</option>
                        </Select>
                      </div>
                      <div className="sm:col-span-2">
                        <Label htmlFor="alloc-amount">
                          Amount {allocType === 'Percentage' ? '(%)' : ''} <span className="text-danger">*</span>
                        </Label>
                        <Input
                          id="alloc-amount"
                          type="number"
                          step="0.01"
                          min="0"
                          value={allocAmount}
                          onChange={(e) => setAllocAmount(e.target.value)}
                          placeholder={allocType === 'Percentage' ? '15' : '500'}
                        />
                      </div>
                    </>
                  )}
                </div>

                {!isConfigurable && locationId && (
                  <div>
                    <div className="mb-1.5 flex items-center justify-between">
                      <Label>Services</Label>
                      {selectedServices.size > 0 && (
                        <button
                          type="button"
                          onClick={() => setSelectedServices(new Set())}
                          className="text-[11.5px] font-medium text-fg-subtle hover:text-fg"
                        >
                          Clear ({selectedServices.size})
                        </button>
                      )}
                    </div>
                    {services.isLoading ? (
                      <Skeleton className="h-32 w-full" />
                    ) : (
                      <ServicePicker
                        nodes={services.data?.services ?? []}
                        selected={selectedServices}
                        onToggle={toggleService}
                      />
                    )}
                  </div>
                )}

                {stageError && (
                  <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
                    {stageError}
                  </div>
                )}
                {commitDrafts.error && (
                  <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
                    {(commitDrafts.error as Error).message}
                  </div>
                )}

                {/* "Add to list" stages locally — no API call yet. Save
                    commits all drafts; Cancel discards them. */}
                <div className="flex items-center justify-end pt-1">
                  <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    disabled={!canSave || commitDrafts.isPending}
                    onClick={addToDraft}
                  >
                    <Plus className="size-3.5" />
                    Add to list
                  </Button>
                </div>
              </div>
            </section>
          </div>
        )}

        <DialogFooter className="flex-col items-stretch gap-2 border-t border-hairline pt-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="text-[12px] text-fg-subtle">
            {drafts.length > 0 ? (
              <>
                <span className="font-medium text-fg">{drafts.length}</span>{' '}
                {drafts.length === 1 ? 'draft' : 'drafts'} ready to save
              </>
            ) : (existing.length > 0 ? (
              <>Pick a centre to add another allocation.</>
            ) : (
              <>Add allocations above, then Save.</>
            ))}
          </div>
          <div className="flex items-center justify-end gap-2">
            <Button
              type="button"
              variant="secondary"
              size="sm"
              disabled={commitDrafts.isPending}
              onClick={() => {
                setDrafts([]);
                onOpenChange(false);
              }}
            >
              Cancel
            </Button>
            <Button
              type="button"
              size="sm"
              disabled={
                commitDrafts.isPending ||
                // Simple: must have at least one draft to save.
                // Configurable: may save with just a rules edit (no draft).
                (isConfigurable ? false : drafts.length === 0)
              }
              onClick={() => commitDrafts.mutate()}
            >
              {commitDrafts.isPending && <Loader2 className="size-3.5 animate-spin" />}
              Save {drafts.length > 0 ? `(${drafts.length})` : ''}
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}


/**
 * Render a Configurable discount's Buy/Get rules as a single readable
 * sentence, e.g. "Buy 3× Laser Hair Removal → Get 2× Facial (10% off) +
 * 1× Ultraformer (free)". Used on allocation rows so operators see what
 * each Configurable row actually does.
 */
function formatRulesSummary(
  rules: ConfigurableRulesValue,
  lookup: Map<number, string>,
): string {
  const buyIds = Array.isArray(rules.base_service)
    ? rules.base_service
    : rules.base_service != null
      ? [rules.base_service]
      : [];
  const getRows = rules.get_rows ?? [];

  // If neither Buy nor any Get row has a real service selected, the rules
  // are effectively empty — say so plainly rather than rendering a string
  // full of "(service)" placeholders.
  const getHasAnyService = getRows.some((r) => r.same_service || r.services_name != null);
  if (buyIds.length === 0 && !getHasAnyService) {
    return 'Buy/Get rules not set — edit below';
  }

  // Tag each buy id with whether it's a parent (category) so the summary
  // reads "3 sessions from Laser category" instead of just the name when
  // the operator chose the category-level node.
  const buyPart =
    buyIds.length === 0
      ? `Buy ${rules.sessions_buy}× (service not set)`
      : `Buy ${rules.sessions_buy}× ${buyIds
          .map((id) => lookup.get(id) ?? `#${id}`)
          .join(', ')}${rules.buy_mode === 'category' ? ' (any)' : ''}`;

  if (getRows.length === 0) return buyPart;

  const getParts = getRows.map((r) => {
    const name = r.same_service
      ? 'same service'
      : r.services_name != null
        ? lookup.get(r.services_name) ?? `#${r.services_name}`
        : '(service not set)';
    const disc = r.disc_type === 'complimentory' ? 'free' : `${r.disc_type}% off`;
    return `${r.sessions}× ${name} (${disc})`;
  });
  return `${buyPart} → Get ${getParts.join(' + ')}`;
}

/** Cheap unique id for draft react keys. `crypto.randomUUID` needs a
 *  secure context (HTTPS / localhost) which the dev `.test` domain over
 *  http doesn't satisfy, so we fall back to a counter + Math.random. */
let _counter = 0;
function localId(): string {
  _counter += 1;
  return `d-${Date.now().toString(36)}-${_counter}-${Math.floor(Math.random() * 1e9).toString(36)}`;
}

/** Look up a location's display name from the `location` tree returned by
 *  /api/discounts/locations/{id}. Used to render draft rows without a
 *  second API call. */
function findLocationName(tree: Record<string, LocationNode>, id: number): string {
  for (const region of Object.values(tree)) {
    for (const child of Object.values(region.children)) {
      if (child.id === id) return child.name.replace(/&nbsp;/g, '').trim();
    }
  }
  return `Centre #${id}`;
}
