/**
 * API client — thin fetch wrapper that:
 *   • unwraps the Laravel envelope { success, data, errors, message } → returns T
 *   • throws ApiError on non-2xx (or success:false), preserving status + field errors
 *   • emits a global "auth:expired" event on 401 so the auth provider can sign-out
 *
 * Supports two auth modes, selected at build time via `VITE_AUTH_MODE`:
 *
 *   cookie (recommended)
 *     — Request uses `credentials: 'include'`; Laravel session cookie
 *       authenticates the call. The XSRF-TOKEN cookie seeded by
 *       `/sanctum/csrf-cookie` is echoed back as `X-XSRF-TOKEN` on
 *       mutating requests. No token lives in sessionStorage.
 *   bearer (default, legacy)
 *     — Pulls a Sanctum PAT from sessionStorage and attaches it as
 *       `Authorization: Bearer …`. Kept for the mobile-app client and
 *       for rollback during the cookie-auth migration.
 *
 * Endpoint shape comes from app/Traits/ApiResponse.php — every API controller
 * returns the same envelope, so callers always type the unwrapped `data`.
 */

const BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '';

export type AuthMode = 'cookie' | 'bearer';

/**
 * Resolves at module load. Default is `cookie` (Sanctum SPA mode);
 * set `VITE_AUTH_MODE=bearer` explicitly at build time to fall back
 * to the legacy bearer-in-sessionStorage flow (useful for the mobile
 * app client or for rolling back a cookie-mode deploy without a
 * backend change). Anything other than `bearer` resolves to `cookie`.
 *
 * The migration landed 2026-04-18 — see
 * memory/project_spa_auth_migration_trigger.md for rationale and the
 * dual-mode plumbing in backend (AuthController::login branches on
 * `client=web` to omit the token; `/api/logout` invalidates both
 * credentials).
 */
export const AUTH_MODE: AuthMode =
  (import.meta.env.VITE_AUTH_MODE as string | undefined) === 'bearer' ? 'bearer' : 'cookie';

const TOKEN_KEY = 'cutera.token';

export const tokenStore = {
  get(): string | null {
    return sessionStorage.getItem(TOKEN_KEY);
  },
  set(token: string): void {
    sessionStorage.setItem(TOKEN_KEY, token);
  },
  clear(): void {
    sessionStorage.removeItem(TOKEN_KEY);
  },
};

type Envelope<T> = {
  success: boolean;
  status: boolean;
  message: string;
  data: T | null;
  errors: Record<string, string[]> | string[] | [];
};

export class ApiError extends Error {
  public readonly status: number;
  public readonly errors: Record<string, string[]> | string[] | [];

  constructor(message: string, status: number, errors: ApiError['errors'] = []) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
  }

  /** Field-keyed validation errors (422). */
  get fieldErrors(): Record<string, string[]> {
    if (this.errors && !Array.isArray(this.errors)) {
      return this.errors;
    }
    return {};
  }
}

type RequestOptions = Omit<RequestInit, 'body' | 'method' | 'headers'> & {
  headers?: Record<string, string>;
  body?: unknown;
  signal?: AbortSignal;
  /**
   * When true, return the raw JSON body instead of unwrapping `.data` from
   * the standard envelope. Use for legacy endpoints (e.g. `/api/leads/datatable`)
   * that return DataTables-style payloads with sibling fields like `meta`,
   * `permissions`, `active_filters` outside the envelope.
   */
  raw?: boolean;
};

/**
 * Read a cookie by name. Needed to forward the XSRF-TOKEN cookie
 * (seeded by `/sanctum/csrf-cookie`) back to Laravel as an
 * `X-XSRF-TOKEN` header so the CSRF middleware accepts mutating calls
 * made over a session cookie.
 */
function readCookie(name: string): string | null {
  if (typeof document === 'undefined') return null;
  const needle = `${name}=`;
  for (const chunk of document.cookie.split(';')) {
    const trimmed = chunk.trim();
    if (trimmed.startsWith(needle)) {
      return decodeURIComponent(trimmed.slice(needle.length));
    }
  }
  return null;
}

