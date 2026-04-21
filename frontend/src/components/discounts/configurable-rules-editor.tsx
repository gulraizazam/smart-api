import { useMemo, useState } from 'react';
import { ChevronsUpDown, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ServicePicker, type ServiceNode } from './service-picker';

/**
 * Pure controlled editor for a discount's Buy/Get rules. Lives on the
 * discount record (one set of rules shared across allocations) — moving
 * it here out of the create form so the allocation dialog can host it
 * alongside centre picking (same UX as Simple's "type + amount live on
 * allocation" pattern).
 *
 * The server payload shape (sessions / services_name / disc_type /
 * configurable_amount / same_service as indexed dicts) is constructed
 * by the caller; this component keeps a clean React-shaped state
 * { buy_mode, sessions_buy, base_service, get_rows[] }.
 */

export type ConfigurableRulesValue = {
  buy_mode: 'service' | 'category';
  sessions_buy: number;
  base_service: number | number[] | undefined;
  get_rows: Array<{
    sessions: number;
    same_service: boolean;
    services_name?: number;
    disc_type: string;
    configurable_amount?: number;
  }>;
};

/**
 * Catalog comes from `/api/discounts/services-for-configurable` which
 * returns a TREE already — parents with their `children` arrays eager
 * loaded (same shape the Simple allocation picker consumes). Nothing to
 * flatten client-side.
 */
type CatalogItem = ServiceNode;

const SESSION_OPTIONS = Array.from({ length: 10 }, (_, i) => i + 1);

export const EMPTY_RULES: ConfigurableRulesValue = {
  buy_mode: 'service',
  sessions_buy: 1,
  base_service: undefined,
  get_rows: [],
};

interface Props {
  value: ConfigurableRulesValue;
  onChange: (next: ConfigurableRulesValue) => void;
  catalog: CatalogItem[];
  disabled?: boolean;
}

