import { useEffect } from 'react';
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
 * Unified create + edit form for lead statuses.
 *
 * Fields: name, parent_id, is_default, is_booked, is_arrived, is_converted,
 * is_junk, is_comment, active. All flags map to the backend's "unique flag"
 * set — the service automatically resets the same flag on all other rows
 * when this one is set to 1, so the form doesn't need to warn about that.
 *
 * Endpoints:
 *   • GET  /api/lead_statuses/create          — { parentLeadStatuses, lead_status }
 *   • GET  /api/lead_statuses/{id}/edit       — { lead_statuse, parentLeadStatuses }
 *     (uses LeadStatusFormResource → raw integer/boolean values)
 *   • POST /api/lead_statuses                 — create
 *   • PUT  /api/lead_statuses/{id}            — update
 */

const schema = z.object({
  name: z.string().min(1, 'Lead status name is required').max(255),
  parent_id: z.string().optional(),
  is_default: z.enum(['0', '1']),
  is_booked: z.enum(['0', '1']),
  is_arrived: z.enum(['0', '1']),
  is_converted: z.enum(['0', '1']),
  is_junk: z.enum(['0', '1']),
  is_comment: z.boolean(),
  active: z.enum(['0', '1']),
});

type FormValues = z.infer<typeof schema>;

type CreateResponse = {
  parentLeadStatuses: Record<string, string>;
  lead_status: {
    is_default: number;
    is_arrived: number;
    is_converted: number;
    is_junk: number;
  };
};

type EditResponse = {
  lead_statuse: {
    id: number;
    name: string;
    parent_id: number;
    is_comment: number;
    is_default: number;
    is_booked: number;
    is_arrived: number;
    is_converted: number;
    is_junk: number;
    active: number;
  };
  parentLeadStatuses: Record<string, string>;
};

