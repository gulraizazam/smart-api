import { useEffect, useMemo } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Loader2 } from 'lucide-react';
import { api, ApiError } from '@/lib/api';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';

/**
 * Unified create + edit form for leads. Same fields, same validation —
 * only the submit endpoint and modal copy change between modes.
 *
 * Field set mirrors the legacy form (resources/views/admin/leads/create.blade.php):
 *   name, phone, gender, city → centre (cascading), source, status,
 *   service → child service (cascading), referred_by, email
 *
 * Endpoints used:
 *   • GET  /api/leads/create                   — bundles all lookups in one
 *     call (cities, services, sources, statuses, employees, gender).
 *   • POST /api/appointments/load-locations    — centres for a given city
 *   • POST /api/leads/load_child_services      — child services for a service
 *   • POST /api/leads                          — create
 *   • PUT  /api/leads/{id}                     — update
 *   • GET  /api/leads/detail/{id}              — prefill on edit
 */

/* Required fields here are stricter than `StoreLeadRequest::rules()` on the
 * server. The reason: the `leads_services` table has NOT NULL on
 * `service_id`, but the request validation marks it nullable — so the
 * server happily accepts `service_id: null`, then the lead-create transaction
 * blows up at the DB layer with an integrity violation.
 *
 * Centre + Source aren't strictly DB-required, but the legacy admin form
 * marks them required and downstream reports rely on them being populated.
 *
 * CUTERA-REVIEW (backend): align StoreLeadRequest with the schema by
 * making service_id required, OR change LeadService::createLead to skip
 * the leads_services insert when no service is supplied. Until then, this
 * stricter SPA validation prevents the 500 error path. */
const schema = z.object({
  name: z.string().min(1, 'Name is required').max(255),
  phone: z.string().min(10, 'Phone must be at least 10 digits').max(15),
  gender: z.enum(['1', '2'], { errorMap: () => ({ message: 'Pick a gender' }) }),
  city_id: z.string().min(1, 'City is required'),
  location_id: z.string().min(1, 'Centre is required'),
  service_id: z.string().min(1, 'Category is required'),
  lead_source_id: z.string().min(1, 'Source is required'),
  email: z.string().email('Invalid email').or(z.literal('')).optional(),
  lead_status_id: z.string().optional(),
  child_service_id: z.string().optional(),
  referred_by: z.string().optional(),
});

type FormValues = z.infer<typeof schema>;

/** /api/leads/create envelope.data shape — verified by curl probe. */
type CreateFormData = {
  Services?: Record<string, string>;
  cities?: Record<string, string>;
  lead_sources?: Record<string, string>;
  lead_statuses?: Record<string, string>;
  employees?: Record<string, string>;
  gender?: Record<string, string>;
};

/** Cascading lookup envelope from POST endpoints. */
type DropdownResponse = { dropdown?: Record<string, string> | string[] };

type Named = { id?: number; name?: string };
type LeadDetail = {
  id: number;
  name?: string;
  phone?: string;
  email?: string | null;
  gender?: number;
  city?: Named | null;
  city_id?: number;
  towns?: Named | null;
  location_id?: number;
  referred_by?: number;
  lead_status?: Named | null;
  lead_source?: Named | null;
  lead_service?: Array<{ service?: Named | null; child_service_id?: number | null }>;
};

type Mode = 'create' | 'edit';

