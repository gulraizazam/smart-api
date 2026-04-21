import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Controller, useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { AlertTriangle, ArrowLeft, Loader2 } from 'lucide-react';
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
import { Badge } from '@/components/ui/badge';
import { RichTextEditor } from './rich-text-editor';

/**
 * Unified create / edit / duplicate dialog for Services.
 *
 * Endpoints:
 *   • GET  /api/services/create              — lookups + empty service stub
 *   • GET  /api/services/{id}/edit           — lookups + service prefill
 *   • GET  /api/services/{id}/duplicate      — same shape as edit; used by duplicate flow
 *   • POST /api/services                     — create
 *   • POST /api/services/duplicate           — duplicate (server decides how to seed name)
 *   • PUT  /api/services/{id}                — update
 *   • GET  /api/services/{id}/bundle-impact?new_price=X — preview bundle reprice
 *
 * Parent vs child convention:
 *   parent_id === "0" (string form value) → this row IS a parent category.
 *   Duration, price, description, end_node, complimentory, tax fields hide.
 *   parent_id > 0                         → this row is a child under that parent.
 *   All child-only fields are required by legacy UX (and numeric/regex by the server).
 */

type ParentOption = { id: number; name: string; color: string };
type TaxType = { id: number; name: string };

type FormDataResponse = {
  parent_services: ParentOption[];
  service: {
    id?: number;
    name?: string;
    parent_id?: number | null;
    duration?: string | null;
    price?: number | string | null;
    color?: string | null;
    description?: string | null;
    end_node?: number | boolean | null;
    complimentory?: number | boolean | null;
    tax_treatment_type_id?: number | null;
  };
  durations: string[];
  tax_treatment_types: TaxType[];
  select_tax_treatment_type: number;
};

type BundleImpactRow = {
  service_bundle_id: number;
  sessions: number;
  discount_percentage: number;
  old_bundle_price: number;
  new_bundle_price: number;
};

const baseSchema = z.object({
  name: z.string().min(1, 'Service name is required').max(255),
  parent_id: z.string().min(1, 'Please select a parent service or choose "Parent Service".'),
  duration: z
    .string()
    .regex(/^\d{2}:\d{2}$/, 'Duration must be in HH:MM format.')
    .optional()
    .or(z.literal('')),
  price: z.coerce.number({ invalid_type_error: 'Price must be a number.' }).min(0, 'Price cannot be negative.').optional(),
  color: z.string().max(20).optional().or(z.literal('')),
  description: z.string().max(5000, 'Description is too long.').optional().or(z.literal('')),
  tax_treatment_type_id: z.coerce.number().int().optional(),
  end_node: z.boolean(),
  complimentory: z.boolean(),
});

const schema = baseSchema.superRefine((values, ctx) => {
    // Child services require duration + price + color (legacy UX).
    if (values.parent_id && values.parent_id !== '0') {
      if (!values.duration) {
        ctx.addIssue({ code: 'custom', path: ['duration'], message: 'Duration is required for child services.' });
      }
      if (values.price === undefined || values.price === null || Number.isNaN(values.price)) {
        ctx.addIssue({ code: 'custom', path: ['price'], message: 'Price is required for child services.' });
      }
    }
  });

type FormValues = z.infer<typeof schema>;

interface Props {
  mode: 'create' | 'edit' | 'duplicate';
  serviceId?: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const EMPTY: FormValues = {
  name: '',
  parent_id: '',
  duration: '',
  price: undefined,
  color: '#000000',
  description: '',
  tax_treatment_type_id: 3,
  end_node: false,
  complimentory: false,
};

export function ServiceFormDialog({ mode, serviceId, open, onOpenChange }: Props) {
  const qc = useQueryClient();
  const [impactRows, setImpactRows] = useState<BundleImpactRow[] | null>(null);
  const [pendingPayload, setPendingPayload] = useState<FormValues | null>(null);

  const endpoint =
    mode === 'create'
      ? '/api/services/create'
      : mode === 'duplicate'
        ? `/api/services/${serviceId}/duplicate`
        : `/api/services/${serviceId}/edit`;

  const formData = useQuery({
    queryKey: ['services', 'form-data', mode, serviceId],
    queryFn: () => api.get<FormDataResponse>(endpoint),
    enabled: open && (mode === 'create' || !!serviceId),
  });

  const {
    register,
    handleSubmit,
    reset,
    setValue,
    setError,
    control,
    formState: { errors, dirtyFields },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: EMPTY,
  });

