/**
 * Thin fetch wrapper + 5-minute client-side cache keyed by (url + filter hash).
 * Lives in window.MD_API so other modules don't import it.
 *
 * On network/server failure the wrapper:
 *   1. rejects so the caller can re-render its own empty/error state;
 *   2. surfaces a visible toast with a retry action (no silent console.error);
 *   3. skips caching the failure so retry hits the network, not a stale value.
 */
(function () {
    'use strict';

    const CACHE_TTL_MS = 5 * 60 * 1000;
    const cache = new Map();

    function keyFor(url, params) {
        // Sort keys so {a,b} and {b,a} hash to the same cache slot.
        const sorted = params
            ? Object.fromEntries(
                  Object.entries(params).sort(([a], [b]) => a.localeCompare(b))
              )
            : null;
        return url + '|' + JSON.stringify(sorted);
    }

    function buildUrl(base, params) {
        if (!params) return base;
        const u = new URL(base, window.location.origin);
        Object.entries(params).forEach(([k, v]) => {
            if (v === null || v === undefined || v === '') return;
            if (Array.isArray(v)) {
                v.forEach((val) => u.searchParams.append(k + '[]', String(val)));
            } else {
                u.searchParams.set(k, String(v));
            }
        });
        return u.toString();
    }

    function ensureToastRegion() {
        let region = document.getElementById('mdToastRegion');
        if (region) return region;
        region = document.createElement('div');
        region.id = 'mdToastRegion';
        region.className = 'md-toast-region';
        region.setAttribute('role', 'status');
        region.setAttribute('aria-live', 'polite');
        region.setAttribute('aria-atomic', 'true');
        document.body.appendChild(region);
        return region;
    }

    function showToast(message, options) {
        options = options || {};
        const region = ensureToastRegion();
        const toast = document.createElement('div');
        toast.className = 'md-toast md-toast--' + (options.variant || 'error');

        const text = document.createElement('span');
        text.textContent = message;
        toast.appendChild(text);

        if (typeof options.onRetry === 'function') {
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'md-toast-action';
            retry.textContent = 'Retry';
            retry.addEventListener('click', () => {
                toast.remove();
                try { options.onRetry(); } catch (_) { /* caller decides */ }
            });
            toast.appendChild(retry);
        }

        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'md-toast-action';
        dismiss.setAttribute('aria-label', 'Dismiss');
        dismiss.textContent = 'Dismiss';
        dismiss.addEventListener('click', () => toast.remove());
        toast.appendChild(dismiss);

        region.appendChild(toast);
        if (options.autoDismissMs !== 0) {
            const ms = options.autoDismissMs || 6000;
            setTimeout(() => toast.remove(), ms);
        }
    }

    async function parseErrorMessage(resp) {
        try {
            const json = await resp.clone().json();
            if (json && typeof json.message === 'string' && json.message) return json.message;
        } catch (_) { /* not JSON */ }
        return 'Request failed (HTTP ' + resp.status + ')';
    }

    async function get(url, params, options) {
        options = options || {};
        const bypassCache = options.noCache === true;
        const silent = options.silent === true;
        const fullUrl = buildUrl(url, params);
        const key = keyFor(url, params);

        if (!bypassCache) {
            const cached = cache.get(key);
            if (cached && cached.expires > Date.now()) {
                return cached.data;
            }
        }

        let resp;
        try {
            resp = await fetch(fullUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });
        } catch (networkErr) {
            if (!silent) {
                showToast('Network error — check your connection.', {
                    onRetry: () => get(url, params, Object.assign({}, options, { noCache: true })),
                });
            }
            throw networkErr;
        }

        if (!resp.ok) {
            const msg = await parseErrorMessage(resp);
            if (!silent) {
                showToast(msg, {
                    onRetry: () => get(url, params, Object.assign({}, options, { noCache: true })),
                });
            }
            throw new Error(msg);
        }

        const json = await resp.json();
        cache.set(key, { data: json, expires: Date.now() + CACHE_TTL_MS });
        return json;
    }

    async function post(url, body) {
        let resp;
        try {
            resp = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify(body || {}),
            });
        } catch (networkErr) {
            showToast('Network error — could not save.', { variant: 'error' });
            throw networkErr;
        }
        if (!resp.ok) {
            const msg = await parseErrorMessage(resp);
            showToast(msg, { variant: 'error' });
            throw new Error(msg);
        }
        return resp.json();
    }

    function invalidate() {
        cache.clear();
    }

    window.MD_API = { get, post, invalidate, showToast };
})();
