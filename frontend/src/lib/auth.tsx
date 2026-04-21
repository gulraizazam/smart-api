import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { AUTH_MODE, api, tokenStore } from './api';
import { clearFrecency, setFrecencyUser } from './page-frecency';

/**
 * Auth provider supporting both the legacy bearer-token mode and the
 * new Sanctum cookie mode. The mode is fixed at build time via
 * `VITE_AUTH_MODE`; see `api.ts` for the runtime resolution.
 *
 * Cookie mode:
 *   • `login()` POSTs `{ email, password, client: 'web' }` — the
 *     backend establishes a web session and does NOT issue a token.
 *   • User state hydrates from `GET /api/user` on boot; a cached
 *     `cutera.user` in sessionStorage is used as the initial render
 *     value to avoid a flash of "not authenticated".
 *   • `logout()` POSTs `/api/logout` so the server invalidates the
 *     session. Idempotent — also clears local state.
 *
 * Bearer mode (legacy, default):
 *   • `login()` receives `{ …user, api_token }` and stores the token
 *     in sessionStorage. Unchanged from the original Sprint 1 flow.
 *   • Boot hydration reads from sessionStorage only — no /api/user
 *     round-trip. A bad token surfaces as a 401 on the next API call
 *     and the `auth:expired` listener clears local state.
 */

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  role?: string;
  permissions?: string[];
  /** server returns the full user object — keep extras accessible without re-typing each field */
  [key: string]: unknown;
};

type LoginPayload = { email: string; password: string };

type LoginResponse = AuthUser & { api_token?: string };

type AuthContextValue = {
  user: AuthUser | null;
  isAuthenticated: boolean;
  isBooting: boolean;
  login: (creds: LoginPayload) => Promise<void>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

const USER_CACHE_KEY = 'cutera.user';

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [isBooting, setBooting] = useState(true);

  useEffect(() => {
    let cancelled = false;

    // Warm the UI immediately from any cached user so the first paint
    // isn't "not authenticated". The cache is trusted optimistically;
    // the server is the authority and the next API call will 401 if
    // the session/token is stale.
    const cached = sessionStorage.getItem(USER_CACHE_KEY);
    if (cached) {
      try {
        const u = JSON.parse(cached) as AuthUser;
        setUser(u);
        setFrecencyUser(u.id);
      } catch {
        /* ignore malformed cache */
      }
    }

    const finish = () => {
      if (!cancelled) setBooting(false);
    };

    if (AUTH_MODE === 'cookie') {
      // Cookie mode: confirm or refresh the session via /api/user.
      // A 401 here clears the cache and drops to login; any network
      // error leaves the cached user in place so transient flaps
      // don't bounce the admin to the login screen.
      api
        .get<AuthUser>('/api/user')
        .then((fresh) => {
          if (cancelled || !fresh) return;
          sessionStorage.setItem(USER_CACHE_KEY, JSON.stringify(fresh));
          setUser(fresh);
          setFrecencyUser(fresh.id);
        })
        .catch(() => {
          // /api/user failed — either 401 (clear state via
          // auth:expired listener below) or network issue (leave
          // cached user in place).
        })
        .finally(finish);
    } else {
      // Bearer mode: no round-trip needed; cached user is enough.
      // Stale tokens fail on the next real request.
      finish();
    }

    const onExpired = () => {
      if (AUTH_MODE === 'bearer') tokenStore.clear();
      sessionStorage.removeItem(USER_CACHE_KEY);
      setFrecencyUser(null);
      setUser(null);
    };
    window.addEventListener('auth:expired', onExpired);

    return () => {
      cancelled = true;
      window.removeEventListener('auth:expired', onExpired);
    };
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      isAuthenticated: !!user,
      isBooting,
      async login(creds) {
        const payload =
          AUTH_MODE === 'cookie' ? { ...creds, client: 'web' } : creds;
        const data = await api.post<LoginResponse>('/api/login', payload);

        if (AUTH_MODE === 'bearer') {
          if (!data.api_token) {
            throw new Error('Server did not return an auth token.');
          }
          tokenStore.set(data.api_token);
        }

        const { api_token: _omit, ...rest } = data;
        void _omit;
        sessionStorage.setItem(USER_CACHE_KEY, JSON.stringify(rest));
        setFrecencyUser(rest.id);
        setUser(rest as AuthUser);
      },
      async logout() {
        // Try to tell the server first so the session (cookie mode) or
        // the current PAT (bearer mode) is invalidated. Ignore failures
        // — local cleanup must always run or the UI gets stuck.
        try {
          await api.post('/api/logout');
        } catch {
          /* server-side cleanup best-effort */
        }

        if (AUTH_MODE === 'bearer') tokenStore.clear();
        sessionStorage.removeItem(USER_CACHE_KEY);
        clearFrecency();
        setFrecencyUser(null);
        setUser(null);
      },
    }),
    [user, isBooting],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within <AuthProvider>');
  return ctx;
}