interface LeadFormDialogProps {
  mode: Mode;
  /** Required when mode === 'edit' */
  leadId?: number | string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function LeadFormDialog({ mode, leadId, open, onOpenChange }: LeadFormDialogProps) {
  const qc = useQueryClient();

  // Single fetch for all primary lookups.
  const formData = useQuery({
    queryKey: ['leads', 'create-form-data'],
    queryFn: () => api.get<CreateFormData>('/api/leads/create'),
    enabled: open,
    staleTime: 5 * 60_000,
  });

  // Separate flagged statuses query — /api/leads/create returns a flat
  // name map without the system flags, so we need this to know which IDs
  // to exclude from the user-pickable dropdown.
  const statusFlags = useQuery({
    queryKey: ['leads', 'statuses', 'v2'],
    queryFn: () =>
      api.get<Array<{ value: number; text: string; is_booked?: boolean; is_arrived?: boolean; is_converted?: boolean; is_junk?: boolean }>>(
        '/api/leads/lead_statuses',
        { raw: true },
      ),
    enabled: open,
    staleTime: 5 * 60_000,
  });

  // Edit mode prefill — shares cache with the drawer's detail query.
  const detail = useQuery({
    queryKey: ['leads', 'detail', leadId],
    queryFn: () => api.get<{ lead: LeadDetail }>(`/api/leads/detail/${leadId}`),
    enabled: open && mode === 'edit' && leadId != null,
  });

  const {
    register,
    handleSubmit,
    reset,
    setError,
    setValue,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { gender: '1' as const },
  });

  // Watched values drive the cascading lookups. RHF re-renders only the
  // bound fields, so this is cheap.
  const watchedCity = watch('city_id');
  const watchedService = watch('service_id');

  const centres = useQuery({
    queryKey: ['leads', 'centres-by-city', watchedCity],
    queryFn: () =>
      api.post<DropdownResponse>('/api/appointments/load-locations', {
        city_id: Number(watchedCity),
      }),
    enabled: open && !!watchedCity,
    staleTime: 5 * 60_000,
  });

  const childServices = useQuery({
    queryKey: ['leads', 'child-services', watchedService],
    queryFn: () =>
      api.post<DropdownResponse>('/api/leads/load_child_services', {
        service_id: Number(watchedService),
      }),
    enabled: open && !!watchedService,
    staleTime: 5 * 60_000,
  });

  // Reset form when (a) opening in create mode or (b) edit data loads.
  useEffect(() => {
    if (!open) return;
    if (mode === 'create') {
      reset({ gender: '1' as const });
    } else if (detail.data?.lead) {
      const l = detail.data.lead;
      reset({
        name: l.name ?? '',
        phone: l.phone ?? '',
        email: l.email ?? '',
        gender: (l.gender === 2 ? '2' : '1') as '1' | '2',
        city_id: l.city?.id != null ? String(l.city.id) : (l.city_id != null ? String(l.city_id) : ''),
        location_id: l.location_id != null ? String(l.location_id) : '',
        lead_source_id: l.lead_source?.id != null ? String(l.lead_source.id) : '',
        lead_status_id: l.lead_status?.id != null ? String(l.lead_status.id) : '',
        service_id: l.lead_service?.[0]?.service?.id != null ? String(l.lead_service[0].service.id) : '',
        child_service_id: l.lead_service?.[0]?.child_service_id != null
          ? String(l.lead_service[0].child_service_id) : '',
        referred_by: l.referred_by != null ? String(l.referred_by) : '',
      });
    }
  }, [open, mode, detail.data, reset]);

  // When city changes, clear stale centre selection (unless prefilling).
  useEffect(() => {
    if (!open || mode === 'edit') return;
    setValue('location_id', '');
  }, [watchedCity, open, mode, setValue]);

  // Same for child service when service changes.
  useEffect(() => {
    if (!open || mode === 'edit') return;
    setValue('child_service_id', '');
  }, [watchedService, open, mode, setValue]);

