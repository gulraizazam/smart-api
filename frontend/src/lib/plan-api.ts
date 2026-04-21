/**
 * Typed wrappers around the legacy plan create/edit endpoints.
 *
 * Strategy: server is the source of truth for tax, discount, and net-amount
 * math. The SPA sends (service_id, discount_id) and stores whatever the
 * server returns for the row. No client-side pricing calculations — the
 * legacy JS/PHP pair has diverged in production; mirroring would introduce
 * paisa-level drift that fails the discount-limit validator on save.
 *
 * The create flow is row-at-a-time:
 *
 *   1. GET  /api/packages/create              → random_id + lookup data
 *   2. for each row:
 *        GET  /api/packages/getserviceinfo    → service price + tax basis
 *        GET  /api/packages/getdiscountinfo   → discount type + price + net
 *        POST /api/packages/savepackagesservice (→ makePackagesServicesData)
 *             creates a package_bundles row tagged with random_id (no package_id yet)
 *   3. POST /api/packages/savepackages        → binds all random_id rows
 *             to a newly-created packages row inside a DB transaction
 *
 * If the user cancels the dialog, we POST /api/packages/deletepackagesservice
 * for each staged row so they don't orphan. The legacy admin has a cleanup
 * job as a backstop.
 */

import { api } from '@/lib/api';

// ── Create form prefill ─────────────────────────────────

export type PlanCreateFormData = {
  locations: Record<string, string>;
  random_id: string;
  paymentmodes: Record<string, string>;
  range: [string | number, string | number];
  discount_type: Record<string, string>;
  discounts: Array<{ id: number; name: string }>;
  patient_specific_data_loaded?: boolean;
  patient_name?: string;
  last_consultation_location_id?: number;
  last_consultation_id?: number;
  last_consultation_location_name?: string;
  appointmentArray?: Array<{ id: number; label: string; type: 'A' | 'C' }>;
  patient_membership?: string | null;
};

export function fetchPlanCreateData(_patientId?: number): Promise<PlanCreateFormData> {
  // Endpoint lives under the `plans/*` namespace (routes/api/appointments-admin.php).
  // Patient context comes from the separate getappointmentinfo call driven by
  // the selected location. The unused _patientId keeps the call site forward-
  // compatible if we later add a patient-scoped variant on the backend.
  return api.get<PlanCreateFormData>('/api/plans/create');
}

// ── Patient search ──────────────────────────────────────

export type PatientSearchResult = {
  id: number;
  name: string;
  phone?: string | null;
};

export async function searchPatients(query: string): Promise<PatientSearchResult[]> {
  if (!query || query.length < 2) return [];
  const qs = new URLSearchParams({ search: query }).toString();
  const resp = await api.get<{ patients: PatientSearchResult[] }>(
    `/api/users/getpatient-optimized?${qs}`,
  );
  return resp.patients ?? [];
}

// ── Appointment lookup ──────────────────────────────────

export type PlanAppointmentEntry = {
  /** Composite appointment key: "{id}.A" for existing, "{consultationId}.N" for create-new. */
  id: string;
  label: string;
  type: 'A' | 'N';
};

type RawAppointmentEntry = {
  id: string;
  name: string;
  doctor_id?: number;
};

type RawAppointmentResp = {
  appointments?: Record<string, RawAppointmentEntry> | RawAppointmentEntry[];
  membership?: string | null;
  users?: Record<string, string>;
  selected_doctor_id?: number | null;
  latest_consultation_id?: number | null;
};

export async function fetchAppointmentsForPlan(
  patientId: number,
  locationId: number,
): Promise<PlanAppointmentEntry[]> {
  const qs = new URLSearchParams({
    patient_id: String(patientId),
    location_id: String(locationId),
  }).toString();
  const resp = await api.get<RawAppointmentResp>(
    `/api/packages/getappointmentinfo?${qs}`,
  );
  const raw = resp.appointments ?? [];
  // Server returns a dict keyed by appointment.id; normalise to an ordered array.
  const entries: RawAppointmentEntry[] = Array.isArray(raw) ? raw : Object.values(raw);
  return entries.map((a) => ({
    id: a.id,
    label: a.name,
    type: a.id.endsWith('.N') ? 'N' : 'A',
  }));
}

// ── Service + discount lookup ───────────────────────────