let csrfPromise: Promise<void> | null = null;

/**
 * Ensure the XSRF-TOKEN cookie is present before a mutating cookie-mode
 * call. Idempotent — multiple callers in the same tick share one fetch.
 */
function ensureCsrfCookie(): Promise<void> {
  if (AUTH_MODE !== 'cookie') return Promise.resolve();
  if (readCookie('XSRF-TOKEN')) return Promise.resolve();
  if (csrfPromise) return csrfPromise;
  csrfPromise = fetch(`${BASE_URL}/sanctum/csrf-cookie`, {
    credentials: 'include',
  })
    .then(() => undefined)
    .finally(() => {
      csrfPromise = null;
    });
  return csrfPromise;
}

const MUTATING_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

async function request<T>(
  method: string,
  path: string,
  opts: RequestOptions = {},
): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...(opts.headers ?? {}),
  };

  if (AUTH_MODE === 'bearer') {
    const token = tokenStore.get();
    if (token) headers.Authorization = `Bearer ${token}`;
  } else if (MUTATING_METHODS.has(method.toUpperCase())) {
    // Cookie mode: seed the CSRF cookie then forward its value as a
    // header on any write. Laravel's VerifyCsrfToken middleware expects
    // `X-XSRF-TOKEN` (URL-decoded) to match the XSRF-TOKEN cookie.
    await ensureCsrfCookie();
    const xsrf = readCookie('XSRF-TOKEN');
    if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;
  }

  let body: BodyInit | undefined;
  if (opts.body !== undefined && opts.body !== null) {
    if (opts.body instanceof FormData) {
      body = opts.body;
    } else {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(opts.body);
    }
  }

  const url = path.startsWith('http') ? path : `${BASE_URL}${path}`;
  const res = await fetch(url, {
    method,
    headers,
    body,
    signal: opts.signal,
    // Bearer mode may still want `include` if the same deploy serves
    // both a cookie-authed Blade admin and the SPA; sending the cookie
    // is harmless if the request doesn't rely on it.
    credentials: 'include',
  });

  let json: Envelope<T> | null = null;
  try {
    json = (await res.json()) as Envelope<T>;
  } catch {
    // Non-JSON response — fall through to status-based error.
  }

  if (res.status === 401) {
    window.dispatchEvent(new CustomEvent('auth:expired'));
  }

  // Two envelope shapes are in play across the legacy Laravel app:
  //   ApiResponse       → { success: true|false, status, message, data, errors }
  //   SimpleApiResponse → { status: true|false, message, data }
  // The former has a `success` key; the latter only `status`. Without this
  // fallback, SimpleApiResponse failures returned HTTP 200 were being
  // treated as successes (e.g. "Child records exist, unable to delete"
  // came back silent, the dialog closed cleanly, operator saw nothing).
  const envelopeFailure =
    json !== null &&
    (json.success === false ||
      (json.success === undefined && (json as { status?: unknown }).status === false));

  if (!res.ok || envelopeFailure) {
    const message = json?.message ?? `Request failed (${res.status})`;
    throw new ApiError(message, res.status, json?.errors ?? []);
  }

  if (opts.raw) {
    return (json as unknown) as T;
  }

  // Success — return unwrapped data. Some endpoints legitimately return
  // null data (e.g. delete operations); cast accordingly.
  return (json?.data ?? null) as T;
}

export const api = {
  get: <T>(path: string, opts?: RequestOptions) => request<T>('GET', path, opts),
  post: <T>(path: string, body?: unknown, opts?: RequestOptions) =>
    request<T>('POST', path, { ...opts, body }),
  put: <T>(path: string, body?: unknown, opts?: RequestOptions) =>
    request<T>('PUT', path, { ...opts, body }),
  patch: <T>(path: string, body?: unknown, opts?: RequestOptions) =>
    request<T>('PATCH', path, { ...opts, body }),
  delete: <T>(path: string, opts?: RequestOptions) => request<T>('DELETE', path, opts),
};
