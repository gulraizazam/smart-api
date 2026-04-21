import { useEffect } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Loader2, Info } from 'lucide-react';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
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
import type { PatientDetail } from './types';

/**
 * Patient create + edit form. Mirrors the legacy admin's modal:
 *   resources/views/admin/patients/{create,edit}.blade.php
 *
 * Server contract — POST /api/patients or PUT /api/patients/{id}:
 *   { name*, phone*, gender*, email?, dob?, address?, cnic?, old_phone? }
 *
 * Behaviour quirks honoured:
 *   • Create with a duplicate phone silently UPSERTS the existing patient
 *     (PatientService::create line 282). We surface a hint so the user
 *     understands they're not adding a fresh row.
 *   • Edit cascades the new name/phone/gender to all related Appointments
 *     and Leads (PatientService::update line 323-330). Edit form shows a
 *     subtle warning so users know the change isn't local.
 */

const schema = z.object({
  name: z.string().min(1, 'Name is required').max(255),
  phone: z.string().min(10, 'Phone must be at least 10 digits').max(20),
  gender: z.enum(['1', '2'], { errorMap: () => ({ message: 'Pick a gender' }) }),
  email: z.string().email('Invalid email').or(z.literal('')).optional(),
  dob: z.string().optional(),
  cnic: z.string().max(20).optional(),
});

type FormValues = z.infer<typeof schema>;

type Mode = 'create' | 'edit';

interface PatientFormDialogProps {
  mode: Mode;
  /** Required when mode === 'edit' */
  patientId?: number | string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Optional callback after successful create/edit (for nav transitions) */
  onSaved?: (patientId: number) => void;
}

