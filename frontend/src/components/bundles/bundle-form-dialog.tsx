import { useEffect, useMemo } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useForm, useFieldArray, useWatch, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Loader2, Plus, Trash2 } from 'lucide-react';
import { api, ApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
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
 * Create/edit dialog for Bundles.
 *
 * Endpoints:
 *   • GET  /api/bundles/{id}/edit  — BundleFormDataResource passthrough:
 *        { bundle, services (catalog), bundle_services, relationships, tax_treatment_types }
 *     The `relationships` array is the source of truth for which services
 *     belong to the bundle and at what per-service price. `bundle_services`
 *     is a denormalised convenience collection we don't need.
 *   • POST /api/bundles                — create
 *   • PUT  /api/bundles/{id}           — update (server REPLACES all services)
 *
 * Price math (mirror of app/Models/Bundles::calculatePrices):
 *   If bundle_price < services_price:
 *     ratio = 1 - (bundle_price / services_price)
 *     calculated_price[i] = round(service_price[i] - service_price[i] * ratio, 2)
 *   If bundle_price == services_price: calculated = service_price (no discount)
 *   If bundle_price > services_price: inverse (premium, rare)
 * Computed client-side for live preview; server is the authority on save.
 */

type CatalogService = { id: number; name: string; price: number; end_node: number };
type Relationship = {
  service_id: number;
  service_price: number;
  calculated_price: number;
  end_node: number;
};
type TaxType = { id: number; name: string };

type FormDataResponse = {
  bundle: {
    id?: number;
    name?: string;
    price?: number | string;
    services_price?: number | string;
    apply_discount?: boolean | number;
    start?: string | null;
    end?: string | null;
    tax_treatment_type_id?: number | null;
  } | null;
  services: CatalogService[];
  relationships: Relationship[];
  tax_treatment_types: TaxType[];
};

const schema = z
  .object({
    name: z.string().min(1, 'Bundle name is required.').max(255),
    price: z.coerce.number({ invalid_type_error: 'Price must be a number.' }).min(0),
    start: z.string().optional().or(z.literal('')),
    end: z.string().optional().or(z.literal('')),
    apply_discount: z.boolean(),
    tax_treatment_type_id: z.coerce.number().int().optional(),
    services: z
      .array(
        z.object({
          service_id: z.coerce.number().int().positive('Pick a service.'),
          service_price: z.coerce.number().min(0),
        }),
      )
      .min(1, 'Add at least one service.'),
  })
  .superRefine((values, ctx) => {
    if (values.start && values.end && values.end < values.start) {
      ctx.addIssue({ code: 'custom', path: ['end'], message: 'End date must be on or after start date.' });
    }
  });

type FormValues = z.infer<typeof schema>;

interface Props {
  mode: 'create' | 'edit';
  bundleId?: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const EMPTY: FormValues = {
  name: '',
  price: 0,
  start: '',
  end: '',
  apply_discount: true,
  tax_treatment_type_id: undefined,
  services: [],
};

/** Proportional distribution — mirrors Bundles::calculatePrices exactly. */
function distributePrices(services: { service_price: number }[], bundlePrice: number): number[] {
  const servicesTotal = services.reduce((s, row) => s + (Number(row.service_price) || 0), 0);
  if (servicesTotal <= 0) return services.map(() => 0);
  if (servicesTotal === bundlePrice) return services.map((r) => Number(r.service_price) || 0);
  const ratio = 1 - Number((bundlePrice / servicesTotal).toFixed(8));
  return services.map((r) => {
    const sp = Number(r.service_price) || 0;
    // ratio > 0 ⇒ discount (bundle cheaper); ratio < 0 ⇒ premium.
    return Math.round((sp - sp * ratio) * 100) / 100;
  });
}

export function BundleFormDialog({ mode, bundleId, open, onOpenChange }: Props) {
  const qc = useQueryClient();

  const formData = useQuery({
    queryKey: ['bundles', 'form-data', mode, bundleId],
    // Create has no dedicated endpoint; reuse /create via a fresh edit-
    // style payload. We bootstrap from /api/bundles/create-form if it
    // exists; otherwise fall back to pulling the services catalog from
    // an edit call we won't use. The backend currently only exposes
    // GET /bundles/{id}/edit, so create mode has to source its catalog
    // differently — we call the services datatable directly.
    queryFn: async (): Promise<FormDataResponse> => {
      if (mode === 'edit' && bundleId) {
        return api.get<FormDataResponse>(`/api/bundles/${bundleId}/edit`);
      }
      // Create: grab the catalog + tax types by reading any existing
      // bundle's /edit payload. `services` and `tax_treatment_types`
      // are identical regardless of which bundle the id targets.
      // If there are no bundles yet we'll fall back to empty arrays.
      try {
        const probe = await api.post<{ data: { id: number }[] }>(
          '/api/bundles/datatable',
          { pagination: { page: 1, perpage: 1 } },
          { raw: true },
        );
        const seedId = probe.data?.[0]?.id;
        if (seedId) {
          const seed = await api.get<FormDataResponse>(`/api/bundles/${seedId}/edit`);
          return {
            bundle: null,
            services: seed.services,
            relationships: [],
            tax_treatment_types: seed.tax_treatment_types,
          };
        }
      } catch {
        // Fall through to empty scaffold.
      }
      return { bundle: null, services: [], relationships: [], tax_treatment_types: [] };
    },
    enabled: open,
  });

  const {
    register,
    handleSubmit,
    reset,
    control,
    watch,
    setError,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: EMPTY,
  });

  const { fields, append, remove } = useFieldArray({ control, name: 'services' });
  // `useWatch` subscribes reactively to every form change at the given
  // path, where plain `watch(...)` outside effects can snapshot a stale
  // value and miss updates driven by setValue / programmatic fills.
  const watchedServices = useWatch({ control, name: 'services' });
  const watchedPrice = useWatch({ control, name: 'price' });
  // `watch` retained for reset-time reads; satisfies the lint rule too.
  void watch;

  // Prefill on open / when data lands.
  useEffect(() => {
    if (!open || !formData.data) return;
    const b = formData.data.bundle;
    const defaultTax = formData.data.tax_treatment_types?.[0]?.id;

    if (mode === 'create' || !b) {
      reset({
        ...EMPTY,
        tax_treatment_type_id: defaultTax,
      });
      return;
    }

    const services = formData.data.relationships.map((r) => ({
      service_id: r.service_id,
      service_price: Number(r.service_price),
    }));

    reset({
      name: b.name ?? '',
      price: Number(b.price ?? 0),
      start: b.start ?? '',
      end: b.end ?? '',
      apply_discount:
        b.apply_discount === true || Number(b.apply_discount) === 1,
      tax_treatment_type_id: b.tax_treatment_type_id ?? defaultTax,
      services,
    });
  }, [open, mode, formData.data, reset]);

  // Live price preview.
  const preview = useMemo(() => {
    const bundlePrice = Number(watchedPrice) || 0;
    const rows = (watchedServices ?? []) as { service_id: number; service_price: number }[];
    const servicesTotal = rows.reduce((s, r) => s + (Number(r.service_price) || 0), 0);
    const calculated = distributePrices(rows, bundlePrice);
    const savings = Math.max(0, servicesTotal - bundlePrice);
    return { servicesTotal, calculated, savings, count: rows.length };
  }, [watchedServices, watchedPrice]);

  const mutation = useMutation({
    mutationFn: (values: FormValues) => {
      // Server expects parallel arrays `service_id[]` + `service_price[]`.
      const payload = {
        name: values.name,
        price: values.price,
        start: values.start || null,
        end: values.end || null,
        apply_discount: values.apply_discount ? 1 : 0,
        tax_treatment_type_id: values.tax_treatment_type_id ?? null,
        service_id: values.services.map((s) => s.service_id),
        service_price: values.services.map((s) => s.service_price),
      };
      return mode === 'create'
        ? api.post('/api/bundles', payload)
        : api.put(`/api/bundles/${bundleId}`, payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bundles', 'datatable'] });
      qc.invalidateQueries({ queryKey: ['bundles', 'form-data'] });
      onOpenChange(false);
    },
    onError: (err) => {
      if (err instanceof ApiError) {
        for (const [field, messages] of Object.entries(err.fieldErrors)) {
          // Map top-level backend errors onto the form. Array-indexed
          // errors (service_id.0, service_price.1) collapse to the
          // parent "services" field — sufficient for user feedback.
          const flat = field.split('.')[0];
          const knownFields: Array<keyof FormValues> = [
            'name',
            'price',
            'start',
            'end',
            'apply_discount',
            'tax_treatment_type_id',
          ];
          if ((knownFields as string[]).includes(flat)) {
            setError(flat as keyof FormValues, { message: (messages as string[])[0] });
          } else if (flat === 'service_id' || flat === 'service_price') {
            setError('services', { message: (messages as string[])[0] });
          }
        }
      }
    },
  });

  const onSubmit = handleSubmit((values) => mutation.mutate(values));

  const title = mode === 'create' ? 'Add bundle' : 'Edit bundle';
  const loading = formData.isLoading;

  const catalog = formData.data?.services ?? [];
  const taxTypes = formData.data?.tax_treatment_types ?? [];

  const addService = () => {
    append({ service_id: 0, service_price: 0 });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>

        <form onSubmit={onSubmit} className="space-y-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
              <Label htmlFor="bundle-name">
                Name <span className="text-danger">*</span>
              </Label>
              <Input id="bundle-name" autoFocus disabled={loading} {...register('name')} />
              {errors.name && (
                <p role="alert" className="mt-1 text-[12px] text-danger">
                  {errors.name.message}
                </p>
              )}
            </div>

            <div>
              <Label htmlFor="bundle-price">
                Bundle price <span className="text-danger">*</span>
              </Label>
              <Input
                id="bundle-price"
                type="number"
                step="0.01"
                min="0"
                disabled={loading}
                {...register('price')}
              />
              {errors.price && (
                <p role="alert" className="mt-1 text-[12px] text-danger">
                  {errors.price.message}
                </p>
              )}
            </div>

            <div>
              <Label>Tax treatment</Label>
              <div className="flex flex-wrap gap-4 pt-1 text-[13px]">
                {taxTypes.map((t) => (
                  <label key={t.id} className="inline-flex items-center gap-2">
                    <input
                      type="radio"
                      value={t.id}
                      disabled={loading}
                      {...register('tax_treatment_type_id')}
                    />
                    {t.name}
                  </label>
                ))}
              </div>
            </div>

            <div>
              <Label htmlFor="bundle-start">Starts</Label>
              <Input id="bundle-start" type="date" disabled={loading} {...register('start')} />
            </div>

            <div>
              <Label htmlFor="bundle-end">Ends</Label>
              <Input id="bundle-end" type="date" disabled={loading} {...register('end')} />
              {errors.end && (
                <p role="alert" className="mt-1 text-[12px] text-danger">
                  {errors.end.message}
                </p>
              )}
            </div>

            <div className="sm:col-span-2">
              <label className="inline-flex items-center gap-2 text-[13px]">
                <input
                  type="checkbox"
                  disabled={loading}
                  className="size-4 rounded border-hairline"
                  {...register('apply_discount')}
                />
                Apply discount rules to this bundle
              </label>
            </div>
          </div>

          <div className="rounded-lg ring-1 ring-inset ring-hairline">
            <div className="flex items-center justify-between gap-2 border-b border-hairline px-3 py-2">
              <div className="text-[12px] font-semibold uppercase tracking-wide text-fg-subtle">
                Services{' '}
                <span className="text-fg-muted">· {preview.count}</span>
              </div>
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={addService}
                disabled={loading}
              >
                <Plus className="size-3.5" /> Add service
              </Button>
            </div>

            {fields.length === 0 && (
              <div className="px-3 py-6 text-center text-[12.5px] text-fg-muted">
                Add at least one service to the bundle.
              </div>
            )}

            <div className="divide-y divide-hairline/60">
              {fields.map((field, idx) => {
                const calc = preview.calculated[idx] ?? 0;
                return (
                  <div
                    key={field.id}
                    className="grid grid-cols-[1fr_110px_110px_auto] items-center gap-2 px-3 py-2"
                  >
                    <Controller
                      control={control}
                      name={`services.${idx}.service_id`}
                      render={({ field: sf }) => (
                        <Select
                          aria-label={`Service ${idx + 1}`}
                          disabled={loading}
                          value={String(sf.value ?? 0)}
                          onChange={(e) => {
                            const id = Number(e.target.value);
                            sf.onChange(id);
                            // Auto-fill the service price from the
                            // catalog via RHF's setValue — mirrors the
                            // legacy UX. React's synthetic event tracker
                            // requires `setValue` rather than direct DOM
                            // input manipulation, or the watched state
                            // won't see the change.
                            const match = catalog.find((c) => c.id === id);
                            if (match) {
                              setValue(
                                `services.${idx}.service_price`,
                                Number(match.price) || 0,
                                { shouldDirty: true, shouldValidate: false },
                              );
                            }
                          }}
                        >
                          <option value="0">Choose service…</option>
                          {catalog.map((c) => (
                            <option key={c.id} value={c.id}>
                              {c.name}
                            </option>
                          ))}
                        </Select>
                      )}
                    />
                    <Input
                      id={`svc-price-${idx}`}
                      type="number"
                      step="0.01"
                      min="0"
                      aria-label={`Service ${idx + 1} price`}
                      disabled={loading}
                      {...register(`services.${idx}.service_price`)}
                    />
                    <div className="nums-tabular text-right text-[12.5px] text-fg-muted" aria-label={`Service ${idx + 1} calculated price`}>
                      {calc.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    </div>
                    <button
                      type="button"
                      aria-label={`Remove service ${idx + 1}`}
                      onClick={() => remove(idx)}
                      className="inline-flex size-7 items-center justify-center rounded-md text-fg-subtle transition-colors hover:bg-surface hover:text-danger focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
                    >
                      <Trash2 className="size-3.5" />
                    </button>
                  </div>
                );
              })}
            </div>

            {fields.length > 0 && (
              <div className="grid grid-cols-[1fr_110px_110px_auto] items-center gap-2 border-t border-hairline bg-surface px-3 py-2 text-[12.5px]">
                <div className="text-fg-muted">
                  {preview.count} service{preview.count === 1 ? '' : 's'}
                </div>
                <div className="text-right nums-tabular text-fg-subtle line-through">
                  {preview.servicesTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </div>
                <div
                  className={cn(
                    'text-right nums-tabular font-semibold',
                    preview.savings > 0 ? 'text-success' : 'text-fg',
                  )}
                  aria-label="Bundle total"
                >
                  {(Number(watchedPrice) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                </div>
                <div className="w-7" />
              </div>
            )}

            {errors.services && (
              <p role="alert" className="border-t border-hairline bg-danger-soft px-3 py-1.5 text-[12px] text-danger">
                {(errors.services as unknown as { message?: string }).message ?? 'Check the services list.'}
              </p>
            )}
          </div>

          {mutation.error && (
            <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
              {(mutation.error as Error).message}
            </div>
          )}

          <DialogFooter>
            <Button type="button" variant="secondary" size="sm" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="submit" size="sm" disabled={isSubmitting || mutation.isPending || loading}>
              {(isSubmitting || mutation.isPending) && <Loader2 className="size-3.5 animate-spin" />}
              {mode === 'create' ? 'Create' : 'Save changes'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