interface Props {
  mode: 'create' | 'edit';
  statusId?: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const EMPTY_DEFAULTS: FormValues = {
  name: '',
  parent_id: '',
  is_default: '0',
  is_booked: '0',
  is_arrived: '0',
  is_converted: '0',
  is_junk: '0',
  is_comment: false,
  active: '1',
};

export function LeadStatusFormDialog({ mode, statusId, open, onOpenChange }: Props) {
  const qc = useQueryClient();

  const createData = useQuery({
    queryKey: ['lead-statuses', 'create'],
    queryFn: () => api.get<CreateResponse>('/api/lead_statuses/create'),
    enabled: open && mode === 'create',
    staleTime: 5 * 60_000,
  });

  const editData = useQuery({
    queryKey: ['lead-statuses', 'edit', statusId],
    queryFn: () => api.get<EditResponse>(`/api/lead_statuses/${statusId}/edit`),
    enabled: open && mode === 'edit' && !!statusId,
  });

  const parentStatuses =
    mode === 'create' ? createData.data?.parentLeadStatuses : editData.data?.parentLeadStatuses;

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: EMPTY_DEFAULTS,
  });

  useEffect(() => {
    if (!open) return;
    if (mode === 'create') {
      reset(EMPTY_DEFAULTS);
    } else if (editData.data) {
      const s = editData.data.lead_statuse;
      reset({
        name: s.name ?? '',
        parent_id: s.parent_id ? String(s.parent_id) : '',
        is_default: s.is_default ? '1' : '0',
        is_booked: s.is_booked ? '1' : '0',
        is_arrived: s.is_arrived ? '1' : '0',
        is_converted: s.is_converted ? '1' : '0',
        is_junk: s.is_junk ? '1' : '0',
        is_comment: !!s.is_comment,
        active: s.active ? '1' : '0',
      });
    }
  }, [open, mode, editData.data, reset]);

  const mutation = useMutation({
    mutationFn: (values: FormValues) => {
      const payload = {
        name: values.name,
        parent_id: values.parent_id ? Number(values.parent_id) : 0,
        is_default: values.is_default,
        is_booked: values.is_booked,
        is_arrived: values.is_arrived,
        is_converted: values.is_converted,
        is_junk: values.is_junk,
        is_comment: values.is_comment ? 1 : 0,
        active: values.active,
      };
      return mode === 'create'
        ? api.post('/api/lead_statuses', payload)
        : api.put(`/api/lead_statuses/${statusId}`, payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['lead-statuses', 'datatable'] });
      qc.invalidateQueries({ queryKey: ['lead-statuses', 'edit'] });
      onOpenChange(false);
    },
    onError: (err) => {
      if (err instanceof ApiError) {
        for (const [field, messages] of Object.entries(err.fieldErrors)) {
          if (field in schema.shape) {
            setError(field as keyof FormValues, { message: messages[0] });
          }
        }
      }
    },
  });

  const onSubmit = handleSubmit((values) => mutation.mutate(values));

  const title = mode === 'create' ? 'Add lead status' : 'Edit lead status';
  const loading =
    (mode === 'create' && createData.isLoading) || (mode === 'edit' && editData.isLoading);

  const parentOptions = parentStatuses
    ? Object.entries(parentStatuses).filter(([id]) => id !== '')
    : [];

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>
        <form onSubmit={onSubmit} className="space-y-4">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <Label htmlFor="lst-name">
                Name <span className="text-danger">*</span>
              </Label>
              <Input id="lst-name" autoFocus disabled={loading} {...register('name')} />
              {errors.name && (
                <p role="alert" className="mt-1 text-[12px] text-danger">
                  {errors.name.message}
                </p>
              )}
            </div>

            <div>
              <Label htmlFor="lst-parent">Parent</Label>
              <Select id="lst-parent" disabled={loading} {...register('parent_id')}>
                <option value="">None</option>
                {parentOptions.map(([id, name]) => (
                  <option key={id} value={id}>
                    {name}
                  </option>
                ))}
              </Select>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <FlagField label="Default for Open leads" name="is_default" register={register} disabled={loading} />
            <FlagField label="Default for Booked leads" name="is_booked" register={register} disabled={loading} />
            <FlagField label="Default for Arrived leads" name="is_arrived" register={register} disabled={loading} />
            <FlagField label="Default for Converted leads" name="is_converted" register={register} disabled={loading} />
            <FlagField label="Default for Junk leads" name="is_junk" register={register} disabled={loading} />

            <div className="flex items-center gap-2 pt-6">
              <input
                id="lst-is-comment"
                type="checkbox"
                disabled={loading}
                className="size-4 rounded border-hairline"
                {...register('is_comment')}
              />
              <Label htmlFor="lst-is-comment" className="cursor-pointer text-[13px] font-normal">
                Ask for comments
              </Label>
            </div>
          </div>

          <div>
            <Label>Status</Label>
            <div className="flex gap-4 pt-1 text-[13px]">
              <label className="inline-flex items-center gap-2">
                <input type="radio" value="1" disabled={loading} {...register('active')} />
                Active
              </label>
              <label className="inline-flex items-center gap-2">
                <input type="radio" value="0" disabled={loading} {...register('active')} />
                Inactive
              </label>
            </div>
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

function FlagField({
  label,
  name,
  register,
  disabled,
}: {
  label: string;
  name: 'is_default' | 'is_booked' | 'is_arrived' | 'is_converted' | 'is_junk';
  register: ReturnType<typeof useForm<FormValues>>['register'];
  disabled?: boolean;
}) {
  return (
    <div>
      <Label className="text-[12px]">{label}</Label>
      <div className="flex gap-3 pt-1 text-[13px]">
        <label className="inline-flex items-center gap-1.5">
          <input type="radio" value="1" disabled={disabled} {...register(name)} />
          Yes
        </label>
        <label className="inline-flex items-center gap-1.5">
          <input type="radio" value="0" disabled={disabled} {...register(name)} />
          No
        </label>
      </div>
    </div>
  );
}
