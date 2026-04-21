import { useEffect, useMemo, useState } from 'react';
import {
  Building2,
  Calendar,
  MapPin,
  Map,
  Plus,
  Sparkles,
  Tag,
  UserCircle2,
  Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/cn';
import {
  PopoverClose,
} from '@/components/ui/popover';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  ComboPicker,
  FilterPill,
  RadioPicker,
  type LookupOption,
} from '@/components/filters/filter-pill';

export type { LookupOption };

/* Modern filter shelf for the Leads page.
   Pattern: always-visible primary pills (Centre, Service) + a "+ Add filter"
   menu that surfaces secondary filters on demand (Date, City, Region, Gender,
   Created by). Each pill is a popover trigger that shows the current value
   inline and opens a searchable picker / date-pair / radio list depending
   on the filter's shape. Matches Linear / Vercel / Attio conventions.

   Pill/picker primitives live in '@/components/filters/filter-pill' so other
   modules (patients, services, lead-statuses, …) render the same shape. */

export type LeadFilters = {
  centreId: string;
  serviceId: string;
  dateFrom: string;
  dateTo: string;
  cityId: string;
  regionId: string;
  gender: string;
  createdBy: string;
};

export const EMPTY_LEAD_FILTERS: LeadFilters = {
  centreId: '',
  serviceId: '',
  dateFrom: '',
  dateTo: '',
  cityId: '',
  regionId: '',
  gender: '',
  createdBy: '',
};

export type LeadFilterLookups = {
  centres: LookupOption[];
  services: LookupOption[];
  cities: LookupOption[];
  regions: LookupOption[];
  users: LookupOption[];
};

type Props = {
  filters: LeadFilters;
  onChange: (next: LeadFilters) => void;
  lookups: LeadFilterLookups;
  /* Status is a top-level filter (not one of the generic secondary ones)
     because it's owned by page-level URL state and has a short curated list
     of options. We render it as the first primary pill for layout parity
     with Centre/Service. Pass empty statusOptions to hide the pill. */
  statusId: string;
  onStatusChange: (id: string) => void;
  statusOptions: LookupOption[];
};

const GENDER_OPTIONS: LookupOption[] = [
  { id: '1', name: 'Male' },
  { id: '2', name: 'Female' },
];

type SecondaryKey = 'serviceId' | 'cityId' | 'regionId' | 'gender' | 'createdBy';

const SECONDARY_META: Record<
  SecondaryKey,
  { label: string; icon: LucideIcon }
> = {
  serviceId: { label: 'Category', icon: Sparkles },
  cityId: { label: 'City', icon: MapPin },
  regionId: { label: 'Region', icon: Map },
  gender: { label: 'Gender', icon: Users },
  createdBy: { label: 'Agent', icon: UserCircle2 },
};

function isSecondaryActive(key: SecondaryKey, f: LeadFilters): boolean {
  switch (key) {
    case 'serviceId':
      return !!f.serviceId;
    case 'cityId':
      return !!f.cityId;
    case 'regionId':
      return !!f.regionId;
    case 'gender':
      return !!f.gender;
    case 'createdBy':
      return !!f.createdBy;
  }
}