  const parentIdWatch = useWatch({ control, name: 'parent_id' });
  const priceWatch = useWatch({ control, name: 'price' });
  const isParent = parentIdWatch === '0';

  // Seed the form when lookups land / mode changes.
  useEffect(() => {
    if (!open || !formData.data) return;
    const s = formData.data.service;
    const defaultTax = formData.data.select_tax_treatment_type ?? 3;

    if (mode === 'create') {
      reset({
        ...EMPTY,
        tax_treatment_type_id: defaultTax,
      });
      return;
    }

    reset({
      name: s.name ?? '',
      parent_id: s.parent_id == null ? '0' : String(s.parent_id),
      duration: s.duration ?? '',
      price: s.price === null || s.price === undefined || s.price === ''
        ? undefined
        : Number(s.price),
      color: s.color ?? '#000000',
      description: s.description ?? '',
      tax_treatment_type_id: s.tax_treatment_type_id ?? defaultTax,
      end_node: !!s.end_node,
      complimentory: !!s.complimentory,
    });
  }, [open, mode, formData.data, reset]);

  // Color auto-fill when the parent changes to a real parent (create mode
  // always; edit/duplicate only when user actively picks a different parent).
  useEffect(() => {
    if (!formData.data || !parentIdWatch || parentIdWatch === '0') return;
    const parent = formData.data.parent_services.find((p) => String(p.id) === parentIdWatch);
    if (!parent) return;
    // Only overwrite if in create mode or if color hasn't been user-edited
    // (detected via dirtyFields — if they already touched color, respect it).
    if (mode === 'create' || !dirtyFields.color) {
      setValue('color', parent.color, { shouldDirty: false });
    }
  }, [parentIdWatch, formData.data, mode, dirtyFields.color, setValue]);

