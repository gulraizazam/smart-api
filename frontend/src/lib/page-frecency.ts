/**
 * Page frecency — per-browser ranking of navigation destinations by a
 * time-decayed click count. Used by the command palette to surface the
 * pages this user actually lives in, without a server round-trip.
 *
 * Model (roughly Firefox's urlbar algorithm):
 *   • Each path has { score, lastBoosted } in localStorage.
 *   • On visit: score = score * decay(now - lastBoosted) + 1;
 *                lastBoosted = now.
 *   • On read: effectiveScore = score * decay(now - lastBoosted).
 *   • decay(ms) = 0.5 ^ (ms / HALF_LIFE_MS) — a 14-day half-life means a
 *     page you clicked heavily two weeks ago but never since gets halved,
 *     four weeks ago quartered, etc. Stops being top-of-mind without
 *     being erased.
 *
 * Sync: none. This is per-browser by design — see the discussion in the
 * command-palette PR; server-side syncing is a later upgrade if we ever
 * need it. Reads/writes are best-effort: any localStorage error just
 * returns an empty store so we never crash navigation.
 */

const STORAGE_PREFIX = 'cutera.page-frecency.v1';
const HALF_LIFE_MS = 14 * 24 * 60 * 60 * 1000; // 14 days
const MAX_ENTRIES = 100; // cap so the store can't grow unbounded

type Entry = { score: number; lastBoosted: number };
type Store = Record<string, Entry>;

/* Event fired after recordVisit so open UIs can re-rank live. */
const UPDATED_EVENT = 'cutera:frecency-updated';

/* Current user's namespaced storage key. Bound by setFrecencyUser() on
   login/boot and cleared on logout. While null, all reads/writes no-op
   — the palette just falls back to the default sidebar list. Namespacing
   means two users on one browser can't see each other's history even
   if a logout is skipped (tab closed, account switched mid-session). */
let currentKey: string | null = null;

export function setFrecencyUser(userId: number | string | null): void {
  currentKey = userId == null ? null : `${STORAGE_PREFIX}:${userId}`;
  try {
    window.dispatchEvent(new Event(UPDATED_EVENT));
  } catch {
    /* non-browser env. */
  }
}

function decay(ageMs: number): number {
  if (ageMs <= 0) return 1;
  return Math.pow(0.5, ageMs / HALF_LIFE_MS);
}

function load(): Store {
  if (!currentKey) return {};
  try {
    const raw = localStorage.getItem(currentKey);
    if (!raw) return {};
    const parsed = JSON.parse(raw);
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return {};
    // Per-entry shape check — a hand-corrupted store could otherwise
    // propagate NaN through the score math and break sorting.
    const clean: Store = {};
    for (const [k, v] of Object.entries(parsed as Record<string, unknown>)) {
      if (
        v &&
        typeof v === 'object' &&
        typeof (v as Entry).score === 'number' &&
        Number.isFinite((v as Entry).score) &&
        typeof (v as Entry).lastBoosted === 'number' &&
        Number.isFinite((v as Entry).lastBoosted)
      ) {
        clean[k] = { score: (v as Entry).score, lastBoosted: (v as Entry).lastBoosted };
      }
    }
    return clean;
  } catch {
    return {};
  }
}

function save(store: Store): void {
  if (!currentKey) return;
  try {
    localStorage.setItem(currentKey, JSON.stringify(store));
  } catch {
    /* quota / private mode — drop silently. */
  }
}

export function recordVisit(path: string): void {
  if (!currentKey) return;
  if (!path || path.startsWith('/coming-soon')) return;
  const now = Date.now();
  const store = load();
  const prev = store[path];
  const prevScore = prev ? prev.score * decay(now - prev.lastBoosted) : 0;
  store[path] = { score: prevScore + 1, lastBoosted: now };

  // Trim if we're over the cap — drop the lowest effective scores.
  const keys = Object.keys(store);
  if (keys.length > MAX_ENTRIES) {
    const ranked = keys
      .map((k) => ({ k, eff: store[k].score * decay(now - store[k].lastBoosted) }))
      .sort((a, b) => b.eff - a.eff);
    const pruned: Store = {};
    for (const { k } of ranked.slice(0, MAX_ENTRIES)) pruned[k] = store[k];
    save(pruned);
  } else {
    save(store);
  }

  try {
    window.dispatchEvent(new Event(UPDATED_EVENT));
  } catch {
    /* non-browser env (tests) — no subscribers to notify. */
  }
}

export function getTopPaths(limit: number): string[] {
  const now = Date.now();
  const store = load();
  return Object.entries(store)
    .map(([path, e]) => ({ path, eff: e.score * decay(now - e.lastBoosted) }))
    .filter(({ eff }) => eff > 0.05) // cull long-decayed ghosts
    .sort((a, b) => b.eff - a.eff)
    .slice(0, limit)
    .map(({ path }) => path);
}

export function subscribeFrecency(cb: () => void): () => void {
  const handler = () => cb();
  window.addEventListener(UPDATED_EVENT, handler);
  return () => window.removeEventListener(UPDATED_EVENT, handler);
}

/* Wipe the current user's store — called from logout as a belt-and-braces
   on top of per-user key namespacing. Namespacing prevents cross-user
   leaks even if this is skipped (tab closed before logout fired), but
   clearing on intentional logout keeps the store small on shared devices. */
export function clearFrecency(): void {
  if (!currentKey) return;
  try {
    localStorage.removeItem(currentKey);
  } catch {
    /* private mode — nothing to clear. */
  }
  try {
    window.dispatchEvent(new Event(UPDATED_EVENT));
  } catch {
    /* non-browser env. */
  }
}