export function LeadFilterShelf({
  filters,
  onChange,
  lookups,
  statusId,
  onStatusChange,
  statusOptions,
}: Props) {
  // Secondary filters visible because the user opted-in via "+ Add filter",
  // or because they're already populated from URL/state. Keep in a Set so
  // order is stable (insertion order = left-to-right visual order).
  const [revealed, setRevealed] = useState<Set<SecondaryKey>>(() => {
    const s = new Set<SecondaryKey>();
    (Object.keys(SECONDARY_META) as SecondaryKey[]).forEach((k) => {
      if (isSecondaryActive(k, filters)) s.add(k);
    });
    return s;
  });

  // When a filter becomes active externally (e.g. URL load), reveal it.
  useEffect(() => {
    setRevealed((prev) => {
      let changed = false;
      const next = new Set(prev);
      (Object.keys(SECONDARY_META) as SecondaryKey[]).forEach((k) => {
        if (isSecondaryActive(k, filters) && !next.has(k)) {
          next.add(k);
          changed = true;
        }
      });
      return changed ? next : prev;
    });
  }, [filters]);

  const set = <K extends keyof LeadFilters>(key: K, value: LeadFilters[K]) =>
    onChange({ ...filters, [key]: value });

  const hideSecondary = (key: SecondaryKey) => {
    // Clearing the values is part of hiding so the table state and the UI
    // stay in sync (no "hidden but still filtering" ghost state).
    const next = { ...filters };
    if (key === 'serviceId') next.serviceId = '';
    else if (key === 'cityId') next.cityId = '';
    else if (key === 'regionId') next.regionId = '';
    else if (key === 'gender') next.gender = '';
    else if (key === 'createdBy') next.createdBy = '';
    onChange(next);
    setRevealed((prev) => {
      const n = new Set(prev);
      n.delete(key);
      return n;
    });
  };

  const addSecondary = (key: SecondaryKey) => {
    setRevealed((prev) => new Set(prev).add(key));
  };

  const availableToAdd = (Object.keys(SECONDARY_META) as SecondaryKey[])
    .filter((k) => !revealed.has(k));

  const centreLabel = useMemo(
    () => lookups.centres.find((o) => String(o.id) === filters.centreId)?.name,
    [lookups.centres, filters.centreId],
  );
  const serviceLabel = useMemo(
    () => lookups.services.find((o) => String(o.id) === filters.serviceId)?.name,
    [lookups.services, filters.serviceId],
  );
  const cityLabel = useMemo(
    () => lookups.cities.find((o) => String(o.id) === filters.cityId)?.name,
    [lookups.cities, filters.cityId],
  );
  const regionLabel = useMemo(
    () => lookups.regions.find((o) => String(o.id) === filters.regionId)?.name,
    [lookups.regions, filters.regionId],
  );
  const userLabel = useMemo(
    () => lookups.users.find((o) => String(o.id) === filters.createdBy)?.name,
    [lookups.users, filters.createdBy],
  );
  const genderLabel = useMemo(
    () => GENDER_OPTIONS.find((o) => o.id === filters.gender)?.name,
    [filters.gender],
  );

  const statusLabel = useMemo(
    () => statusOptions.find((o) => String(o.id) === statusId)?.name,
    [statusOptions, statusId],
  );

  const dateLabel = useMemo(() => {
    if (!filters.dateFrom && !filters.dateTo) return undefined;
    // Format: "Apr 18, 26" (short month · day · 2-digit year). en-US
    // locale so the shape stays consistent across browsers. Within a
    // same-year range we drop the year from the first date to keep
    // the pill short.
    const fmt = (s: string, withYear = true) => {
      if (!s) return '…';
      return new Date(s).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        ...(withYear ? { year: '2-digit' } : {}),
      });
    };
    if (filters.dateFrom && filters.dateTo) {
      const fy = new Date(filters.dateFrom).getFullYear();
      const ty = new Date(filters.dateTo).getFullYear();
      const sameYear = fy === ty;
      return `${fmt(filters.dateFrom, !sameYear)} → ${fmt(filters.dateTo)}`;
    }
    if (filters.dateFrom) return `From ${fmt(filters.dateFrom)}`;
    return `Until ${fmt(filters.dateTo)}`;
  }, [filters.dateFrom, filters.dateTo]);

  return (
    <>
      {statusOptions.length > 0 && (
        <FilterPill
          icon={Tag}
          label="Status"
          value={statusLabel}
          onClear={() => onStatusChange('')}
          popover={
            <RadioPicker
              title="Status"
              options={statusOptions}
              value={statusId}
              onChange={onStatusChange}
            />
          }
        />
      )}
      <FilterPill
        icon={Building2}
        label="Centre"
        value={centreLabel}
        onClear={() => set('centreId', '')}
        popover={
          <ComboPicker
            title="Centre"
            options={lookups.centres}
            value={filters.centreId}
            onChange={(v) => set('centreId', v)}
          />
        }
      />
      <FilterPill
        icon={Calendar}
        label="Received"
        value={dateLabel}
        onClear={() => onChange({ ...filters, dateFrom: '', dateTo: '' })}
        popoverWidth="w-[300px]"
        popover={
          <DateRangePicker
            from={filters.dateFrom}
            to={filters.dateTo}
            onChange={(from, to) => onChange({ ...filters, dateFrom: from, dateTo: to })}
          />
        }
      />

      {Array.from(revealed).map((key) => {
        const meta = SECONDARY_META[key];
        if (key === 'serviceId') {
          return (
            <FilterPill
              key={key}
              icon={meta.icon}
              label={meta.label}
              value={serviceLabel}
              removable
              onRemove={() => hideSecondary('serviceId')}
              onClear={() => set('serviceId', '')}
              popover={
                <ComboPicker
                  title="Category"
                  options={lookups.services}
                  value={filters.serviceId}
                  onChange={(v) => set('serviceId', v)}
                />
              }
            />
          );
        }
        if (key === 'gender') {
          return (
            <FilterPill
              key={key}
              icon={meta.icon}
              label={meta.label}
              value={genderLabel}
              removable
              onRemove={() => hideSecondary('gender')}
              onClear={() => set('gender', '')}
              popover={
                <RadioPicker
                  title="Gender"
                  options={GENDER_OPTIONS}
                  value={filters.gender}
                  onChange={(v) => set('gender', v)}
                />
              }
            />
          );
        }
        if (key === 'cityId') {
          return (
            <FilterPill
              key={key}
              icon={meta.icon}
              label={meta.label}
              value={cityLabel}
              removable
              onRemove={() => hideSecondary('cityId')}
              onClear={() => set('cityId', '')}
              popover={
                <ComboPicker
                  title="City"
                  options={lookups.cities}
                  value={filters.cityId}
                  onChange={(v) => set('cityId', v)}
                />
              }
            />
          );
        }
        if (key === 'regionId') {
          return (
            <FilterPill
              key={key}
              icon={meta.icon}
              label={meta.label}
              value={regionLabel}
              removable
              onRemove={() => hideSecondary('regionId')}
              onClear={() => set('regionId', '')}
              popover={
                <ComboPicker
                  title="Region"
                  options={lookups.regions}
                  value={filters.regionId}
                  onChange={(v) => set('regionId', v)}
                />
              }
            />
          );
        }
        // createdBy
        return (
          <FilterPill
            key={key}
            icon={meta.icon}
            label={meta.label}
            value={userLabel}
            removable
            onRemove={() => hideSecondary('createdBy')}
            onClear={() => set('createdBy', '')}
            popover={
              <ComboPicker
                title="Agent"
                options={lookups.users}
                value={filters.createdBy}
                onChange={(v) => set('createdBy', v)}
              />
            }
          />
        );
      })}

      {availableToAdd.length > 0 && (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button
              type="button"
              className="inline-flex h-8 items-center gap-1 rounded-lg border border-dashed border-hairline px-2.5 text-[12.5px] font-medium text-fg-subtle transition-colors hover:border-fg-muted hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
            >
              <Plus className="size-3.5" /> Add filter
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="start" className="min-w-[180px]">
            {availableToAdd.map((k) => {
              const { label, icon: Icon } = SECONDARY_META[k];
              return (
                <DropdownMenuItem key={k} onSelect={() => addSecondary(k)}>
                  <Icon className="size-3.5 text-fg-subtle" /> {label}
                </DropdownMenuItem>
              );
            })}
          </DropdownMenuContent>
        </DropdownMenu>
      )}
    </>
  );
}