  const mutation = useMutation({
    mutationFn: async (values: FormValues) => {
      const isRoot = values.parent_id === '0';
      const payload: Record<string, unknown> = {
        name: values.name,
        parent_id: Number(values.parent_id),
        color: values.color || null,
      };
      if (!isRoot) {
        payload.duration = values.duration || null;
        payload.price = values.price ?? 0;
        payload.description = values.description || null;
        payload.tax_treatment_type_id = values.tax_treatment_type_id ?? null;
        payload.end_node = values.end_node;
        payload.complimentory = values.complimentory;
      }

      if (mode === 'create') return api.post('/api/services', payload);
      if (mode === 'duplicate') {
        return api.post('/api/services/duplicate', { ...payload, source_id: serviceId });
      }
      return api.put(`/api/services/${serviceId}`, payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['services', 'datatable'] });
      qc.invalidateQueries({ queryKey: ['services', 'form-data'] });
      setImpactRows(null);
      setPendingPayload(null);
      onOpenChange(false);
    },
    onError: (err) => {
      if (err instanceof ApiError) {
        for (const [field, messages] of Object.entries(err.fieldErrors)) {
          if (field in baseSchema.shape) {
            setError(field as keyof FormValues, { message: (messages as string[])[0] });
          }
        }
      }
    },
  });

  const onSubmit = handleSubmit(async (values) => {
    // Bundle-impact preview only applies on edit mode, when the price
    // field is actually dirty, and when the service already has bundles.
    // An empty preview (no bundles, or same price) skips the gate.
    const shouldPreview =
      mode === 'edit' &&
      !!serviceId &&
      !!dirtyFields.price &&
      values.parent_id !== '0' &&
      values.price !== undefined;

    if (shouldPreview) {
      try {
        const res = await api.get<{ affected: BundleImpactRow[] }>(
          `/api/services/${serviceId}/bundle-impact?new_price=${encodeURIComponent(String(values.price))}`,
        );
        const affected = res?.affected ?? [];
        if (affected.length > 0) {
          setImpactRows(affected);
          setPendingPayload(values);
          return;
        }
      } catch {
        // Preview fetch failed — fall through and let the server decide on save.
      }
    }
    mutation.mutate(values);
  });

  const confirmImpact = () => {
    if (pendingPayload) mutation.mutate(pendingPayload);
  };

  const cancelImpact = () => {
    setImpactRows(null);
    setPendingPayload(null);
  };

  const parentOptions = formData.data?.parent_services ?? [];
  const durations = formData.data?.durations ?? [];
  const taxTypes = formData.data?.tax_treatment_types ?? [];
  const loading = formData.isLoading;

  const title = useMemo(() => {
    if (mode === 'create') return 'Add service';
    if (mode === 'duplicate') return 'Duplicate service';
    return 'Edit service';
  }, [mode]);

  return (
    <Dialog
      open={open}
      onOpenChange={(o) => {
        if (!o) {
          cancelImpact();
        }
        onOpenChange(o);
      }}
    >
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>

        {impactRows ? (
          <BundleImpactStep
            rows={impactRows}
            oldPrice={Number(formData.data?.service.price ?? 0)}
            newPrice={Number(priceWatch ?? 0)}
            onBack={cancelImpact}
            onConfirm={confirmImpact}
            busy={mutation.isPending}
            error={mutation.error instanceof Error ? mutation.error.message : null}
          />
        ) : (
          <form onSubmit={onSubmit} className="space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div className="sm:col-span-2">
                <Label htmlFor="svc-name">
                  Name <span className="text-danger">*</span>
                </Label>
                <Input id="svc-name" autoFocus disabled={loading} {...register('name')} />
                {errors.name && (
                  <p role="alert" className="mt-1 text-[12px] text-danger">
                    {errors.name.message}
                  </p>
                )}
              </div>

              <div>
                <Label htmlFor="svc-parent">
                  Parent <span className="text-danger">*</span>
                </Label>
                <Select id="svc-parent" disabled={loading} {...register('parent_id')}>
                  <option value="">Choose…</option>
                  <option value="0">— Parent service (category) —</option>
                  {parentOptions.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.name}
                    </option>
                  ))}
                </Select>
                {errors.parent_id && (
                  <p role="alert" className="mt-1 text-[12px] text-danger">
                    {errors.parent_id.message}
                  </p>
                )}
              </div>

              <div>
                <Label htmlFor="svc-color">Color</Label>
                <div className="flex items-center gap-2">
                  <input
                    id="svc-color"
                    type="color"
                    disabled={loading}
                    className="h-10 w-14 cursor-pointer rounded-lg border border-hairline bg-elevated"
                    {...register('color')}
                  />
                  <Input
                    disabled={loading}
                    placeholder="#000000"
                    className="flex-1"
                    {...register('color')}
                  />
                </div>
              </div>
            </div>

            {!isParent && parentIdWatch && (
              <>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div>
                    <Label htmlFor="svc-duration">
                      Duration <span className="text-danger">*</span>
                    </Label>
                    <Select id="svc-duration" disabled={loading} {...register('duration')}>
                      <option value="">Choose…</option>
                      {durations.map((d) => (
                        <option key={d} value={d}>
                          {d}
                        </option>
                      ))}
                    </Select>
                    {errors.duration && (
                      <p role="alert" className="mt-1 text-[12px] text-danger">
                        {errors.duration.message}
                      </p>
                    )}
                  </div>

                  <div>
                    <Label htmlFor="svc-price">
                      Price <span className="text-danger">*</span>
                    </Label>
                    <Input
                      id="svc-price"
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
                </div>

                <div>
                  <Label>Description</Label>
                  <Controller
                    control={control}
                    name="description"
                    render={({ field }) => (
                      <RichTextEditor
                        value={field.value ?? ''}
                        onChange={field.onChange}
                        disabled={loading}
                      />
                    )}
                  />
                  {errors.description && (
                    <p role="alert" className="mt-1 text-[12px] text-danger">
                      {errors.description.message}
                    </p>
                  )}
                </div>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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

                  <div className="flex flex-col gap-2 pt-6 text-[13px]">
                    <label className="inline-flex items-center gap-2">
                      <input
                        type="checkbox"
                        disabled={loading}
                        className="size-4 rounded border-hairline"
                        {...register('end_node')}
                      />
                      End node (no further sub-services)
                    </label>
                    <label className="inline-flex items-center gap-2">
                      <input
                        type="checkbox"
                        disabled={loading}
                        className="size-4 rounded border-hairline"
                        {...register('complimentory')}
                      />
                      Complimentary
                    </label>
                  </div>
                </div>
              </>
            )}

            {mutation.error && !impactRows && (
              <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
                {(mutation.error as Error).message}
              </div>
            )}

            <DialogFooter>
              <Button type="button" variant="secondary" size="sm" onClick={() => onOpenChange(false)}>
                Cancel
              </Button>
              <Button type="submit" size="sm" disabled={mutation.isPending || loading}>
                {mutation.isPending && <Loader2 className="size-3.5 animate-spin" />}
                {mode === 'create' || mode === 'duplicate' ? 'Create' : 'Save changes'}
              </Button>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  );
}