export function PatientFormDialog({ mode, patientId, open, onOpenChange, onSaved }: PatientFormDialogProps) {
  const qc = useQueryClient();
  const { user } = useAuth();
  // Mirrors legacy `@can('contact')` gating around the phone field in
  // resources/views/admin/patients/edit.blade.php. Users without the
  // `contact` permission can't read or modify a patient's phone number.
  // Create stays editable so a new patient can be added at all (the field
  // is required server-side); edit hides the input but keeps the existing
  // value in the submit body so the row's phone isn't wiped on save.
  const canSeePhone = !!user?.permissions?.includes('contact');
  const phoneEditable = mode === 'create' || canSeePhone;

  const detail = useQuery({
    queryKey: ['patients', 'detail', patientId],
    queryFn: () => api.get<{ patient: PatientDetail }>(`/api/patients/${patientId}`),
    enabled: open && mode === 'edit' && patientId != null,
  });

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting, dirtyFields },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { gender: '1' as const },
  });

  useEffect(() => {
    if (!open) return;
    if (mode === 'create') {
      reset({ gender: '1' as const });
    } else if (detail.data?.patient) {
      const p = detail.data.patient;
      reset({
        name: p.name ?? '',
        phone: p.phone ?? '',
        email: p.email ?? '',
        gender: (p.gender === 2 ? '2' : '1') as '1' | '2',
        dob: p.dob ?? '',
        cnic: p.cnic ?? '',
      });
    }
  }, [open, mode, detail.data, reset]);

  const submit = useMutation({
    mutationFn: (values: FormValues) => {
      const body: Record<string, unknown> = {
        name: values.name,
        phone: values.phone,
        // PatientRequest expects `required|string|in:1,2` — send the zod
        // value verbatim ('1' or '2'). Casting to Number here was the
        // "gender must be a string" 422 bug.
        gender: values.gender,
        email: values.email || undefined,
        dob: values.dob || undefined,
        cnic: values.cnic || undefined,
      };
      // Preserve the old phone so the server can run its dedupe / cascade
      // logic correctly (PatientService line 302-307, 326-330).
      if (mode === 'edit' && detail.data?.patient.phone) {
        body.old_phone = detail.data.patient.phone;
      }
      return mode === 'create'
        ? api.post<{ id?: number }>('/api/patients', body)
        : api.put(`/api/patients/${patientId}`, body);
    },
    onSuccess: (data) => {
      qc.invalidateQueries({ queryKey: ['patients', 'datatable'] });
      if (mode === 'edit' && patientId != null) {
        qc.invalidateQueries({ queryKey: ['patients', 'detail', patientId] });
      }
      onOpenChange(false);
      const newId = (data as { id?: number } | null)?.id;
      if (newId && onSaved) onSaved(newId);
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

  const isLoading = mode === 'edit' && detail.isLoading;
  const title = mode === 'create' ? 'Add a new patient' : 'Edit patient';
  const submitLabel = mode === 'create' ? 'Create patient' : 'Save changes';
  const submittingLabel = mode === 'create' ? 'Creating…' : 'Saving…';

  // Surface the cascading-update warning only when the user actually
  // changed name / phone / gender on edit — those are the fields that
  // cascade to appointments + leads server-side.
  const cascadeWarn =
    mode === 'edit' && (dirtyFields.name || dirtyFields.phone || dirtyFields.gender);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto p-5">
        <DialogHeader className="mb-3">
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>

        {isLoading ? (
          <div className="flex items-center justify-center py-10 text-fg-muted text-[13px]">
            <Loader2 className="size-4 animate-spin mr-2" /> Loading patient…
          </div>
        ) : (
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-3" noValidate>
            {submit.error && !(submit.error instanceof ApiError && submit.error.status === 422) && (
              <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
                {(submit.error as Error).message}
              </div>
            )}

            {mode === 'create' && (
              <div className="flex gap-2 rounded-lg bg-info-soft px-3 py-2 text-[11.5px] text-info ring-1 ring-inset ring-info/20">
                <Info className="size-3.5 shrink-0 mt-0.5" />
                <span>If a patient with this phone already exists, the form will <strong>update that record</strong> instead of creating a duplicate.</span>
              </div>
            )}

            {cascadeWarn && (
              <div className="flex gap-2 rounded-lg bg-warning-soft px-3 py-2 text-[11.5px] text-warning ring-1 ring-inset ring-warning/20">
                <Info className="size-3.5 shrink-0 mt-0.5" />
                <span>Changing name / phone / gender will sync to <strong>all this patient's appointments and leads</strong>.</span>
              </div>
            )}

            <div className="grid grid-cols-1 gap-x-3 gap-y-2 sm:grid-cols-2">
              <Field label="Full name" required error={errors.name?.message}>
                <Input {...register('name')} placeholder="e.g. Sara Khan" autoComplete="name" className="h-9" />
              </Field>
              {phoneEditable ? (
                <Field label="Phone" required error={errors.phone?.message}>
                  <Input {...register('phone')} placeholder="0314 115 0209" inputMode="tel" autoComplete="tel" className="h-9" />
                </Field>
              ) : (
                <Field label="Phone">
                  {/* Hidden input keeps the existing value in the submit
                      body so the save doesn't wipe the row's phone. */}
                  <input type="hidden" {...register('phone')} />
                  <div className="flex h-9 items-center rounded-md border border-hairline bg-elevated px-3 text-[12.5px] italic text-fg-subtle">
                    Hidden — requires the <code className="px-1">contact</code> permission to view or edit.
                  </div>
                </Field>
              )}
              <Field label="Gender" required error={errors.gender?.message}>
                <Select {...register('gender')} className="h-9">
                  <option value="1">Male</option>
                  <option value="2">Female</option>
                </Select>
              </Field>
              <Field label="Email" error={errors.email?.message}>
                <Input {...register('email')} type="email" placeholder="optional" autoComplete="email" className="h-9" />
              </Field>
              <Field label="Date of birth" error={errors.dob?.message}>
                <Input {...register('dob')} type="date" className="h-9" />
              </Field>
              <Field label="CNIC" error={errors.cnic?.message}>
                <Input {...register('cnic')} placeholder="xxxxx-xxxxxxx-x" className="h-9" />
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
  children,
  className,
}: {
  label: string;
  required?: boolean;
  error?: string;
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
      {error && <p role="alert" className="text-[11px] text-danger">{error}</p>}
    </div>
  );
}