/* ───────── Date range (presets + from/to) ───────── */

/* Local-tz YYYY-MM-DD. `toISOString()` is UTC and would shift the day for
   users east/west of GMT around midnight — avoid. */
function toLocalISO(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function addDays(d: Date, n: number): Date {
  const c = new Date(d);
  c.setDate(c.getDate() + n);
  return c;
}

/* Preset builder — returns { from, to } pairs relative to "today" in the
   user's local time zone. "Last N days" is inclusive of today per product
   convention (today-(N-1) → today, giving N distinct days). Every preset
   yields ≤ 31 days to stay within the export cap policy — don't add
   wider presets (e.g. "This year") without also relaxing that policy. */
type PresetKey = 'today' | 'yesterday' | 'last7' | 'last30' | 'thisMonth' | 'lastMonth';

function presetRange(key: PresetKey): { from: string; to: string } {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  switch (key) {
    case 'today':
      return { from: toLocalISO(today), to: toLocalISO(today) };
    case 'yesterday': {
      const y = addDays(today, -1);
      return { from: toLocalISO(y), to: toLocalISO(y) };
    }
    case 'last7':
      return { from: toLocalISO(addDays(today, -6)), to: toLocalISO(today) };
    case 'last30':
      return { from: toLocalISO(addDays(today, -29)), to: toLocalISO(today) };
    case 'thisMonth': {
      const start = new Date(today.getFullYear(), today.getMonth(), 1);
      return { from: toLocalISO(start), to: toLocalISO(today) };
    }
    case 'lastMonth': {
      const start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
      const end = new Date(today.getFullYear(), today.getMonth(), 0);
      return { from: toLocalISO(start), to: toLocalISO(end) };
    }
  }
}

const PRESETS: { key: PresetKey; label: string }[] = [
  { key: 'today', label: 'Today' },
  { key: 'yesterday', label: 'Yesterday' },
  { key: 'last7', label: 'Last 7 days' },
  { key: 'last30', label: 'Last 30 days' },
  { key: 'thisMonth', label: 'This month' },
  { key: 'lastMonth', label: 'Last month' },
];

function DateRangePicker({
  from,
  to,
  onChange,
}: {
  from: string;
  to: string;
  onChange: (from: string, to: string) => void;
}) {
  const [localFrom, setLocalFrom] = useState(from);
  const [localTo, setLocalTo] = useState(to);

  useEffect(() => {
    setLocalFrom(from);
    setLocalTo(to);
  }, [from, to]);

  const activePreset = useMemo<PresetKey | null>(() => {
    if (!from || !to) return null;
    return (
      PRESETS.find((p) => {
        const r = presetRange(p.key);
        return r.from === from && r.to === to;
      })?.key ?? null
    );
  }, [from, to]);

  const apply = () => onChange(localFrom, localTo);
  const clear = () => {
    setLocalFrom('');
    setLocalTo('');
    onChange('', '');
  };

  const invalid = !!(localFrom && localTo && localFrom > localTo);
  const incomplete = !localFrom || !localTo;

  return (
    <div className="flex flex-col">
      <div className="border-b border-hairline p-2">
        <div className="mb-1.5 px-1 text-[10.5px] font-semibold uppercase tracking-wide text-fg-subtle">
          Quick ranges
        </div>
        <div className="grid grid-cols-2 gap-1">
          {PRESETS.map((p) => {
            const active = activePreset === p.key;
            return (
              <PopoverClose asChild key={p.key}>
                <button
                  type="button"
                  onClick={() => {
                    const r = presetRange(p.key);
                    onChange(r.from, r.to);
                  }}
                  className={cn(
                    'rounded-md px-2 py-1.5 text-left text-[12.5px] transition-colors',
                    'focus-visible:outline-none focus-visible:bg-surface',
                    active
                      ? 'bg-accent-cyan/10 text-fg font-medium ring-1 ring-inset ring-accent-cyan/30'
                      : 'text-fg-muted hover:bg-surface hover:text-fg',
                  )}
                >
                  {p.label}
                </button>
              </PopoverClose>
            );
          })}
        </div>
      </div>
      <div className="flex flex-col gap-2 p-3">
        <div className="text-[10.5px] font-semibold uppercase tracking-wide text-fg-subtle">
          Custom range
        </div>
        <label className="flex flex-col gap-1">
          <span className="text-[11.5px] text-fg-subtle">From</span>
          <input
            type="date"
            value={localFrom}
            max={localTo || undefined}
            onChange={(e) => setLocalFrom(e.target.value)}
            className="h-9 rounded-lg bg-surface px-2.5 text-[13px] text-fg ring-1 ring-inset ring-hairline focus:outline-none focus-visible:ring-accent-cyan/50"
          />
        </label>
        <label className="flex flex-col gap-1">
          <span className="text-[11.5px] text-fg-subtle">To</span>
          <input
            type="date"
            value={localTo}
            min={localFrom || undefined}
            onChange={(e) => setLocalTo(e.target.value)}
            className="h-9 rounded-lg bg-surface px-2.5 text-[13px] text-fg ring-1 ring-inset ring-hairline focus:outline-none focus-visible:ring-accent-cyan/50"
          />
        </label>
        {invalid ? (
          <p role="alert" className="text-[11.5px] text-danger">
            "From" must be on or before "To".
          </p>
        ) : incomplete ? (
          <p className="text-[11.5px] text-fg-subtle">Pick both dates to apply.</p>
        ) : null}
        <div className="flex items-center justify-between gap-2 pt-1">
          <button
            type="button"
            onClick={clear}
            className="text-[12px] font-medium text-fg-subtle transition-colors hover:text-fg"
          >
            Clear
          </button>
          <PopoverClose asChild>
            <button
              type="button"
              onClick={apply}
              disabled={invalid || incomplete}
              className="rounded-md bg-accent-cyan px-3 py-1 text-[12.5px] font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-40"
            >
              Apply
            </button>
          </PopoverClose>
        </div>
      </div>
    </div>
  );
}