function BundleImpactStep({
  rows,
  oldPrice,
  newPrice,
  onBack,
  onConfirm,
  busy,
  error,
}: {
  rows: BundleImpactRow[];
  oldPrice: number;
  newPrice: number;
  onBack: () => void;
  onConfirm: () => void;
  busy: boolean;
  error: string | null;
}) {
  return (
    <div className="space-y-4">
      <div className="flex items-start gap-3 rounded-lg bg-warning-soft p-3 text-[13px] text-warning ring-1 ring-inset ring-warning/20">
        <AlertTriangle className="mt-0.5 size-4 shrink-0" />
        <div>
          Changing the price from <span className="font-semibold">{oldPrice.toLocaleString()}</span> to{' '}
          <span className="font-semibold">{newPrice.toLocaleString()}</span> will re-price{' '}
          <span className="font-semibold">{rows.length}</span> service bundle{rows.length === 1 ? '' : 's'}.
          Past-sold packages are unaffected; future sales use the new prices.
        </div>
      </div>

      <div className="max-h-[320px] overflow-auto rounded-lg ring-1 ring-inset ring-hairline">
        <table className="w-full text-[12.5px]">
          <thead className="bg-surface text-fg-subtle">
            <tr className="text-left">
              <th className="px-3 py-2 font-semibold">Sessions</th>
              <th className="px-3 py-2 font-semibold">Discount</th>
              <th className="px-3 py-2 font-semibold text-right">Old price</th>
              <th className="px-3 py-2 font-semibold text-right">New price</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-hairline/60">
            {rows.map((r) => (
              <tr key={r.service_bundle_id}>
                <td className="px-3 py-2">{r.sessions}</td>
                <td className="px-3 py-2">
                  {r.discount_percentage ? `${r.discount_percentage}%` : '—'}
                </td>
                <td className="px-3 py-2 text-right nums-tabular">{r.old_bundle_price.toLocaleString()}</td>
                <td className="px-3 py-2 text-right nums-tabular font-medium text-fg">
                  {r.new_bundle_price.toLocaleString()}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {error && (
        <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
          {error}
        </div>
      )}

      <DialogFooter>
        <Button type="button" variant="secondary" size="sm" onClick={onBack} disabled={busy}>
          <ArrowLeft className="size-3.5" /> Back
        </Button>
        <Button type="button" size="sm" onClick={onConfirm} disabled={busy}>
          {busy && <Loader2 className="size-3.5 animate-spin" />}
          Confirm & save
        </Button>
      </DialogFooter>
    </div>
  );
}

export function makeBadge(label: string, tone: 'brand' | 'success' | 'warning' | 'danger' | 'neutral') {
  return <Badge variant={tone}>{label}</Badge>;
}
