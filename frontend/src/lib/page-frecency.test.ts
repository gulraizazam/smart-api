import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  clearFrecency,
  getTopPaths,
  recordVisit,
  setFrecencyUser,
  subscribeFrecency,
} from './page-frecency';

const STORAGE_KEY_USER_42 = 'cutera.page-frecency.v1:42';
const DAY = 24 * 60 * 60 * 1000;

beforeEach(() => {
  localStorage.clear();
  setFrecencyUser(42);
  vi.useFakeTimers();
  vi.setSystemTime(new Date('2026-04-18T12:00:00Z'));
});

afterEach(() => {
  vi.useRealTimers();
  setFrecencyUser(null);
});

describe('recordVisit', () => {
  it('creates an entry with score 1 on first visit', () => {
    recordVisit('/patients');
    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY_USER_42) ?? '{}');
    expect(stored['/patients']).toMatchObject({ score: 1 });
    expect(stored['/patients'].lastBoosted).toBe(Date.now());
  });

  it('ignores empty paths and coming-soon redirects (no pollution)', () => {
    recordVisit('');
    recordVisit('/coming-soon?from=%2Ffoo');
    expect(localStorage.getItem(STORAGE_KEY_USER_42)).toBeNull();
  });

  it('accumulates score on repeated same-tick visits', () => {
    recordVisit('/patients');
    recordVisit('/patients');
    recordVisit('/patients');
    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY_USER_42) ?? '{}');
    // Same-tick: no decay between writes → 0+1, 1+1, 2+1
    expect(stored['/patients'].score).toBe(3);
  });

  it('decays previous score before adding 1 on a later visit', () => {
    recordVisit('/patients'); // score = 1
    vi.advanceTimersByTime(14 * DAY); // one half-life
    recordVisit('/patients');
    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY_USER_42) ?? '{}');
    // prev decayed to 0.5, + 1 = 1.5
    expect(stored['/patients'].score).toBeCloseTo(1.5, 5);
  });

  it('fires a frecency-updated event so open UIs can re-rank live', () => {
    const cb = vi.fn();
    const unsub = subscribeFrecency(cb);
    recordVisit('/patients');
    recordVisit('/leads');
    expect(cb).toHaveBeenCalledTimes(2);
    unsub();
    recordVisit('/services');
    expect(cb).toHaveBeenCalledTimes(2); // unsub worked
  });
});

describe('getTopPaths', () => {
  it('returns the empty list when storage is empty', () => {
    expect(getTopPaths(5)).toEqual([]);
  });

  it('ranks by effective score — frequency wins at equal recency', () => {
    recordVisit('/patients');
    recordVisit('/patients');
    recordVisit('/patients');
    recordVisit('/leads');
    expect(getTopPaths(5)).toEqual(['/patients', '/leads']);
  });

  it('ranks by effective score — recency beats stale high-count', () => {
    // Score 5 boosted 60 days ago → eff ≈ 5 * 0.5^(60/14) ≈ 5 * 0.0509 ≈ 0.255
    for (let i = 0; i < 5; i++) recordVisit('/old-favorite');
    vi.advanceTimersByTime(60 * DAY);
    // Score 1 boosted just now → eff = 1
    recordVisit('/fresh');
    const top = getTopPaths(5);
    expect(top[0]).toBe('/fresh');
    expect(top[1]).toBe('/old-favorite');
  });

  it('respects the limit parameter', () => {
    recordVisit('/a');
    recordVisit('/a');
    recordVisit('/b');
    recordVisit('/b');
    recordVisit('/c');
    recordVisit('/c');
    expect(getTopPaths(2)).toHaveLength(2);
  });

  it('culls long-decayed ghosts (effective score <= 0.05)', () => {
    recordVisit('/ghost'); // score = 1
    // 4 half-lives ≈ 1/16 ≈ 0.0625 (still passes 0.05 threshold)
    vi.advanceTimersByTime(4 * 14 * DAY);
    expect(getTopPaths(5)).toEqual(['/ghost']);
    // One more half-life: ~0.031 — now below threshold, should vanish from ranking
    vi.advanceTimersByTime(14 * DAY);
    expect(getTopPaths(5)).toEqual([]);
  });

  it('does not crash when localStorage holds garbage', () => {
    localStorage.setItem(STORAGE_KEY_USER_42, '{ not valid json');
    expect(() => getTopPaths(5)).not.toThrow();
    expect(getTopPaths(5)).toEqual([]);
  });

  it('filters malformed entries without crashing the whole ranking', () => {
    localStorage.setItem(
      STORAGE_KEY_USER_42,
      JSON.stringify({
        '/good': { score: 2, lastBoosted: Date.now() },
        '/bad-score': { score: 'not-a-number', lastBoosted: Date.now() },
        '/bad-time': { score: 1, lastBoosted: null },
        '/null': null,
        '/string': 'surprise',
      }),
    );
    expect(getTopPaths(10)).toEqual(['/good']);
  });
});

