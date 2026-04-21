import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Controller, useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { CalendarRange, ChevronsUpDown, Loader2, Search, X } from 'lucide-react';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
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

/**
 * Discount create/edit dialog. Supports all three backend types:
 *
 *   Fixed       — flat currency amount off (e.g. 500 off any service)
 *   Percentage  — % off (e.g. 15% off)
 *   Configurable — "buy N of X, get Y" (spawns N rows in base_discount_services
 *                  per buy session, plus M rows in get_discount_services per
 *                  GET row's sessions count)
 *
 * Endpoints:
 *   GET  /api/discounts/create              — dropdown data (types, roles, locations, customer types)
 *   GET  /api/discounts/{id}/edit           — full prefill incl. roles, base_discount_services, get_discount_services
 *   GET  /api/discounts/services-for-configurable — service catalog for BUY/GET pickers
 *   POST /api/discounts                     — create (server routes on `type`)
 *   PUT  /api/discounts/{id}                — update
 *
 * Configurable payload uses the server's legacy dynamic-field shape:
 *   { sessions_buy: N, base_service: id | [ids], buy_mode: 'service'|'category',
 *     sessions: {0: n0, 1: n1, ...},
 *     services_name: {0: id, 1: id, ...},
 *     disc_type: {0: 'complimentory'|'NN', ...},
 *     configurable_amount: {0: amount, ...},
 *     same_service: {0: '0'|'1', ...} }
 *
 * On update, the server expects the same keys (no "edit_" prefix — the SPA
 * path uses DiscountService::updateConfigurableDiscount which handles
 * raw keys the same way).
 */

type LookupDict = Record<string, string>;
type LocationNode = {
  id: number;
  name: string;
  optgroup: string;
  children: Record<string, { id: number; name: string; slug: string }>;
};
type CreateData = {
  discount_types: LookupDict;
  discount_groups: LookupDict;
  amount_types: LookupDict;
  roles: LookupDict;
  locations: Record<string, LocationNode>;
  customer_types: LookupDict;
};

type EditData = CreateData & {
  discount: {
    id: number;
    slug: string;
    name: string;
    type: 'Fixed' | 'Percentage' | 'Configurable';
    amount: string | number | null;
    discount_type: string;
    start: string;
    end: string;
    active: boolean | number;
    customer_type_id: number | null;
  };
  base_discount_services: Array<{
    service_id: number;
    sessions: number;
    is_category: number | boolean;
  }>;
  get_discount_services: Array<{
    service_id: number;
    sessions: number;
    discount_type: string;
    discount_amount: string | number;
    same_service: number | boolean;
    base_service_id: number;
  }>;
  selected_roles: number[];
};


const getRowSchema = z.object({
  sessions: z.coerce.number().int().min(1, 'At least 1 session.'),
  same_service: z.boolean(),
  services_name: z.coerce.number().int().optional(),
  disc_type: z.string().min(1, 'Pick comp or percentage.'),
  configurable_amount: z.coerce.number().min(0).optional(),
});

const schema = z
  .object({
    name: z.string().min(1, 'Discount name is required.').max(255),
    type: z.enum(['Fixed', 'Percentage', 'Configurable']),
    amount: z.coerce.number().min(0).optional(),
    discount_type: z.string().min(1),
    slug: z.string().optional(),
    start: z.string().min(1, 'Start date is required.'),
    end: z.string().min(1, 'End date is required.'),
    active: z.boolean(),
    roles: z.array(z.coerce.number().int()).min(1, 'Pick at least one role.'),
    customer_type_id: z.coerce.number().int().optional().or(z.literal('').transform(() => undefined)),
    // Configurable-only
    buy_mode: z.enum(['service', 'category']).optional(),
    sessions_buy: z.coerce.number().int().min(1).optional(),
    base_service: z.union([z.coerce.number().int(), z.array(z.coerce.number().int())]).optional(),
    get_rows: z.array(getRowSchema).optional(),
  })
  .superRefine((values, ctx) => {
    // Simple discounts no longer require type/amount at this level — they're
    // set per-allocation on the allocation dialog. Configurable's Buy/Get
    // rules also moved to the allocation dialog, so the create form only
    // validates discount-level metadata (name, type, validity, roles, etc.).
    // The first allocation saved by the SPA issues a follow-up PUT that
    // populates the values downstream plan-pricing readers depend on.
    if (values.start && values.end && values.end < values.start) {
      ctx.addIssue({ code: 'custom', path: ['end'], message: 'End must be on or after start.' });
    }
  });

