import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { api, ApiError, tokenStore } from './api';

describe('tokenStore', () => {
  beforeEach(() => {
    sessionStorage.clear();
  });

  it('round-trips token through sessionStorage', () => {
    expect(tokenStore.get()).toBeNull();
    tokenStore.set('abc123');
    expect(tokenStore.get()).toBe('abc123');
    tokenStore.clear();
    expect(tokenStore.get()).toBeNull();
  });
});

describe('ApiError', () => {
  it('exposes status and field errors', () => {
    const err = new ApiError('Validation failed', 422, { name: ['Required'] });
    expect(err.status).toBe(422);
    expect(err.fieldErrors).toEqual({ name: ['Required'] });
    expect(err.message).toBe('Validation failed');
  });

  it('returns an empty object from fieldErrors when errors is an array', () => {
    const err = new ApiError('Generic', 500, []);
    expect(err.fieldErrors).toEqual({});
  });

  it('handles missing errors gracefully', () => {
    const err = new ApiError('No body', 500);
    expect(err.fieldErrors).toEqual({});
    expect(err.errors).toEqual([]);
  });
});

describe('api client — envelope unwrapping', () => {
  beforeEach(() => {
    sessionStorage.clear();
    vi.stubGlobal('fetch', vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  const mockJson = (status: number, body: unknown) => {
    (fetch as unknown as ReturnType<typeof vi.fn>).mockResolvedValueOnce(
      new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
      }),
    );
  };

  it('unwraps the standard envelope and returns inner data', async () => {
    mockJson(200, { success: true, status: true, message: 'ok', data: { id: 1, name: 'X' }, errors: [] });
    const result = await api.get<{ id: number; name: string }>('/api/test');
    expect(result).toEqual({ id: 1, name: 'X' });
  });

  it('returns the raw body when raw:true is set', async () => {
    // Simulates leads/datatable shape where data sits alongside meta.
    mockJson(200, { data: [{ id: 1 }], meta: { total: 100 }, permissions: {} });
    const result = await api.post<{ data: unknown[]; meta: unknown }>('/api/leads/datatable', {}, { raw: true });
    expect(result).toEqual({ data: [{ id: 1 }], meta: { total: 100 }, permissions: {} });
  });

  it('throws ApiError on non-2xx with status, message, and fieldErrors mapped', async () => {
    mockJson(422, { message: 'Lead name is required.', errors: { name: ['Lead name is required.'] } });
    let caught: unknown;
    try {
      await api.post('/api/leads', {});
    } catch (err) {
      caught = err;
    }
    expect(caught).toBeInstanceOf(ApiError);
    expect((caught as ApiError).status).toBe(422);
    expect((caught as ApiError).message).toBe('Lead name is required.');
    expect((caught as ApiError).fieldErrors.name).toEqual(['Lead name is required.']);
  });

  it('throws ApiError when the envelope says success:false even on 200', async () => {
    mockJson(200, { success: false, status: false, message: 'Phone exists', data: null, errors: [] });
    await expect(api.post('/api/leads', {})).rejects.toMatchObject({
      message: 'Phone exists',
      status: 200,
    });
  });

  it('attaches Authorization header when a token is present', async () => {
    tokenStore.set('test-token');
    mockJson(200, { success: true, message: '', data: null, errors: [] });
    await api.get('/api/me');
    const call = (fetch as unknown as ReturnType<typeof vi.fn>).mock.calls[0];
    const headers = call[1].headers as Record<string, string>;
    expect(headers.Authorization).toBe('Bearer test-token');
  });

  it('skips Authorization when no token is set', async () => {
    mockJson(200, { success: true, message: '', data: null, errors: [] });
    await api.get('/api/public');
    const call = (fetch as unknown as ReturnType<typeof vi.fn>).mock.calls[0];
    const headers = call[1].headers as Record<string, string>;
    expect(headers.Authorization).toBeUndefined();
  });

  it('dispatches auth:expired event on 401', async () => {
    const listener = vi.fn();
    window.addEventListener('auth:expired', listener);
    mockJson(401, { message: 'Invalid Token.' });
    await expect(api.get('/api/leads')).rejects.toBeInstanceOf(ApiError);
    expect(listener).toHaveBeenCalled();
    window.removeEventListener('auth:expired', listener);
  });

  it('serialises JSON body with correct Content-Type', async () => {
    mockJson(200, { success: true, message: '', data: null, errors: [] });
    await api.post('/api/leads', { name: 'X', phone: '0301' });
    const call = (fetch as unknown as ReturnType<typeof vi.fn>).mock.calls[0];
    const headers = call[1].headers as Record<string, string>;
    expect(headers['Content-Type']).toBe('application/json');
    expect(call[1].body).toBe(JSON.stringify({ name: 'X', phone: '0301' }));
  });
});