describe('per-user isolation', () => {
  it('no-ops every write when no user is bound (pre-login boot)', () => {
    setFrecencyUser(null);
    recordVisit('/patients');
    recordVisit('/leads');
    expect(localStorage.length).toBe(0);
    expect(getTopPaths(5)).toEqual([]);
  });

  it('keeps two users’ histories in separate localStorage keys', () => {
    // User 42 (set by beforeEach) visits /patients heavily.
    recordVisit('/patients');
    recordVisit('/patients');
    recordVisit('/patients');

    // Switch to user 99 — should see an empty ranking, not user 42's.
    setFrecencyUser(99);
    expect(getTopPaths(5)).toEqual([]);
    recordVisit('/leads');
    expect(getTopPaths(5)).toEqual(['/leads']);

    // Switch back to user 42 — their history is still intact.
    setFrecencyUser(42);
    expect(getTopPaths(5)).toEqual(['/patients']);

    // Both users' stores coexist under distinct keys.
    expect(localStorage.getItem('cutera.page-frecency.v1:42')).toBeTruthy();
    expect(localStorage.getItem('cutera.page-frecency.v1:99')).toBeTruthy();
  });

  it('clearFrecency wipes only the current user’s key, leaving others intact', () => {
    recordVisit('/patients'); // user 42
    setFrecencyUser(99);
    recordVisit('/leads'); // user 99

    clearFrecency(); // clears only user 99
    expect(localStorage.getItem('cutera.page-frecency.v1:99')).toBeNull();
    expect(localStorage.getItem('cutera.page-frecency.v1:42')).toBeTruthy();

    // Confirm from the 42 side.
    setFrecencyUser(42);
    expect(getTopPaths(5)).toEqual(['/patients']);
  });

  it('clearFrecency no-ops when no user is bound', () => {
    setFrecencyUser(null);
    localStorage.setItem('cutera.page-frecency.v1:42', '{"/x":{"score":1,"lastBoosted":0}}');
    clearFrecency();
    // The unrelated user 42 entry should still be there — null-bound clear
    // must not touch anyone's data.
    expect(localStorage.getItem('cutera.page-frecency.v1:42')).toBeTruthy();
  });
});

describe('store cap', () => {
  it('prunes to MAX_ENTRIES (100) when the cap is exceeded, keeping the highest effective scores', () => {
    // Seed 100 old, low-score entries.
    const seed: Record<string, { score: number; lastBoosted: number }> = {};
    const baseTime = Date.now();
    for (let i = 0; i < 100; i++) {
      seed[`/seed-${i}`] = { score: 1, lastBoosted: baseTime - 30 * DAY };
    }
    localStorage.setItem(STORAGE_KEY_USER_42, JSON.stringify(seed));

    // Add one fresh visit — should push us over, triggering prune.
    recordVisit('/fresh');

    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY_USER_42) ?? '{}');
    expect(Object.keys(stored)).toHaveLength(100);
    expect(stored['/fresh']).toBeDefined();
    // One of the decayed seeds should have been evicted — /fresh has eff=1,
    // all seeds have eff ≈ 0.23, so the last seed alphabetically (or
    // whichever sorted last) gets dropped. We just assert /fresh survived.
  });
});