export type ServiceCatalogItem = {
  id: number;
  name: string;
  price: string | number;
  tax_treatment_type_id?: number;
};

export async function fetchServicesForLocation(locationId: number): Promise<ServiceCatalogItem[]> {
  const qs = new URLSearchParams({ location_id: String(locationId) }).toString();
  const resp = await api.get<{ service: ServiceCatalogItem[] }>(
    `/api/packages/getservice?${qs}`,
  );
  return resp.service ?? [];
}

export type BundleCatalogItem = {
  id: number;
  name: string;
  price: string | number;
  tax_treatment_type_id?: number;
};

export async function fetchBundlesForLocation(locationId: number): Promise<BundleCatalogItem[]> {
  const qs = new URLSearchParams({ location_id: String(locationId) }).toString();
  const resp = await api.get<{ bundles: BundleCatalogItem[] }>(
    `/api/packages/getbundles?${qs}`,
  );
  return resp.bundles ?? [];
}

export type MembershipTypeItem = {
  id: number;
  name: string;
  amount: string | number;
};

export async function fetchMembershipTypes(
  locationId: number,
  patientId: number,
): Promise<MembershipTypeItem[]> {
  const qs = new URLSearchParams({
    location_id: String(locationId),
    patient_id: String(patientId),
  }).toString();
  const resp = await api.get<{ memberships: MembershipTypeItem[] }>(
    `/api/packages/getmemberships?${qs}`,
  );
  return resp.memberships ?? [];
}

export type MembershipCode = {
  id: number;
  code: string;
};

export async function searchMembershipCodes(
  search: string,
  membershipTypeId?: number,
): Promise<MembershipCode[]> {
  if (!search || search.length < 2) return [];
  const qs = new URLSearchParams({
    search,
    ...(membershipTypeId ? { membership_type_id: String(membershipTypeId) } : {}),
  }).toString();
  const resp = await api.get<{ codes: MembershipCode[] }>(
    `/api/packages/searchmembershipcodes?${qs}`,
    { raw: true },
  ).catch(() => ({ codes: [] as MembershipCode[] }));
  // searchmembershipcodes returns {status, data:{codes:[...]}} not the standard envelope.
  // api.get with raw:true gives us the full object; peek at data.codes.
  const anyResp = resp as unknown as { status?: boolean; data?: { codes?: MembershipCode[] }; codes?: MembershipCode[] };
  return anyResp.data?.codes ?? anyResp.codes ?? [];
}

export type MembershipInfo = {
  name: string;
  amount: string | number;
  tax_percentage?: string | number;
};

export async function fetchMembershipInfo(membershipTypeId: number): Promise<MembershipInfo | null> {
  const qs = new URLSearchParams({ membership_id: String(membershipTypeId) }).toString();
  try {
    return await api.get<MembershipInfo>(`/api/packages/getmembershipinfo?${qs}`);
  } catch {
    return null;
  }
}

export type ServiceInfo = {
  service_price: string | number;
  net_amount: string | number;
  tax_percentage?: string | number;
  discount_id?: number | null;
};

export async function fetchServiceInfo(params: {
  service_id: number;
  location_id: number;
  discount_id?: number | null;
}): Promise<ServiceInfo | null> {
  const qs = new URLSearchParams({
    service_id: String(params.service_id),
    location_id: String(params.location_id),
    ...(params.discount_id ? { discount_id: String(params.discount_id) } : {}),
  }).toString();
  try {
    return await api.get<ServiceInfo>(`/api/packages/getserviceinfo?${qs}`);
  } catch {
    return null;
  }
}

export type DiscountInfo = {
  discount_type: string;
  discount_price: string | number;
  net_amount: string | number;
  custom_checked: 0 | 1;
  discount_is_voucher: boolean;
};

export async function fetchDiscountInfo(params: {
  service_id: number;
  discount_id: number;
  patient_id: number;
  location_id: number;
}): Promise<DiscountInfo | null> {
  const qs = new URLSearchParams({
    service_id: String(params.service_id),
    discount_id: String(params.discount_id),
    patient_id: String(params.patient_id),
    location_id: String(params.location_id),
  }).toString();
  try {
    return await api.get<DiscountInfo>(`/api/packages/getdiscountinfo?${qs}`);
  } catch {
    return null;
  }
}

export type CustomDiscountInfo = {
  net_amount: string | number;
};