  const submit = useMutation({
    mutationFn: (values: FormValues) => {
      const num = (v: string | undefined) => (v ? Number(v) : undefined);
      const body = {
        name: values.name,
        phone: values.phone,
        gender: Number(values.gender),
        city_id: Number(values.city_id),
        email: values.email || undefined,
        lead_source_id: num(values.lead_source_id),
        lead_status_id: num(values.lead_status_id),
        service_id: num(values.service_id),
        child_service_id: num(values.child_service_id),
        location_id: num(values.location_id),
        referred_by: num(values.referred_by),
      };
      return mode === 'create'
        ? api.post('/api/leads', body)
        : api.put(`/api/leads/${leadId}`, body);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['leads', 'datatable'] });
      if (mode === 'edit' && leadId != null) {
        qc.invalidateQueries({ queryKey: ['leads', 'detail', leadId] });
      }
      onOpenChange(false);
    },
  });

  const onSubmit = async (values: FormValues) => {
    try {
      await submit.mutateAsync(values);
    } catch (err) {
      if (err instanceof ApiError) {
        for (const [field, messages] of Object.entries(err.fieldErrors)) {
          if (field in values) {
            setError(field as keyof FormValues, { message: messages[0] });
          }
        }
      }
    }
  };

  // Convert dropdown payload (object map or array) → ordered options.
  const optionsFrom = (raw?: Record<string, string> | string[]): Array<{ value: string; label: string }> => {
    if (!raw) return [];
    if (Array.isArray(raw)) return raw.map((label, i) => ({ value: String(i), label }));
    return Object.entries(raw).map(([value, label]) => ({ value, label }));
  };

  const cities = optionsFrom(formData.data?.cities);
  const services = optionsFrom(formData.data?.Services);
  const sources = optionsFrom(formData.data?.lead_sources);
  // The /api/leads/create response gives status options as a flat name
  // map without flags. Cross-reference the flagged statuses query to drop
  // every system-derived status (Booked, Arrived, Converted, Junk) from
  // the user-pickable list.
  const flaggedStatusIds = useMemo(() => {
    const raw = statusFlags.data ?? [];
    return new Set(
      raw
        .filter((s) => s.is_booked || s.is_arrived || s.is_converted || s.is_junk)
        .map((s) => String(s.value)),
    );
  }, [statusFlags.data]);
  const statuses = optionsFrom(formData.data?.lead_statuses)
    .filter((s) => !flaggedStatusIds.has(String(s.value)));
  const employees = optionsFrom(formData.data?.employees);
  const centresOpts = optionsFrom(centres.data?.dropdown);
  const childServicesOpts = optionsFrom(childServices.data?.dropdown);

  const isLoading = (mode === 'edit' && detail.isLoading) || formData.isLoading;
  const title = mode === 'create' ? 'Add a new lead' : 'Edit lead';
  const submitLabel = mode === 'create' ? 'Create lead' : 'Save changes';
  const submittingLabel = mode === 'create' ? 'Creating…' : 'Saving…';

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto p-5">
        <DialogHeader className="mb-3">
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>

        {isLoading ? (
          <div className="flex items-center justify-center py-10 text-fg-muted text-[13px]">
            <Loader2 className="size-4 animate-spin mr-2" /> Loading form…
          </div>
        ) : (
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-3" noValidate>
            {submit.error && !(submit.error instanceof ApiError && submit.error.status === 422) && (
              <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
                {(submit.error as Error).message}
              </div>
            )}

            {/* Single dense grid — no section headers. Logical clusters
                still read clearly via row order: contact, then geography,
                then service, then tracking. */}
            <div className="grid grid-cols-1 gap-x-3 gap-y-2 sm:grid-cols-2">
              <Field label="Full name" required error={errors.name?.message}>
                <Input {...register('name')} placeholder="e.g. Ayesha Khan" autoComplete="name" className="h-9" />
              </Field>
              <Field label="Phone" required error={errors.phone?.message}>
                <Input {...register('phone')} placeholder="0314 115 0209" inputMode="tel" autoComplete="tel" className="h-9" />
              </Field>
              <Field label="Gender" required error={errors.gender?.message}>
                <Select {...register('gender')} className="h-9">
                  <option value="1">Male</option>
                  <option value="2">Female</option>
                </Select>
              </Field>
              <Field label="Email" error={errors.email?.message}>
                <Input {...register('email')} type="email" placeholder="optional" autoComplete="email" className="h-9" />
              </Field>

              <Field label="City" required error={errors.city_id?.message}>
                <Select {...register('city_id')} className="h-9">
                  <option value="">Select a city</option>
                  {cities.map((c) => (
                    <option key={c.value} value={c.value}>{c.label}</option>
                  ))}
                </Select>
              </Field>
              <Field
                label="Centre"
                required
                hint={!watchedCity ? 'Pick a city first' : centres.isLoading ? 'Loading…' : undefined}
                error={errors.location_id?.message}
              >
                <Select {...register('location_id')} disabled={!watchedCity || centres.isLoading} className="h-9">
                  <option value="">{centresOpts.length ? 'Select a centre' : 'No centres'}</option>
                  {centresOpts.map((c) => (
                    <option key={c.value} value={c.value}>{c.label}</option>
                  ))}
                </Select>
              </Field>

              <Field label="Category" required error={errors.service_id?.message}>
                <Select {...register('service_id')} className="h-9">
                  <option value="">Select a category</option>
                  {services.map((s) => (
                    <option key={s.value} value={s.value}>{s.label}</option>
                  ))}
                </Select>
              </Field>
              <Field
                label="Service"
                hint={!watchedService ? 'Pick a category first' : childServices.isLoading ? 'Loading…' : (!childServicesOpts.length ? 'None' : undefined)}
                error={errors.child_service_id?.message}
              >
                <Select {...register('child_service_id')} disabled={!watchedService || childServices.isLoading || !childServicesOpts.length} className="h-9">
                  <option value="">—</option>
                  {childServicesOpts.map((c) => (
                    <option key={c.value} value={c.value}>{c.label}</option>
                  ))}
                </Select>
              </Field>

              <Field label="Source" required error={errors.lead_source_id?.message}>
                <Select {...register('lead_source_id')} className="h-9">
                  <option value="">Select a source</option>
                  {sources.map((s) => (
                    <option key={s.value} value={s.value}>{s.label}</option>
                  ))}
                </Select>
              </Field>
              <Field label="Status" error={errors.lead_status_id?.message}>
                <Select {...register('lead_status_id')} className="h-9">
                  <option value="">—</option>
                  {statuses.map((s) => (
                    <option key={s.value} value={s.value}>{s.label}</option>
                  ))}
                </Select>
              </Field>

              <Field label="Referred by" error={errors.referred_by?.message} className="sm:col-span-2">
                <Select {...register('referred_by')} className="h-9">
                  <option value="">—</option>
                  {employees.map((e) => (
                    <option key={e.value} value={e.value}>{e.label}</option>
                  ))}
                </Select>
              </Field>
            </div>

            <DialogFooter className="mt-4">
              <Button type="button" variant="secondary" size="sm" onClick={() => onOpenChange(false)}>
                Cancel
              </Button>
              <Button type="submit" size="sm" disabled={isSubmitting}>
                {isSubmitting && <Loader2 className="size-4 animate-spin" />}
                {isSubmitting ? submittingLabel : submitLabel}
              </Button>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  );
}

function Field({
  label,
  required,
  error,
  hint,
  children,
  className,
}: {
  label: string;
  required?: boolean;
  error?: string;
  hint?: string;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div className={`space-y-1 ${className ?? ''}`}>
      <Label className="flex items-center gap-1 text-[11.5px]">
        {label}
        {required && <span className="text-danger" aria-hidden>*</span>}
      </Label>
      {children}
      {error ? (
        <p role="alert" className="text-[11px] text-danger">{error}</p>
      ) : hint ? (
        <p className="text-[11px] text-fg-subtle">{hint}</p>
      ) : null}
    </div>
  );
}

// Suppress unused-warning when childServices/centres aren't used yet by some
// linters — they're consumed via watchedCity/watchedService memoization above.
void useMemo;