type FormValues = z.infer<typeof schema>;

const EMPTY: FormValues = {
  name: '',
  type: 'Percentage',
  amount: 0,
  discount_type: 'Treatment',
  slug: 'default',
  start: '',
  end: '',
  active: true,
  roles: [],
  customer_type_id: undefined,
  buy_mode: 'service',
  sessions_buy: 1,
  base_service: undefined,
  get_rows: [],
};

interface Props {
  mode: 'create' | 'edit';
  discountId?: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Fires after a successful create with the new id. Used by the list
   *  page to auto-open the allocation dialog so operators don't have
   *  to hunt for it. */
  onCreated?: (id: number) => void;
}

export function DiscountFormDialog({ mode, discountId, open, onOpenChange, onCreated }: Props) {
  const qc = useQueryClient();

  // Form-data query — in edit mode the response contains everything
  // create-mode needs PLUS the discount record + children. Reuse the
  // same cache entry across modes so the role/location/type lookups
  // don't refetch unnecessarily.
  const formData = useQuery<EditData | CreateData>({
    queryKey: ['discounts', 'form-data', mode, discountId],
    queryFn: () =>
      mode === 'edit' && discountId
        ? api.get<EditData>(`/api/discounts/${discountId}/edit`)
        : api.get<CreateData>('/api/discounts/create'),
    enabled: open && (mode === 'create' || !!discountId),
  });

  const {
    register,
    handleSubmit,
    reset,
    control,
    watch,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: EMPTY,
  });
  const type = useWatch({ control, name: 'type' });
  void watch;

  // Prefill on open + when data lands.
  useEffect(() => {
    if (!open || !formData.data) return;
    const data = formData.data as Partial<EditData>;
    if (mode === 'edit' && data.discount) {
      const d = data.discount;
      // Derive buy_mode + base_service from the first base_discount_services row.
      // NOTE: `sessions_buy` is stored on EVERY row by the backend (DiscountService.buildBaseServices:650
      // writes `'sessions' => $sessionCount`) — read it directly off the first
      // row rather than dividing row count by unique service IDs. The old
      // `bases.length / uniqueBaseIds.length` formula silently rounded when
      // rows drifted (soft-deletes, manual DB edits, uneven categories) and
      // then the next save wrote the wrong session count back.
      const bases = data.base_discount_services ?? [];
      const firstBase = bases[0];
      const buyModeVal: 'service' | 'category' =
        firstBase && Number(firstBase.is_category) === 1 ? 'category' : 'service';
      const uniqueBaseIds = Array.from(new Set(bases.map((b) => b.service_id)));
      const sessionsBuy = firstBase ? Math.max(1, Number(firstBase.sessions) || 1) : 1;
      const baseServiceVal =
        buyModeVal === 'category' ? uniqueBaseIds : uniqueBaseIds[0];

      // Collapse get_discount_services (which stores N rows per GET session)
      // into grouped rows keyed by (service_id, discount_type, same_service).
      const grouped = new Map<
        string,
        { sessions: number; same_service: boolean; services_name: number; disc_type: string; configurable_amount: number }
      >();
      for (const r of data.get_discount_services ?? []) {
        const sameService = Number(r.same_service) === 1;
        const key = `${sameService ? 'same' : r.service_id}|${r.discount_type}|${r.discount_amount}`;
        const existing = grouped.get(key);
        if (existing) {
          existing.sessions += 1;
        } else {
          grouped.set(key, {
            sessions: 1,
            same_service: sameService,
            services_name: Number(r.service_id),
            disc_type: String(r.discount_type),
            configurable_amount: Number(r.discount_amount),
          });
        }
      }
      const getRows = Array.from(grouped.values());

      reset({
        name: d.name ?? '',
        type: d.type ?? 'Percentage',
        amount: d.amount !== null && d.amount !== undefined ? Number(d.amount) : 0,
        discount_type: d.discount_type ?? 'Treatment',
        slug: d.slug ?? 'default',
        start: d.start ?? '',
        end: d.end ?? '',
        active: !!d.active,
        roles: (data.selected_roles ?? []).map(Number),
        customer_type_id: d.customer_type_id ?? undefined,
        buy_mode: buyModeVal,
        sessions_buy: sessionsBuy,
        base_service: baseServiceVal,
        get_rows: getRows,
      });
    } else {
      reset(EMPTY);
    }
  }, [open, mode, formData.data, reset]);

  const mutation = useMutation({
    mutationFn: (values: FormValues) => {
      const base = {
        name: values.name,
        type: values.type,
        discount_type: values.discount_type,
        slug: values.slug || 'default',
        start: values.start,
        end: values.end,
        active: values.active ? '1' : '0',
        roles: values.roles,
        customer_type_id: values.customer_type_id || null,
      };
      let payload: Record<string, unknown>;
      if (values.type !== 'Configurable') {
        payload = { ...base, amount: values.amount ?? 0 };
      } else if (mode === 'edit') {
        // Edit mode: round-trip existing Buy/Get rules so the server's
        // updateConfigurableDiscount doesn't wipe them when rebuilding.
        // Rules are *edited* on the allocation dialog; the form edit just
        // modifies metadata + preserves rules in the update payload.
        const sessions: Record<number, number> = {};
        const services_name: Record<number, number> = {};
        const disc_type: Record<number, string> = {};
        const configurable_amount: Record<number, number> = {};
        const same_service: Record<number, string> = {};
        (values.get_rows ?? []).forEach((r, i) => {
          sessions[i] = r.sessions;
          services_name[i] = r.services_name ?? 0;
          disc_type[i] = r.disc_type;
          configurable_amount[i] = r.configurable_amount ?? 0;
          same_service[i] = r.same_service ? '1' : '0';
        });
        payload = {
          ...base,
          buy_mode: values.buy_mode,
          sessions_buy: values.sessions_buy,
          base_service: values.base_service,
          sessions,
          services_name,
          disc_type,
          configurable_amount,
          same_service,
        };
      } else {
        // Create mode: bare discount, no Buy/Get. The allocation dialog
        // follow-up PUT sets the rules.
        payload = { ...base };
      }
      return mode === 'create'
        ? api.post<{ id?: number } | null>('/api/discounts', payload)
        : api.put(`/api/discounts/${discountId}`, payload);
    },
    onSuccess: (resp) => {
      qc.invalidateQueries({ queryKey: ['discounts', 'datatable'] });
      qc.invalidateQueries({ queryKey: ['discounts', 'form-data'] });
      onOpenChange(false);
      if (mode === 'create' && onCreated) {
        const newId = (resp as { id?: number } | null)?.id;
        if (typeof newId === 'number') onCreated(newId);
      }
    },
    onError: (err) => {
      if (err instanceof ApiError) {
        for (const [field, messages] of Object.entries(err.fieldErrors)) {
          const flat = field.split('.')[0];
          const known: Array<keyof FormValues> = [
            'name', 'type', 'amount', 'discount_type', 'start', 'end',
            'roles', 'customer_type_id',
            'sessions_buy', 'base_service', 'buy_mode',
          ];
          if ((known as string[]).includes(flat)) {
            setError(flat as keyof FormValues, { message: (messages as string[])[0] });
          }
        }
      }
    },
  });

  const onSubmit = handleSubmit((values) => mutation.mutate(values));

  const lookups = (formData.data as CreateData | EditData | undefined) ?? undefined;
  const roles = lookups?.roles ?? {};
  const customerTypes = lookups?.customer_types ?? {};

  const loading = formData.isLoading;
  const title = mode === 'create' ? 'Add discount' : 'Edit discount';

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl p-5">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>

        <form onSubmit={onSubmit} className="space-y-3 max-h-[70vh] overflow-y-auto pr-1">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div className="sm:col-span-2">
              <Label htmlFor="disc-name">
                Name <span className="text-danger">*</span>
              </Label>
              <Input id="disc-name" autoFocus disabled={loading} {...register('name')} />
              {errors.name && <p role="alert" className="mt-1 text-[12px] text-danger">{errors.name.message}</p>}
            </div>

            <div>
              <Label>Type <span className="text-danger">*</span></Label>
              <div className="flex flex-wrap gap-3 pt-1 text-[13px]">
                {/* Collapsed to two modes. "Simple" covers Fixed + Percentage —
                    the actual Fixed/Percentage + amount decision now lives on
                    each allocation so different centres can apply different
                    terms under the same named discount. On submit, Simple is
                    mapped to Percentage with amount=0 as a server placeholder;
                    the first allocation saved overwrites both fields via
                    DiscountService::saveAllocations. */}
                <label
                  className={
                    'inline-flex items-center gap-2 ' +
                    (mode === 'edit' ? 'cursor-not-allowed opacity-60' : '')
                  }
                >
                  <input
                    type="radio"
                    value="Percentage"
                    disabled={loading || mode === 'edit'}
                    {...register('type')}
                  />
                  Simple
                </label>
                <label
                  className={
                    'inline-flex items-center gap-2 ' +
                    (mode === 'edit' ? 'cursor-not-allowed opacity-60' : '')
                  }
                >
                  <input
                    type="radio"
                    value="Configurable"
                    disabled={loading || mode === 'edit'}
                    {...register('type')}
                  />
                  Configurable (Buy/Get)
                </label>
              </div>
              {mode === 'edit' ? (
                <p className="mt-1 text-[11.5px] text-fg-subtle">
                  Type is locked after creation. Create a new discount to change this.
                </p>
              ) : (
                <p className="mt-1 text-[11.5px] text-fg-subtle">
                  {type === 'Configurable'
                    ? 'Configurable — buy N of one service, get M of another at a discount.'
                    : 'Simple — a fixed amount or percentage off, set per centre.'}
                </p>
              )}
            </div>

            {/* Amount input removed for Simple type — moved to the
                allocation dialog. Configurable keeps its buy/get rules
                below, which replace the scalar amount entirely. */}

            <div>
              <Label>Valid <span className="text-danger">*</span></Label>
              <Controller
                control={control}
                name="start"
                render={({ field: startField }) => (
                  <Controller
                    control={control}
                    name="end"
                    render={({ field: endField }) => (
                      <ValidRangePopover
                        start={startField.value || ''}
                        end={endField.value || ''}
                        onStartChange={startField.onChange}
                        onEndChange={endField.onChange}
                        disabled={loading}
                      />
                    )}
                  />
                )}
              />
              {(errors.start || errors.end) && (
                <p role="alert" className="mt-1 text-[12px] text-danger">
                  {errors.start?.message ?? errors.end?.message}
                </p>
              )}
            </div>

            <div>
              <Label htmlFor="disc-cust">Customer type</Label>
              <Select id="disc-cust" disabled={loading} {...register('customer_type_id')}>
                <option value="">— Any —</option>
                {Object.entries(customerTypes).map(([k, v]) => (
                  <option key={k} value={k}>{v}</option>
                ))}
              </Select>
            </div>

            <div>
              <Label>Staff roles allowed <span className="text-danger">*</span></Label>
              <Controller
                control={control}
                name="roles"
                render={({ field }) => (
                  <RolesMultiSelect
                    options={roles}
                    value={(field.value ?? []).map(Number)}
                    onChange={field.onChange}
                    disabled={loading}
                  />
                )}
              />
              <p className="mt-1 text-[11.5px] text-fg-subtle">
                Only users in these roles can apply the discount.
              </p>
              {errors.roles && <p role="alert" className="mt-1 text-[12px] text-danger">{errors.roles.message}</p>}
            </div>

            <div className="sm:col-span-2">
              <label className="inline-flex items-center gap-2 text-[13px]">
                <input type="checkbox" disabled={loading} className="size-4 rounded border-hairline" {...register('active')} />
                Active
              </label>
            </div>
          </div>

          {type === 'Configurable' && mode === 'create' && (
            <div className="rounded-lg bg-brand-blue-soft/40 px-3 py-2 text-[12px] text-brand-blue ring-1 ring-inset ring-brand-blue/30">
              Buy/Get rules are defined on the allocation screen after save,
              so different centres can inherit the same rule set.
            </div>
          )}


          {mutation.error && (
            <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
              {(mutation.error as Error).message}
            </div>
          )}

          <DialogFooter>
            <Button type="button" variant="secondary" size="sm" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="submit" size="sm" disabled={isSubmitting || mutation.isPending || loading}>
              {(isSubmitting || mutation.isPending) && <Loader2 className="size-3.5 animate-spin" />}
              {mode === 'create' ? 'Create' : 'Save changes'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

/**
 * Single-button date range picker in the shape of the list's filter pills.
 * Click the button → popover opens with two date inputs; the trigger shows
 * the current range (or "Pick dates" if empty). Works with any controlled
 * pair of YYYY-MM-DD values — here driven by react-hook-form's Controller.
 */
function ValidRangePopover({
  start,
  end,
  onStartChange,
  onEndChange,
  disabled,
}: {
  start: string;
  end: string;
  onStartChange: (v: string) => void;
  onEndChange: (v: string) => void;
  disabled?: boolean;
}) {
  const hasRange = !!(start || end);
  const label = hasRange
    ? `${formatDateLabel(start) || '—'} → ${formatDateLabel(end) || '—'}`
    : 'Pick dates';

  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          type="button"
          disabled={disabled}
          aria-label="Valid date range"
          className={
            'flex h-9 w-full items-center gap-2 rounded-lg bg-elevated px-3 text-left text-[13px] ring-1 ring-inset ring-hairline ' +
            'hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50 ' +
            'disabled:cursor-not-allowed disabled:opacity-60'
          }
        >
          <CalendarRange className="size-3.5 text-fg-subtle" aria-hidden />
          <span className={hasRange ? 'text-fg' : 'text-fg-subtle'}>{label}</span>
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-[240px] p-3">
        <div className="space-y-2">
          <div>
            <div className="mb-0.5 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">From</div>
            <Input
              type="date"
              aria-label="From"
              value={start}
              onChange={(e) => onStartChange(e.target.value)}
            />
          </div>
          <div>
            <div className="mb-0.5 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">To</div>
            <Input
              type="date"
              aria-label="To"
              value={end}
              onChange={(e) => onEndChange(e.target.value)}
            />
          </div>
          {hasRange && (
            <button
              type="button"
              onClick={() => {
                onStartChange('');
                onEndChange('');
              }}
              className="mt-1 text-[11.5px] font-medium text-fg-subtle hover:text-fg"
            >
              Clear
            </button>
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}

function formatDateLabel(value: string): string {
  if (!value) return '';
  // Intl is locale-aware; using UTC to avoid off-by-one when the string is
  // a plain YYYY-MM-DD (interpreted as midnight UTC by new Date()).
  const d = new Date(value + 'T00:00:00Z');
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

/**
 * Compact roles multi-select. Trigger shows chips for the current
 * selection (truncated to 3, with "+N more" badge beyond). Opens a
 * popover with a search filter, scrollable options list, and quick
 * Select all / Clear helpers.
 */
function RolesMultiSelect({
  options,
  value,
  onChange,
  disabled,
}: {
  options: Record<string, string>;
  value: number[];
  onChange: (next: number[]) => void;
  disabled?: boolean;
}) {
  const [query, setQuery] = useState('');
  const [debounced, setDebounced] = useState('');

  useEffect(() => {
    const t = setTimeout(() => setDebounced(query.trim().toLowerCase()), 120);
    return () => clearTimeout(t);
  }, [query]);

  const entries = Object.entries(options); // [id, name]
  const filtered = debounced
    ? entries.filter(([, name]) => name.toLowerCase().includes(debounced))
    : entries;

  const selectedSet = new Set(value);
  const selectedEntries = entries.filter(([id]) => selectedSet.has(Number(id)));
  const visibleChips = selectedEntries.slice(0, 3);
  const overflow = selectedEntries.length - visibleChips.length;

  const toggle = (id: number) => {
    const next = new Set(value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    onChange(Array.from(next));
  };

  const selectAll = () => onChange(entries.map(([id]) => Number(id)));
  const clearAll = () => onChange([]);

  const allChecked = selectedSet.size === entries.length && entries.length > 0;

  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          type="button"
          disabled={disabled}
          aria-label="Select roles"
          className={
            'mt-1 flex min-h-9 w-full items-center gap-2 rounded-lg bg-elevated px-3 py-1 text-left text-[13px] ring-1 ring-inset ring-hairline ' +
            'hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50 ' +
            'disabled:cursor-not-allowed disabled:opacity-60'
          }
        >
          <div className="flex min-w-0 flex-1 flex-wrap items-center gap-1">
            {selectedEntries.length === 0 ? (
              <span className="text-fg-subtle">Select roles…</span>
            ) : (
              <>
                {visibleChips.map(([id, name]) => (
                  <span
                    key={id}
                    className="inline-flex items-center gap-1 rounded-md bg-brand-blue-soft px-1.5 py-0.5 text-[11.5px] text-brand-blue"
                  >
                    <span className="truncate max-w-[140px]">{name}</span>
                  </span>
                ))}
                {overflow > 0 && (
                  <span className="inline-flex items-center rounded-md bg-surface px-1.5 py-0.5 text-[11.5px] text-fg-muted ring-1 ring-inset ring-hairline">
                    +{overflow} more
                  </span>
                )}
              </>
            )}
          </div>
          <ChevronsUpDown className="size-3.5 shrink-0 text-fg-subtle" aria-hidden />
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-[320px] p-0" align="start">
        <div className="border-b border-hairline p-2">
          <div className="relative">
            <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-fg-subtle" aria-hidden />
            <input
              type="search"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Filter roles…"
              aria-label="Filter roles"
              autoFocus
              className="h-8 w-full rounded-md bg-elevated pl-7 pr-2 text-[12.5px] ring-1 ring-inset ring-hairline focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
            />
          </div>
        </div>
        <ul className="max-h-60 overflow-y-auto p-1">
          {filtered.length === 0 ? (
            <li className="px-2 py-1.5 text-[12.5px] text-fg-muted">No matches.</li>
          ) : (
            filtered.map(([id, name]) => {
              const idNum = Number(id);
              const checked = selectedSet.has(idNum);
              return (
                <li key={id}>
                  <button
                    type="button"
                    onClick={() => toggle(idNum)}
                    className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-[12.5px] transition-colors hover:bg-surface"
                  >
                    <input
                      type="checkbox"
                      tabIndex={-1}
                      readOnly
                      checked={checked}
                      className="size-3.5 rounded border-hairline text-brand-blue pointer-events-none"
                    />
                    <span className="truncate">{name}</span>
                  </button>
                </li>
              );
            })
          )}
        </ul>
        <div className="flex items-center justify-between border-t border-hairline p-2 text-[11.5px]">
          <button
            type="button"
            onClick={allChecked ? clearAll : selectAll}
            className="font-medium text-fg-subtle hover:text-fg"
          >
            {allChecked ? 'Clear all' : 'Select all'}
          </button>
          {selectedEntries.length > 0 && !allChecked && (
            <button
              type="button"
              onClick={clearAll}
              className="inline-flex items-center gap-1 font-medium text-fg-subtle hover:text-fg"
            >
              <X className="size-3" /> Clear ({selectedEntries.length})
            </button>
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}