/**
 * Calculate a custom-discount price. `discount_value` + `discount_type` are
 * user-entered; server validates against the sys-discounts range setting
 * and returns false on out-of-bounds.
 */
export async function fetchCustomDiscountInfo(params: {
  service_id: number;
  discount_id: number;
  discount_value: number;
  discount_type: 'Fixed' | 'Percentage';
  patient_id: number;
  location_id: number;
}): Promise<CustomDiscountInfo | null> {
  const qs = new URLSearchParams({
    service_id: String(params.service_id),
    discount_id: String(params.discount_id),
    discount_value: String(params.discount_value),
    discount_type: params.discount_type,
    patient_id: String(params.patient_id),
    location_id: String(params.location_id),
  }).toString();
  try {
    return await api.get<CustomDiscountInfo>(`/api/packages/getdiscountinfo_custom?${qs}`);
  } catch {
    return null;
  }
}

// ── Voucher reserve / refund ───────────────────────────

export async function reserveVoucher(params: {
  voucher_id: number;
  patient_id: number;
  amount: number;
}): Promise<void> {
  await api.post('/api/plans/voucher/reserve', params);
}

export async function refundVoucher(params: {
  voucher_id: number;
  patient_id: number;
  amount: number;
}): Promise<void> {
  await api.post('/api/plans/voucher/refund', params);
}

// ── Row create / delete ─────────────────────────────────

export type StagedPlanRow = {
  id: number;
  service_id: number;
  service_name: string;
  service_price: string | number;
  discount_name: string | null;
  discount_type: string | null;
  discount_price: string | number;
  net_amount: string | number;
  tax_percentage: string | number;
  tax_price: string | number;
  tax_including_price: string | number;
  sold_by: number | null;
  sold_by_name: string | null;
};

type SaveServiceResp = {
  servicesData: {
    bundlesData: {
      id: number;
      service_name: string;
      service_price: string | number;
      net_amount: string | number;
      tax_percentage: string | number;
      tax_price: string | number;
      tax_including_price: string | number;
    };
    discount_name?: string | null;
    discount_type?: string | null;
    discount_price: string | number;
    sold_by: number | null;
    sold_by_name: string | null;
    total: string | number;
  };
};

export async function addServiceRow(payload: {
  bundle_id: number;
  discount_id: number | null;
  discount_price: number;
  net_amount: number;
  random_id: string;
  location_id: number;
  user_id: number;
  sold_by?: number | null;
  plan_type?: 'plan' | 'bundle' | 'membership';
}): Promise<StagedPlanRow> {
  const resp = await api.post<SaveServiceResp>('/api/packages/savepackagesservice', {
    ...payload,
    plan_type: payload.plan_type ?? 'plan',
    qty: 1,
  });
  const bd = resp.servicesData.bundlesData;
  return {
    id: bd.id,
    service_id: payload.bundle_id,
    service_name: bd.service_name,
    service_price: bd.service_price,
    discount_name: resp.servicesData.discount_name ?? null,
    discount_type: resp.servicesData.discount_type ?? null,
    discount_price: resp.servicesData.discount_price,
    net_amount: bd.net_amount,
    tax_percentage: bd.tax_percentage,
    tax_price: bd.tax_price,
    tax_including_price: bd.tax_including_price,
    sold_by: resp.servicesData.sold_by,
    sold_by_name: resp.servicesData.sold_by_name ?? null,
  };
}

type BundleSaveResp = {
  bundlesData: {
    id: number;
    service_name: string;
    service_price: string | number;
    net_amount: string | number;
    tax_percentage: string | number;
    tax_price: string | number;
    tax_including_price: string | number;
  };
};

export async function addBundleRow(payload: {
  bundle_id: number;
  location_id: number;
  net_amount: number;
  random_id: string;
  sold_by?: number | null;
}): Promise<StagedPlanRow> {
  const resp = await api.post<BundleSaveResp>('/api/packages/savebundle_service', {
    ...payload,
    source_type: 'bundle',
  });
  const bd = resp.bundlesData;
  return {
    id: bd.id,
    service_id: payload.bundle_id,
    service_name: bd.service_name,
    service_price: bd.service_price,
    discount_name: null,
    discount_type: null,
    discount_price: 0,
    net_amount: bd.net_amount,
    tax_percentage: bd.tax_percentage,
    tax_price: bd.tax_price,
    tax_including_price: bd.tax_including_price,
    sold_by: payload.sold_by ?? null,
    sold_by_name: null,
  };
}

