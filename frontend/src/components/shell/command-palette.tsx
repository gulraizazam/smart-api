import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router';
import { CornerDownLeft, Search, Sparkles } from 'lucide-react';
import { Dialog, DialogContent, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { cn } from '@/lib/cn';
import { getTopPaths, recordVisit, subscribeFrecency } from '@/lib/page-frecency';
import { NAV, isSubsection } from './sidebar-data';

/**
 * Command palette — ⌘K-driven jump bar.
 *
 * Sprint 1 scope: navigates the static sidebar IA only (every link from
 * sidebar-data.ts becomes a result, with group/sub-section as the
 * breadcrumb hint). Sprint 2+ will fan out into resource search
 * (patients, leads, invoices) via a `/api/search` endpoint and merge
 * those results above navigation entries.
 */

type Result = {
  label: string;
  to: string;
  hint?: string;          // "Clinic" or "People · HR"
  implemented?: boolean;
};

/* Top N frecency-ranked pages shown under the "Frequent" heading on the
   empty-query state. Keep it short so the rest of the sidebar IA is
   still visible without scrolling — this is a shortcut, not the menu. */
const FREQUENT_LIMIT = 5;

const ALL_RESULTS: Result[] = (() => {
  const out: Result[] = [];
  for (const item of NAV) {
    if (item.type === 'link') {
      out.push({ label: item.label, to: item.to, implemented: item.implemented });
      continue;
    }
    const g = item.group;
    for (const sub of g.items) {
      if (isSubsection(sub)) {
        for (const leaf of sub.items) {
          out.push({
            label: leaf.label,
            to: leaf.to,
            hint: `${g.label} · ${sub.label}`,
            implemented: leaf.implemented,
          });
        }
      } else {
        out.push({
          label: sub.label,
          to: sub.to,
          hint: g.label,
          implemented: sub.implemented,
        });
      }
    }
  }
  return out;
})();

interface CommandPaletteProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function CommandPalette({ open, onOpenChange }: CommandPaletteProps) {
  const navigate = useNavigate();
  const [query, setQuery] = useState('');
  const [activeIndex, setActiveIndex] = useState(0);
  /* Tick bumps whenever frecency changes; used as a dep to recompute
     `frequent` without stashing the list in React state (the source of
     truth is localStorage). */
  const [frecencyTick, setFrecencyTick] = useState(0);
  const inputRef = useRef<HTMLInputElement>(null);
  const listRef = useRef<HTMLDivElement>(null);

  useEffect(() => subscribeFrecency(() => setFrecencyTick((t) => t + 1)), []);

  const trimmed = query.trim().toLowerCase();

  /* Empty-query state: top N frecency-ranked entries rendered above the
     full sidebar IA. When the user types, we fall back to the score-based
     search over every entry — intent beats habit. */
  const frequent = useMemo<Result[]>(() => {
    if (trimmed) return [];
    const byPath = new Map(ALL_RESULTS.map((r) => [r.to, r] as const));
    return getTopPaths(FREQUENT_LIMIT)
      .map((p) => byPath.get(p))
      .filter((r): r is Result => !!r);
  }, [trimmed, frecencyTick, open]);

  const frequentPaths = useMemo(() => new Set(frequent.map((r) => r.to)), [frequent]);

  const body = useMemo<Result[]>(() => {
    if (!trimmed) {
      return ALL_RESULTS.filter((r) => !frequentPaths.has(r.to)).slice(0, 12);
    }
    return ALL_RESULTS
      .map((r) => {
        const label = r.label.toLowerCase();
        const hint = r.hint?.toLowerCase() ?? '';
        // Score: exact prefix > word-start > substring > hint match
        let score = 0;
        if (label === trimmed) score = 1000;
        else if (label.startsWith(trimmed)) score = 500;
        else if (new RegExp(`\\b${escape(trimmed)}`).test(label)) score = 200;
        else if (label.includes(trimmed)) score = 100;
        else if (hint.includes(trimmed)) score = 30;
        return { r, score };
      })
      .filter(({ score }) => score > 0)
      .sort((a, b) => b.score - a.score)
      .slice(0, 30)
      .map(({ r }) => r);
  }, [trimmed, frequentPaths]);

  /* Flat index over [frequent, body] for keyboard nav. Each item knows
     which section it belongs to so the renderer can insert headers
     without breaking the counter. */
  const results = useMemo<Result[]>(() => [...frequent, ...body], [frequent, body]);

  // Reset query and selection when opening
  useEffect(() => {
    if (open) {
      setQuery('');
      setActiveIndex(0);
      // Radix focuses the first focusable element by default; force input
      requestAnimationFrame(() => inputRef.current?.focus());
    }
  }, [open]);

  // Clamp selection when results change
  useEffect(() => {
    setActiveIndex((i) => Math.min(i, Math.max(0, results.length - 1)));
  }, [results.length]);

  // Scroll active item into view
  useEffect(() => {
    const el = listRef.current?.querySelector<HTMLElement>(`[data-cmd-index="${activeIndex}"]`);
    el?.scrollIntoView({ block: 'nearest' });
  }, [activeIndex]);

  const choose = (r: Result | undefined) => {
    if (!r) return;
    onOpenChange(false);
    // Record the canonical destination (not the coming-soon redirect)
    // so frecency reflects the page the user *meant* to reach.
    if (r.implemented !== false) recordVisit(r.to);
    navigate(r.implemented === false ? `/coming-soon?from=${encodeURIComponent(r.to)}` : r.to);
  };

  const onKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setActiveIndex((i) => Math.min(i + 1, results.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setActiveIndex((i) => Math.max(i - 1, 0));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      choose(results[activeIndex]);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        hideClose
        className="top-[20%] max-w-xl translate-y-0 overflow-hidden p-0"
        onOpenAutoFocus={(e) => e.preventDefault()}
      >
        <DialogTitle className="sr-only">Command palette</DialogTitle>
        <DialogDescription className="sr-only">
          Type to search pages and jump to them with the keyboard.
        </DialogDescription>

        <div className="flex items-center gap-2 border-b border-hairline px-3 py-2.5">
          <Search className="size-4 shrink-0 text-fg-subtle" aria-hidden />
          <input
            ref={inputRef}
            type="text"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={onKeyDown}
            placeholder="Jump to a page…"
            className="min-w-0 flex-1 bg-transparent text-[14px] text-fg placeholder:text-fg-subtle focus:outline-none"
            aria-label="Search pages"
          />
          <kbd className="hidden shrink-0 rounded border border-hairline bg-surface px-1.5 py-px font-mono text-[10px] text-fg-subtle sm:inline-block">
            esc
          </kbd>
        </div>

        <div ref={listRef} className="max-h-[360px] overflow-y-auto p-1">
          {results.length === 0 ? (
            <div className="px-4 py-10 text-center text-[12.5px] text-fg-muted">
              No pages match “{query}”
            </div>
          ) : (
            <>
              {frequent.length > 0 && (
                <SectionHeader icon={Sparkles} label="Frequent" />
              )}
              {frequent.map((r, i) => (
                <ResultRow
                  key={`fr-${r.to}`}
                  r={r}
                  index={i}
                  active={activeIndex === i}
                  onChoose={choose}
                  onHover={setActiveIndex}
                />
              ))}
              {frequent.length > 0 && body.length > 0 && (
                <SectionHeader label="All pages" />
              )}
              {body.map((r, j) => {
                const i = frequent.length + j;
                return (
                  <ResultRow
                    key={`all-${r.to}-${i}`}
                    r={r}
                    index={i}
                    active={activeIndex === i}
                    onChoose={choose}
                    onHover={setActiveIndex}
                  />
                );
              })}
            </>
          )}
        </div>

        <div className="flex items-center justify-between border-t border-hairline bg-surface/60 px-3 py-2 text-[10.5px] text-fg-subtle">
          <div className="flex items-center gap-3">
            <span className="inline-flex items-center gap-1">
              <kbd className="rounded border border-hairline bg-elevated px-1 py-px font-mono text-[9.5px]">↑</kbd>
              <kbd className="rounded border border-hairline bg-elevated px-1 py-px font-mono text-[9.5px]">↓</kbd>
              navigate
            </span>
            <span className="inline-flex items-center gap-1">
              <kbd className="rounded border border-hairline bg-elevated px-1 py-px font-mono text-[9.5px]">↵</kbd>
              open
            </span>
          </div>
          <span className="hidden sm:inline">Sprint 2 will add patient/lead search.</span>
        </div>
      </DialogContent>
    </Dialog>
  );
}

function escape(s: string): string {
  return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function SectionHeader({
  label,
  icon: Icon,
}: {
  label: string;
  icon?: typeof Search;
}) {
  return (
    <div className="flex items-center gap-1.5 px-2.5 pb-1 pt-2 text-[10.5px] font-semibold uppercase tracking-wide text-fg-subtle">
      {Icon && <Icon className="size-3 text-accent-cyan-hover" aria-hidden />}
      {label}
    </div>
  );
}

function ResultRow({
  r,
  index,
  active,
  onChoose,
  onHover,
}: {
  r: Result;
  index: number;
  active: boolean;
  onChoose: (r: Result) => void;
  onHover: (i: number) => void;
}) {
  return (
    <button
      type="button"
      data-cmd-index={index}
      onClick={() => onChoose(r)}
      onMouseMove={() => onHover(index)}
      className={cn(
        'flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-start transition-colors',
        active ? 'bg-surface' : 'hover:bg-surface/60',
      )}
    >
      <span
        aria-hidden
        className={cn(
          'inline-flex size-6 shrink-0 items-center justify-center rounded-md text-[11px] font-medium',
          active ? 'bg-accent-cyan-soft text-accent-cyan-hover' : 'bg-surface text-fg-subtle',
        )}
      >
        {r.label.slice(0, 1).toUpperCase()}
      </span>
      <span className="min-w-0 flex-1 truncate text-[13px] font-medium text-fg">{r.label}</span>
      {r.hint && <span className="shrink-0 text-[11.5px] text-fg-subtle">{r.hint}</span>}
      {r.implemented === false && (
        <span className="shrink-0 rounded-full bg-accent-cyan-soft px-1.5 py-px text-[9px] font-semibold uppercase tracking-wide text-accent-cyan-hover">
          Soon
        </span>
      )}
      {active && <CornerDownLeft className="size-3 shrink-0 text-fg-subtle" aria-hidden />}
    </button>
  );
}
