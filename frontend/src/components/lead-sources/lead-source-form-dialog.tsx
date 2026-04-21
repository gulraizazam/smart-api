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

const schema = z.object({
  name: z.string().min(1, 'Lead source name is required').max(255),
  active: z.enum(['0', '1']),
});

type FormValues = z.infer<typeof schema>;

type LeadSource = {
  id: number;
  name: string;
  sort_no: number;
  active: boolean | number;
};

interface Props {
  mode: 'create' | 'edit';
  sourceId?: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function LeadSourceFormDialog({ mode, sourceId, open, onOpenChange }: Props) {
  const qc = useQueryClient();

  const editData = useQuery({
    queryKey: ['lead-sources', 'edit', sourceId],
    queryFn: () => api.get<LeadSource>(`/api/lead_sources/${sourceId}/edit`),
    enabled: open && mode === 'edit' && !!sourceId,
  });

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: '', active: '1' },
  });

  useEffect(() => {
    if (!open) return;
    if (mode === 'create') {
      reset({ name: '', active: '1' });
    } else if (editData.data) {
      reset({
        name: editData.data.name ?? '',
        active: editData.data.active ? '1' : '0',
      });
    }
  }, [open, mode, editData.data, reset]);

  const mutation = useMutation({
    mutationFn: (values: FormValues) =>
      mode === 'create'
        ? api.post('/api/lead_sources', values)
        : api.put(`/api/lead_sources/${sourceId}`, values),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['lead-sources', 'datatable'] });
      qc.invalidateQueries({ queryKey: ['lead-sources', 'edit'] });
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

  const title = mode === 'create' ? 'Add lead source' : 'Edit lead source';
  const loading = mode === 'edit' && editData.isLoading;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>
        <form onSubmit={onSubmit} className="space-y-4">
          <div>
            <Label htmlFor="ls-name">
              Name <span className="text-danger">*</span>
            </Label>
            <Input id="ls-name" autoFocus disabled={loading} {...register('name')} />
            {errors.name && (
              <p role="alert" className="mt-1 text-[12px] text-danger">
                {errors.name.message}
              </p>
            )}
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