export async function deleteServiceRow(
  packageBundleId: number,
  randomId: string,
): Promise<void> {
  await api.post('/api/packages/deletepackagesservice', {
    package_bundle_id: packageBundleId,
    random_id: randomId,
  });
}

// ── Final save ──────────────────────────────────────────

export type SavePlanPayload = {
  random_id: string;
  patient_id: number;
  location_id: number;
  /** Composite key from getappointmentinfo: "{id}.A" or "{consultationId}.N". */
  appointment_id: string;
  plan_type: 'plan' | 'bundle' | 'membership';
  /** Numeric string; legacy expects str_replace(',','') at server side. */
  total: string;
  /** IDs of the staged package_bundles rows. Server binds them to the new package. */
  package_bundles: number[];
  payment_mode_id?: number | null;
  cash_amount?: number;
};

export async function savePlan(payload: SavePlanPayload): Promise<{ status: boolean; package_id?: number; message?: string }> {
  return await api.post<{ status: boolean; package_id?: number; message?: string }>(
    '/api/packages/savepackages',
    payload,
    { raw: true },
  );
}

// ── Membership save ────────────────────────────────────

export type MembershipLine = {
  /** Matches PlanService::storeMembershipData expectations (field names follow
   *  legacy PascalCase form keys). */
  membershipId: number;
  membershipCodeId: number;
  RegularPrice: string;
  Amount: string;
  Tax: string;
  DiscountName?: string | null;
  Type?: string | null;
  DiscountValue?: number;
};

export type SaveMembershipPayload = {
  random_id: string;
  patient_id: number;
  location_id: number;
  appointment_id: string;
  plan_type: 'membership';
  total: string;
  package_memberships: MembershipLine[];
  payment_mode_id?: number | null;
  cash_amount?: number;
};

export async function saveMembershipPlan(
  payload: SaveMembershipPayload,
): Promise<{ status: boolean; package_id?: number; message?: string }> {
  return await api.post<{ status: boolean; package_id?: number; message?: string }>(
    '/api/packages/savepackages',
    payload,
    { raw: true },
  );
}

// ── Edit prefill + update ──────────────────────────────

export type EditFormData = {
  package: {
    id: number;
    random_id: string;
    patient_id: number;
    location_id: number;
    appointment_id: number | null;
    plan_type: string;
    total_price: string | number;
    name: string;
  };
  packagebundles: StagedPlanRow[];
  packageadvances: Array<{
    id: number;
    cash_flow: 'in' | 'out';
    cash_amount: number | string;
    is_setteled: number | boolean;
  }>;
  is_settled: boolean;
};

export function fetchPlanEditData(packageId: number): Promise<EditFormData> {
  // Route is registered under the `plans/*` prefix
  // (routes/api/appointments-admin.php:146), not `packages/*`. The earlier
  // `packages/edit/{id}` path 404'd because no route with that prefix is
  // defined — `packages.edit_cash` and `packages.refund.edit` exist but are
  // different endpoints.
  return api.get<EditFormData>(`/api/plans/edit/${packageId}`);
}

/**
 * Update existing plan. Note: legacy uses GET /packages/updatepackages
 * (weird for a mutation — predates the REST convention), so the SPA goes
 * through the same URL with a GET that carries the payload in query params.
 * The safer path is to mirror the legacy endpoint; calling with POST would
 * 405 against the `GET` route declaration.
 */
export async function updatePlan(payload: {
  random_id: string;
  patient_id: number;
  location_id: number;
  appointment_id: string;
  total: string;
  package_bundles: number[];
  payment_mode_id?: number | null;
  cash_amount?: number;
}): Promise<{ status: boolean; message?: string; data?: { setteled?: number } }> {
  // Legacy route is GET with a long query string — flatten the array.
  const qs = new URLSearchParams();
  Object.entries(payload).forEach(([k, v]) => {
    if (v === null || v === undefined) return;
    if (Array.isArray(v)) {
      v.forEach((item) => qs.append(`${k}[]`, String(item)));
    } else {
      qs.append(k, String(v));
    }
  });
  return await api.get<{ status: boolean; message?: string; data?: { setteled?: number } }>(
    `/api/packages/updatepackages?${qs.toString()}`,
    { raw: true },
  );
}
