import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
  formatNumber,
  formatCompact,
  formatCurrency,
  formatPercent,
  formatRelativeTime,
  formatTableDate,
  formatPhone,
  initials,
} from './format';

describe('formatNumber', () => {
  it('formats integers with thousands separators', () => {
    expect(formatNumber(478828)).toBe('478,828');
    expect(formatNumber(0)).toBe('0');
    expect(formatNumber(1234567)).toBe('1,234,567');
  });
  it('returns dash for null/undefined/NaN', () => {
    expect(formatNumber(null)).toBe('—');
    expect(formatNumber(undefined)).toBe('—');
    expect(formatNumber(Number.NaN)).toBe('—');
  });
});

describe('formatCompact', () => {
  it('shortens large numbers', () => {
    expect(formatCompact(1500)).toBe('1.5K');
    expect(formatCompact(2_000_000)).toBe('2M');
  });
  it('returns dash for empty values', () => {
    expect(formatCompact(null)).toBe('—');
  });
});

describe('formatCurrency', () => {
  it('formats Pakistani rupees with the Rs symbol from en-PK locale', () => {
    // Intl with en-PK uses "Rs" rather than "PKR" — verifying the actual
    // user-visible rendering, not the ISO code.
    const out = formatCurrency(145000);
    expect(out).toMatch(/Rs/);
    expect(out).toMatch(/145,000/);
  });
  it('returns dash for null', () => {
    expect(formatCurrency(null)).toBe('—');
  });
});

describe('formatPercent', () => {
  it('formats with default 1 decimal', () => {
    expect(formatPercent(12.345)).toBe('12.3%');
  });
  it('respects fractionDigits', () => {
    expect(formatPercent(12.345, 0)).toBe('12%');
  });
  it('returns dash for null', () => {
    expect(formatPercent(null)).toBe('—');
  });
});

describe('formatRelativeTime', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-04-17T22:00:00Z'));
  });
  afterEach(() => {
    vi.useRealTimers();
  });

  it('says "just now" for fresh timestamps', () => {
    expect(formatRelativeTime(new Date('2026-04-17T21:59:30Z'))).toBe('just now');
  });
  it('formats minutes', () => {
    expect(formatRelativeTime(new Date('2026-04-17T21:55:00Z'))).toBe('5m ago');
  });
  it('formats hours', () => {
    expect(formatRelativeTime(new Date('2026-04-17T18:00:00Z'))).toBe('4h ago');
  });
  it('formats days', () => {
    expect(formatRelativeTime(new Date('2026-04-15T22:00:00Z'))).toBe('2d ago');
  });
  it('returns empty string for null/undefined', () => {
    expect(formatRelativeTime(null)).toBe('');
    expect(formatRelativeTime(undefined)).toBe('');
  });
});

describe('formatTableDate', () => {
  it('parses ISO and returns compact pieces', () => {
    const d = formatTableDate('2026-04-06T13:36:27Z');
    expect(d.date).toMatch(/Apr/);
    expect(d.date).toMatch(/26$/); // 2-digit year
    expect(d.time).toMatch(/(AM|PM)/);
  });

  it('falls back to splitting the legacy server format', () => {
    // Server returns "April 5,2026 06:36 PM" — non-standard, Date.parse fails
    // on some runtimes. The fallback splits on first space.
    const d = formatTableDate('April 5,2026 06:36 PM');
    // Either it parses (modern engines do) or it splits cleanly — both
    // outputs must produce non-empty date + time and never throw.
    expect(d.date).toBeTruthy();
    expect(d.time).toBeTruthy();
    expect(d.date.length).toBeLessThanOrEqual(15);
  });

  it('returns dash for null/undefined', () => {
    expect(formatTableDate(null)).toEqual({ date: '—', time: '' });
    expect(formatTableDate(undefined)).toEqual({ date: '—', time: '' });
  });
});

describe('formatPhone', () => {
  it('formats a 10-digit local number with leading zero + grouping', () => {
    expect(formatPhone('3141150209')).toBe('0314 115 0209');
  });
  it('strips +92 country code', () => {
    expect(formatPhone('+923141150209')).toBe('0314 115 0209');
    expect(formatPhone('923141150209')).toBe('0314 115 0209');
  });
  it('strips leading 0 + reformats', () => {
    expect(formatPhone('03141150209')).toBe('0314 115 0209');
  });
  it('returns raw input when the digit count does not look local', () => {
    // Too short — falls through to raw
    expect(formatPhone('12345')).toBe('12345');
    // 11 digits after stripping (1 + 5551234567) — also falls through
    expect(formatPhone('+1-555-1234567')).toBe('+1-555-1234567');
  });
  it('returns empty string for empty input', () => {
    expect(formatPhone('')).toBe('');
    expect(formatPhone(null)).toBe('');
  });
});

describe('initials', () => {
  it('takes first letter of first two name parts', () => {
    expect(initials('Mohammad Mudassar')).toBe('MM');
    expect(initials('aisha khan')).toBe('AK');
    expect(initials('Sara')).toBe('S');
  });
  it('handles trailing/extra whitespace', () => {
    expect(initials('  Sehar   Maqsood  ')).toBe('SM');
  });
  it('returns "U" fallback for empty/null', () => {
    expect(initials(null)).toBe('U');
    expect(initials(undefined)).toBe('U');
    expect(initials('')).toBe('U');
  });
});
