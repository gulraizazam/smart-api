import { useEffect, useMemo, useRef, useState } from 'react';
import { Check, ChevronDown, Search, X } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/cn';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';

/* Shared filter primitives — extracted from the Leads filter shelf so every
   module (patients, services, lead-statuses, lead-sources, …) renders the
   same pill/popover shape. The Leads shelf is the design reference; do not
   fork these locally — extend this module. */

export type LookupOption = { id: number | string; name: string };

/* ───────── Filter pill ───────── */

export function FilterPill({
  icon: Icon,
  label,
  value,
  onClear,
  popover,
  removable,
  onRemove,
  popoverWidth,
}: {
  icon: LucideIcon;
  label: string;
  value: string | undefined;
  onClear: () => void;
  popover: React.ReactNode;
  removable?: boolean;
  onRemove?: () => void;
  popoverWidth?: string;
}) {
  const [open, setOpen] = useState(false);
  const active = !!value;
  return (
    <Popover open={open} onOpenChange={setOpen}>
      <div
        className={cn(
          'group inline-flex h-8 items-center overflow-hidden rounded-lg ring-1 ring-inset transition-colors',
          active
            ? 'bg-accent-cyan/10 ring-accent-cyan/30 text-fg'
            : 'bg-elevated ring-hairline text-fg-muted hover:text-fg',
        )}
      >
        <PopoverTrigger asChild>
          <button
            type="button"
            className="flex items-center gap-1.5 px-2.5 text-[12.5px] font-medium focus-visible:outline-none focus-visible:bg-surface"
          >
            <Icon className={cn('size-3.5', active ? 'text-accent-cyan-hover' : 'text-fg-subtle')} />
            <span>{label}</span>
            {active ? (
              <>
                <span className="text-fg-subtle">·</span>
                <span className="max-w-[140px] truncate text-fg">{value}</span>
              </>
            ) : (
              <ChevronDown className="size-3 text-fg-subtle" />
            )}
          </button>
        </PopoverTrigger>
        {active && (
          <button
            type="button"
            aria-label={`Clear ${label}`}
            onClick={(e) => {
              e.stopPropagation();
              onClear();
            }}
            className="flex h-full items-center border-l border-accent-cyan/20 px-1.5 text-fg-subtle transition-colors hover:bg-accent-cyan/10 hover:text-fg"
          >
            <X className="size-3" />
          </button>
        )}
        {!active && removable && (
          <button
            type="button"
            aria-label={`Remove ${label} filter`}
            onClick={(e) => {
              e.stopPropagation();
              onRemove?.();
            }}
            className="flex h-full items-center border-l border-hairline px-1.5 text-fg-subtle transition-colors hover:bg-surface hover:text-fg"
          >
            <X className="size-3" />
          </button>
        )}
      </div>
      <PopoverContent className={popoverWidth ?? 'w-[260px]'}>{popover}</PopoverContent>
    </Popover>
  );
}

/* ───────── Searchable combo picker ───────── */

export function ComboPicker({
  title,
  options,
  value,
  onChange,
}: {
  title: string;
  options: LookupOption[];
  value: string;
  onChange: (v: string) => void;
}) {
  const [query, setQuery] = useState('');
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return options;
    return options.filter((o) => o.name.toLowerCase().includes(q));
  }, [options, query]);

  return (
    <div className="flex flex-col">
      <div className="relative border-b border-hairline">
        <Search className="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-fg-subtle" aria-hidden />
        <input
          ref={inputRef}
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={`Search ${title.toLowerCase()}…`}
          className="h-9 w-full bg-transparent pl-8 pr-2 text-[13px] text-fg placeholder:text-fg-subtle focus:outline-none"
        />
      </div>
      <ul role="listbox" className="max-h-[240px] overflow-y-auto py-1">
        {filtered.length === 0 ? (
          <li className="px-3 py-6 text-center text-[12px] text-fg-subtle">
            No matches
          </li>
        ) : (
          filtered.map((o) => {
            const selected = String(o.id) === value;
            return (
              <li key={o.id}>
                <button
                  type="button"
                  role="option"
                  aria-selected={selected}
                  onClick={() => onChange(String(o.id))}
                  className={cn(
                    'flex w-full items-center justify-between gap-2 rounded-md px-2.5 py-1.5 text-left text-[13px] transition-colors',
                    'hover:bg-surface focus-visible:outline-none focus-visible:bg-surface',
                    selected ? 'text-fg font-medium' : 'text-fg-muted',
                  )}
                >
                  <span className="truncate">{o.name}</span>
                  {selected && <Check className="size-3.5 shrink-0 text-accent-cyan-hover" />}
                </button>
              </li>
            );
          })
        )}
      </ul>
    </div>
  );
}

/* ───────── Radio picker (small sets) ───────── */

export function RadioPicker({
  title,
  options,
  value,
  onChange,
}: {
  title: string;
  options: LookupOption[];
  value: string;
  onChange: (v: string) => void;
}) {
  return (
    <div className="flex flex-col">
      <div className="border-b border-hairline px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-fg-subtle">
        {title}
      </div>
      <ul role="listbox" className="py-1">
        {options.map((o) => {
          const selected = String(o.id) === value;
          return (
            <li key={o.id}>
              <button
                type="button"
                role="option"
                aria-selected={selected}
                onClick={() => onChange(String(o.id))}
                className={cn(
                  'flex w-full items-center justify-between gap-2 rounded-md px-2.5 py-1.5 text-left text-[13px] transition-colors',
                  'hover:bg-surface focus-visible:outline-none focus-visible:bg-surface',
                  selected ? 'text-fg font-medium' : 'text-fg-muted',
                )}
              >
                <span>{o.name}</span>
                {selected && <Check className="size-3.5 text-accent-cyan-hover" />}
              </button>
            </li>
          );
        })}
      </ul>
    </div>
  );
}
