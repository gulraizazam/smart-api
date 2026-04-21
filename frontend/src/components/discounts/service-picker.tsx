import { useEffect, useMemo, useState } from 'react';
import { ChevronDown, ChevronRight, Search, X } from 'lucide-react';

/**
 * ServicePicker — a searchable, collapsible tree picker for services.
 *
 * Shared between the Simple discount allocation dialog (multi-select of
 * centres' services) and the Configurable Buy/Get editor (pick the base
 * service or categories). Callers control selection semantics via
 * `onToggle`: the component itself is agnostic about whether toggling a
 * parent cascades, whether selection is single or multi, etc.
 *
 * Features:
 *   - Search filters by name; parents stay visible when any descendant matches
 *   - Collapsible categories (chevron); auto-expanded while searching
 *   - Row-sized click target (full pill background on hover)
 *   - Selected-chip strip above the tree
 */

export type ServiceNode = {
  id: number;
  name: string;
  parent_id?: number | null;
  children?: ServiceNode[];
};

interface Props {
  nodes: ServiceNode[];
  selected: Set<number>;
  onToggle: (id: number) => void;
  placeholder?: string;
  /** When set, overrides the default "No services available" empty state. */
  emptyText?: string;
}

export function ServicePicker({ nodes, selected, onToggle, placeholder = 'Filter services…', emptyText }: Props) {
  const [query, setQuery] = useState('');
  const [expanded, setExpanded] = useState<Set<number>>(new Set());
  const [debouncedQuery, setDebouncedQuery] = useState('');

  useEffect(() => {
    const t = setTimeout(() => setDebouncedQuery(query.trim().toLowerCase()), 150);
    return () => clearTimeout(t);
  }, [query]);

  // Flatten so the chip strip can look up names by id.
  const flatMap = useMemo(() => {
    const m = new Map<number, ServiceNode>();
    const walk = (n: ServiceNode) => {
      m.set(n.id, n);
      n.children?.forEach(walk);
    };
    nodes.forEach(walk);
    return m;
  }, [nodes]);

  const matches = (n: ServiceNode): boolean => {
    if (!debouncedQuery) return true;
    if (n.name.toLowerCase().includes(debouncedQuery)) return true;
    return (n.children ?? []).some(matches);
  };

  const toggleExpand = (id: number) => {
    setExpanded((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  if (nodes.length === 0) {
    return (
      <div className="rounded-lg bg-surface px-3 py-4 text-center text-[12.5px] text-fg-muted">
        {emptyText ?? 'No services available.'}
      </div>
    );
  }

  return (
    <div className="space-y-2">
      <div className="relative">
        <Search className="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-fg-subtle" aria-hidden />
        <input
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder={placeholder}
          aria-label={placeholder}
          className="h-8 w-full rounded-lg bg-elevated pl-8 pr-2 text-[12.5px] ring-1 ring-inset ring-hairline focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
        />
      </div>

      {selected.size > 0 && (
        <div className="flex flex-wrap gap-1">
          {Array.from(selected).map((id) => {
            const node = flatMap.get(id);
            return (
              <span
                key={id}
                className="inline-flex items-center gap-1 rounded-md bg-brand-blue-soft px-2 py-0.5 text-[11.5px] text-brand-blue"
              >
                <span className="truncate max-w-[160px]">{node?.name ?? `#${id}`}</span>
                <button
                  type="button"
                  aria-label={`Remove ${node?.name ?? id}`}
                  onClick={() => onToggle(id)}
                  className="text-brand-blue/70 hover:text-brand-blue"
                >
                  <X className="size-3" />
                </button>
              </span>
            );
          })}
        </div>
      )}

      <div className="max-h-[280px] overflow-y-auto rounded-lg bg-surface p-1.5 ring-1 ring-inset ring-hairline">
        <ServiceTreeLevel
          nodes={nodes}
          selected={selected}
          onToggle={onToggle}
          expanded={expanded}
          onToggleExpand={toggleExpand}
          matches={matches}
          searching={!!debouncedQuery}
          depth={0}
        />
      </div>
    </div>
  );
}

function ServiceTreeLevel({
  nodes,
  selected,
  onToggle,
  expanded,
  onToggleExpand,
  matches,
  searching,
  depth,
}: {
  nodes: ServiceNode[];
  selected: Set<number>;
  onToggle: (id: number) => void;
  expanded: Set<number>;
  onToggleExpand: (id: number) => void;
  matches: (n: ServiceNode) => boolean;
  searching: boolean;
  depth: number;
}) {
  const visible = nodes.filter(matches);
  if (visible.length === 0) {
    return depth === 0 ? (
      <div className="px-2 py-3 text-center text-[12px] text-fg-muted">No matches.</div>
    ) : null;
  }

  return (
    <ul className="space-y-0.5">
      {visible.map((n) => {
        const hasChildren = (n.children?.length ?? 0) > 0;
        const open = searching || expanded.has(n.id);
        const isSelected = selected.has(n.id);
        return (
          <li key={n.id}>
            <div
              className="flex items-center gap-1 rounded-md px-1 py-1 text-[12.5px] transition-colors hover:bg-elevated"
              style={{ paddingLeft: 4 + depth * 14 }}
            >
              {hasChildren ? (
                <button
                  type="button"
                  aria-label={open ? 'Collapse' : 'Expand'}
                  onClick={() => onToggleExpand(n.id)}
                  className="flex size-4 shrink-0 items-center justify-center text-fg-subtle hover:text-fg"
                >
                  {open ? <ChevronDown className="size-3" /> : <ChevronRight className="size-3" />}
                </button>
              ) : (
                <span className="size-4 shrink-0" aria-hidden />
              )}
              <label className="flex min-w-0 flex-1 cursor-pointer items-center gap-2">
                <input
                  type="checkbox"
                  className="size-3.5 shrink-0 rounded border-hairline text-brand-blue focus:ring-2 focus:ring-accent-cyan/50"
                  checked={isSelected}
                  onChange={() => onToggle(n.id)}
                />
                <span className={'truncate ' + (hasChildren ? 'font-medium text-fg' : 'text-fg-muted')}>
                  {n.name}
                </span>
              </label>
            </div>
            {hasChildren && open && (
              <ServiceTreeLevel
                nodes={n.children ?? []}
                selected={selected}
                onToggle={onToggle}
                expanded={expanded}
                onToggleExpand={onToggleExpand}
                matches={matches}
                searching={searching}
                depth={depth + 1}
              />
            )}
          </li>
        );
      })}
    </ul>
  );
}
