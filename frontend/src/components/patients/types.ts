/**
 * Patient module wire-format types — verified against the live API via curl
 * probes during the audit. Adjust the shape here, not inline in components,
 * so the contract is single-source-of-truth and easy to update.
 *
 * GET /api/patients/{id} routes the raw model through `PatientDetailResource`
 * on the backend so the wire format is a strict whitelist — no auth fields,
 * no `password`/`remember_token`, no arbitrary model attributes. If you add
 * a field here, also add it to the Resource.
 */

export type PatientRow = {
  id: number;
  patient_id?: number;
  name: string;
  email?: string | null;
  phone?: string | null;
  gender?: number;            // 1 = Male, 2 = Female
  active?: boolean | number;
  created_at?: string;
  membership?: PatientMembership | null;
};

export type PatientMembership = {
  code?: string;
  type?: string;
  start_date?: string;
  end_date?: string;
  is_active?: boolean;
} | null;

export type PatientDatatableResponse = {
  data: PatientRow[];
  meta: {
    field: string;
    page: number;
    pages: number;
    perpage: number;
    total: number;
    sort: string;
  };
  permissions?: Record<string, boolean>;
  filter_values?: Record<string, unknown>;
  active_filters?: Record<string, unknown>;
};

export type PatientDetail = {
  id: number;
  patient_code?: string;
  name: string;
  email?: string | null;
  phone?: string | null;
  gender?: number;
  gender_label?: string;
  cnic?: string | null;
  dob?: string | null;
  referred_by?: number | null;
  referrer_name?: string | null;
  image_url?: string | null;
  active?: boolean | number;
  created_at?: string;
};

export type PatientDetailPermissions = {
  edit?: boolean;
  delete?: boolean;
  active?: boolean;
  inactive?: boolean;
  manage?: boolean;
  contact?: boolean;          // Gates phone visibility
  add_referrals?: boolean;
};

export type PatientDetailResponse = {
  patient: PatientDetail;
  membership: PatientMembership;
  permissions: PatientDetailPermissions;
};

export type PatientNote = {
  id: number;
  patient_id: number;
  note: string;
  is_pinned: boolean;
  created_by: { id: number; name: string };
  created_at: string;
  updated_at: string;
};

export type PatientActivityItem = {
  id?: number;
  description?: string;
  message?: string;
  causer?: { name?: string } | null;
  created_at?: string;
  changes?: Record<string, unknown>;
};

export type PatientAppointmentRow = {
  id: number;
  doctor?: string | { name?: string };
  service?: string | { name?: string };
  status?: string | { name?: string };
  appointment_date?: string;
  location?: string | { name?: string };
  notes?: string;
  amount?: number | string;
  invoice_id?: number | null;
  [key: string]: unknown;
};

export type PatientAppointmentDatatableResponse = {
  data: PatientAppointmentRow[];
  meta: {
    page: number;
    pages: number;
    perpage: number;
    total: number;
    field?: string;
    sort?: string;
  };
  permissions?: Record<string, boolean>;
  filter_values?: Record<string, unknown>;
};

export type PatientDocumentRow = {
  id: number;
  name?: string;
  document_type?: string;
  url?: string;
  user_id?: number;
  created_at?: string;
};

export type PatientSearchResult = { id: number; name: string; phone?: string };