export function ConfigurableRulesEditor({ value, onChange, catalog, disabled }: Props) {
  const update = (patch: Partial<ConfigurableRulesValue>) => onChange({ ...value, ...patch });

  // Catalog is already a tree; the parent-id set flags nodes that have
  // children (used to derive buy_mode: any parent selected => category).
  const parentIds = useMemo(() => {
    const s = new Set<number>();
    const walk = (n: ServiceNode) => {
      if (n.children && n.children.length > 0) s.add(n.id);
      n.children?.forEach(walk);
    };
    catalog.forEach(walk);
    return s;
  }, [catalog]);

  // Flat id → node lookup for GET rows' popover trigger to show the
  // selected service's name without walking the tree on every render.
  const flatCatalog = useMemo(() => {
    const m = new Map<number, ServiceNode>();
    const walk = (n: ServiceNode) => {
      m.set(n.id, n);
      n.children?.forEach(walk);
    };
    catalog.forEach(walk);
    return m;
  }, [catalog]);
  const selectedIds = useMemo(() => {
    const b = value.base_service;
    if (b == null) return new Set<number>();
    return new Set(Array.isArray(b) ? b : [b as number]);
  }, [value.base_service]);

  // Identical to Simple allocation's `toggleService`: a plain Set operation.
  // buy_mode is derived afterwards (category if any selected id has children,
  // else service) so the click handler stays single-purpose. Matches the
  // exact code shape the user asked for — the picker doesn't care about
  // leaves vs parents, it just toggles membership.
  const toggleService = (id: number) => {
    const next = new Set(selectedIds);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    const arr = Array.from(next);
    const hasAnyParent = arr.some((x) => parentIds.has(x));
    onChange({
      ...value,
      buy_mode: hasAnyParent ? 'category' : 'service',
      base_service:
        arr.length === 0 ? undefined : arr.length === 1 && !hasAnyParent ? arr[0] : arr,
    });
  };

  const updateRow = (idx: number, patch: Partial<ConfigurableRulesValue['get_rows'][number]>) => {
    onChange({
      ...value,
      get_rows: value.get_rows.map((r, i) => (i === idx ? { ...r, ...patch } : r)),
    });
  };

  const addRow = () =>
    onChange({
      ...value,
      get_rows: [
        ...value.get_rows,
        // Default to 5% rather than Complimentary — "100% free" is a
        // risky silent default if the operator forgets to change it.
        { sessions: 1, same_service: false, services_name: undefined, disc_type: '5', configurable_amount: 5 },
      ],
    });

  const removeRow = (idx: number) =>
    onChange({ ...value, get_rows: value.get_rows.filter((_, i) => i !== idx) });

  return (
    <div className="space-y-4 rounded-lg ring-1 ring-inset ring-hairline">
      <div className="border-b border-hairline px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">
        Buy
      </div>

      <div className="px-4 pb-3">
        {/* Sessions narrow left column, picker flex-1 right — sessions
            rarely changes so it doesn't need more than ~140px; the picker
            benefits from every extra pixel for readable service names. */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
          <div className="sm:w-[140px] sm:shrink-0">
            <Label htmlFor="cfg-sb">Sessions to buy</Label>
            <Select
              id="cfg-sb"
              disabled={disabled}
              value={String(value.sessions_buy)}
              onChange={(e) => update({ sessions_buy: Number(e.target.value) })}
            >
              {SESSION_OPTIONS.map((n) => (
                <option key={n} value={n}>{n}</option>
              ))}
            </Select>
          </div>

          <div className="min-w-0 flex-1">
            <Label>Service or category</Label>
            {/* Dropdown-styled multi-select — opens the same tree GET
                rows use, but stays open after each pick so multiple
                categories / services can be selected in one go. */}
            <ServicePickerPopover
              catalog={catalog}
              flatLookup={flatCatalog}
              selected={selectedIds}
              onToggle={toggleService}
              disabled={disabled}
              multiple
              placeholder="Choose service or category…"
            />
          </div>
        </div>
      </div>

      <div className="flex items-center justify-between gap-2 border-y border-hairline px-4 py-2.5">
        <div className="text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">
          Get <span className="text-fg-muted">· {value.get_rows.length}</span>
        </div>
        <Button type="button" variant="secondary" size="sm" onClick={addRow} disabled={disabled}>
          <Plus className="size-3.5" /> Add row
        </Button>
      </div>

      {value.get_rows.length === 0 && (
        <div className="px-4 pb-4 text-center text-[12.5px] text-fg-muted">
          Add at least one GET row.
        </div>
      )}

      <div className="divide-y divide-hairline/60">
        {value.get_rows.map((row, idx) => (
          <div key={idx} className="grid grid-cols-[70px_1fr_140px_auto] items-center gap-3 px-4 py-3">
            <div>
              <Label className="text-[11px]">Sessions</Label>
              <Select
                aria-label={`GET row ${idx + 1} sessions`}
                disabled={disabled}
                value={String(row.sessions)}
                onChange={(e) => updateRow(idx, { sessions: Number(e.target.value) })}
              >
                {SESSION_OPTIONS.map((n) => (
                  <option key={n} value={n}>{n}</option>
                ))}
              </Select>
            </div>
            <div>
              <Label className="text-[11px]">Service</Label>
              <ServicePickerPopover
                catalog={catalog}
                flatLookup={flatCatalog}
                selected={
                  row.services_name != null ? new Set([row.services_name]) : new Set<number>()
                }
                onToggle={(id) => {
                  // Single-select: toggling the current id clears it,
                  // anything else replaces.
                  const next = row.services_name === id ? undefined : id;
                  updateRow(idx, { services_name: next });
                }}
                disabled={disabled}
              />
            </div>
            <div>
              <Label className="text-[11px]">Discount</Label>
              <Select
                aria-label={`GET row ${idx + 1} disc type`}
                disabled={disabled}
                value={row.disc_type}
                onChange={(e) => updateRow(idx, { disc_type: e.target.value })}
              >
                <option value="complimentory">Complimentary</option>
                {/* 5%–50% in 5% steps. Keeps input finite so operators can
                    pick rather than type, and matches the legacy's common
                    discount tiers. */}
                {[5, 10, 15, 20, 25, 30, 35, 40, 45, 50].map((p) => (
                  <option key={p} value={p}>{p}%</option>
                ))}
              </Select>
            </div>
            <button
              type="button"
              aria-label={`Remove GET row ${idx + 1}`}
              onClick={() => removeRow(idx)}
              className="inline-flex size-7 items-center justify-center self-end rounded-md text-fg-subtle transition-colors hover:bg-surface hover:text-danger focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
            >
              <Trash2 className="size-3.5" />
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}

// ── Collapse helpers — map backend's row-per-session storage into the
// component's value shape (and back). Reused by both the form (if ever
// re-introduced) and the allocation dialog.

type RawBase = { service_id: number; sessions: number | string; is_category?: number | boolean };
type RawGet = {
  service_id: number;
  sessions: number | string;
  discount_type: string;
  discount_amount: number | string;
  same_service: number | boolean;
};

export function collapseRulesFromServer(
  bases: RawBase[],
  gets: RawGet[],
): ConfigurableRulesValue {
  if (!bases.length && !gets.length) return EMPTY_RULES;

  const firstBase = bases[0];
  const buyMode: 'service' | 'category' =
    firstBase && Number(firstBase.is_category) === 1 ? 'category' : 'service';
  const uniqueBaseIds = Array.from(new Set(bases.map((b) => b.service_id)));
  const sessionsBuy = firstBase ? Math.max(1, Number(firstBase.sessions) || 1) : 1;
  const baseServiceVal =
    buyMode === 'category' ? uniqueBaseIds : uniqueBaseIds[0];

  // GET rows compress N duplicate rows into { sessions: N } per
  // (service_id + disc_type + discount_amount + same_service) key.
  const grouped = new Map<
    string,
    {
      sessions: number;
      same_service: boolean;
      services_name?: number;
      disc_type: string;
      configurable_amount?: number;
    }
  >();
  for (const r of gets) {
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

  return {
    buy_mode: buyMode,
    sessions_buy: sessionsBuy,
    base_service: baseServiceVal,
    get_rows: Array.from(grouped.values()),
  };
}

/**
 * Serialise the rules to the legacy dynamic-field shape the backend
 * accepts. Pass `'edit'` for update flows — DiscountService reads
 * `edit_sessions_buy` / `edit_base_service` / `edit_sessions` etc when
 * $isEdit=true; sending unprefixed keys silently skips the update.
 */
export function rulesToLegacyPayload(
  value: ConfigurableRulesValue,
  mode: 'create' | 'edit' = 'create',
): Record<string, unknown> {
  const p = mode === 'edit' ? 'edit_' : '';
  const sessions: Record<number, number> = {};
  const services_name: Record<number, number> = {};
  const disc_type: Record<number, string> = {};
  const configurable_amount: Record<number, number> = {};
  const same_service: Record<number, string> = {};
  value.get_rows.forEach((r, i) => {
    sessions[i] = r.sessions;
    services_name[i] = r.services_name ?? 0;
    disc_type[i] = r.disc_type;
    // `discount_amount` drives the "N% Off" display string on the server
    // side. With the UI now offering only a finite preset list (5..50 in
    // 5% steps + Complimentary), derive the numeric amount from disc_type
    // so display stays consistent. Complimentary → 0 (server renders
    // "Complimentary" instead of a percent).
    const parsed = Number(r.disc_type);
    configurable_amount[i] = r.disc_type === 'complimentory' ? 0 : (Number.isFinite(parsed) ? parsed : 0);
    // "Same as BUY service" toggle removed from the UI; always send '0'.
    // Legacy rows with same_service=1 will round-trip as separate service
    // selections on edit since the picker shows the actual services_name.
    same_service[i] = '0';
  });
  return {
    [`${p}buy_mode`]: value.buy_mode,
    [`${p}sessions_buy`]: value.sessions_buy,
    [`${p}base_service`]: value.base_service,
    [`${p}sessions`]: sessions,
    [`${p}services_name`]: services_name,
    [`${p}disc_type`]: disc_type,
    [`${p}configurable_amount`]: configurable_amount,
    [`${p}same_service`]: same_service,
  };
}

/**
 * Popover-wrapped ServicePicker shared by BUY and GET. The trigger is a
 * short dropdown-style button so the row stays compact; opening the
 * popover shows the full searchable tree. `multiple` picks semantics:
 *   - false (default) → single-select, closes on pick (GET rows).
 *   - true            → multi-select, stays open until dismissed (BUY).
 * Trigger label summarises the selection ("Invasive" or "3 selected").
 */
function ServicePickerPopover({
  catalog,
  flatLookup,
  selected,
  onToggle,
  disabled,
  multiple,
  placeholder = 'Choose service…',
}: {
  catalog: ServiceNode[];
  flatLookup: Map<number, ServiceNode>;
  selected: Set<number>;
  onToggle: (id: number) => void;
  disabled?: boolean;
  multiple?: boolean;
  placeholder?: string;
}) {
  const [open, setOpen] = useState(false);

  const label = useMemo(() => {
    const arr = Array.from(selected);
    if (arr.length === 0) return placeholder;
    if (arr.length === 1) return flatLookup.get(arr[0])?.name ?? `#${arr[0]}`;
    return `${arr.length} selected`;
  }, [selected, flatLookup, placeholder]);

  const isEmpty = selected.size === 0;

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <button
          type="button"
          disabled={disabled}
          className={
            'flex h-9 w-full items-center gap-2 rounded-lg bg-elevated px-3 text-left text-[13px] ring-1 ring-inset ring-hairline ' +
            'hover:bg-surface focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50 ' +
            'disabled:cursor-not-allowed disabled:opacity-60'
          }
        >
          <span className={(isEmpty ? 'text-fg-subtle' : 'text-fg') + ' min-w-0 flex-1 truncate'}>
            {label}
          </span>
          <ChevronsUpDown className="size-3.5 shrink-0 text-fg-subtle" aria-hidden />
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-[340px] p-2" align="start">
        <ServicePicker
          nodes={catalog}
          selected={selected}
          onToggle={(id) => {
            onToggle(id);
            if (!multiple) setOpen(false);
          }}
          placeholder="Filter services…"
          emptyText="No services available."
        />
      </PopoverContent>
    </Popover>
  );
}

