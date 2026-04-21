/**
 * Display formatters — pure, no allocations beyond the result string.
 * Centralise so dashboard, tables, and KPI cards stay consistent.
 */

const numberFmt = new Intl.NumberFormat('en-US');
const compactFmt = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 });

export function formatNumber(n: number | null | undefined): string {
  if (n == null || Number.isNaN(n)) return '—';
  return numberFmt.format(n);
}

export function formatCompact(n: number | null | undefined): string {
  if (n == null || Number.isNaN(n)) return '—';
  return compactFmt.format(n);
}

export function formatCurrency(n: number | null | undefined, currency = 'PKR'): string {
  if (n == null || Number.isNaN(n)) return '—';
  return new Intl.NumberFormat('en-PK', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(n);
}

export function formatPercent(n: number | null | undefined, fractionDigits = 1): string {
  if (n == null || Number.isNaN(n)) return '—';
  return `${n.toFixed(fractionDigits)}%`;
}

export function formatRelativeTime(input: string | Date | null | undefined): string {
  if (!input) return '';
  const date = input instanceof Date ? input : new Date(input);
  const diff = Math.floor((Date.now() - date.getTime()) / 1000);
  if (diff < 60) return 'just now';
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86_400) return `${Math.floor(diff / 3600)}h ago`;
  if (diff < 604_800) return `${Math.floor(diff / 86_400)}d ago`;
  return date.toLocaleDateString();
}

/**
 * Compact two-piece date for table cells. Accepts the legacy server format
 * ("April 5,2026 06:36 PM") and ISO. Returns { date, time } with no
 * whitespace inside each piece — render with nowrap so the cell is
 * guaranteed ≤2 lines regardless of column width.
 *
 * Falls back to splitting on the first space if parsing fails (e.g.
 * non-date strings like "—") so the cell never throws.
 */
export function formatTableDate(input: string | Date | null | undefined): { date: string; time: string } {
  if (!input) return { date: '—', time: '' };
  const date = input instanceof Date ? input : new Date(input);
  if (Number.isNaN(date.getTime())) {
    // Server "April 5,2026 06:36 PM" — already pre-formatted; split it
    // into "April 5,2026" + "06:36 PM" and squash to "Apr 5, 26 / time".
    if (typeof input === 'string') {
      const m = input.match(/^([A-Za-z]+)\s+(\d+),(\d{4})\s+(.+)$/);
      if (m) {
        const monthShort = m[1].slice(0, 3);
        return { date: `${monthShort} ${m[2]}, ${m[3].slice(2)}`, time: m[4] };
      }
      const sp = input.indexOf(' ');
      return sp > 0 ? { date: input.slice(0, sp), time: input.slice(sp + 1) } : { date: input, time: '' };
    }
    return { date: '—', time: '' };
  }
  const day = date.getDate();
  const month = date.toLocaleString('en-US', { month: 'short' });
  const year2 = String(date.getFullYear()).slice(2);
  const time = date.toLocaleString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
  return { date: `${month} ${day}, ${year2}`, time };
}

/**
 * Format a Pakistani phone number for readability — local format, no
 * country code (per product preference; the +92 prefix added visual noise).
 *
 *   "3141150209"     → "0314 115 0209"
 *   "923141150209"   → "0314 115 0209"
 *   "+923141150209"  → "0314 115 0209"
 *
 * Falls back to the raw input if the digit count doesn't look local.
 */
export function formatPhone(raw: string | null | undefined): string {
  if (!raw) return '';
  const digits = raw.replace(/\D/g, '');
  const local = digits.startsWith('92')
    ? digits.slice(2)
    : digits.startsWith('0')
      ? digits.slice(1)
      : digits;
  if (local.length !== 10) return raw;
  return `0${local.slice(0, 3)} ${local.slice(3, 6)} ${local.slice(6)}`;
}

export function initials(name: string | null | undefined): string {
  if (!name) return 'U';
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase() ?? '')
    .join('') || 'U';
}
