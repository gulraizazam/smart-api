import { useQuery } from '@tanstack/react-query';
import { Loader2 } from 'lucide-react';
import { api, ApiError } from '@/lib/api';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

type Props = {
  serviceId: number | null;
  serviceName: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function ServiceInstructionsDialog({ serviceId, serviceName, open, onOpenChange }: Props) {
  const detail = useQuery({
    queryKey: ['services', 'detail', serviceId],
    queryFn: () => api.get<{ description: string | null }>(`/api/services/${serviceId}`),
    enabled: open && serviceId !== null,
    staleTime: 30_000,
  });

  const description = detail.data?.description?.trim() ?? '';

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle className="truncate">
            Service instructions{serviceName ? ` — ${serviceName}` : ''}
          </DialogTitle>
          <DialogDescription>
            Usage, prep, and after-care notes configured on this service.
          </DialogDescription>
        </DialogHeader>

        <div className="max-h-[60vh] overflow-y-auto rounded-lg bg-surface p-4 ring-1 ring-inset ring-hairline">
          {detail.isLoading && (
            <div className="flex items-center justify-center py-10 text-fg-subtle">
              <Loader2 className="mr-2 size-4 animate-spin" />
              <span className="text-[12.5px]">Loading…</span>
            </div>
          )}

          {detail.error && (
            <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
              {(detail.error as ApiError).message}
            </div>
          )}

          {!detail.isLoading && !detail.error && description === '' && (
            <p className="py-6 text-center text-[12.5px] text-fg-subtle">
              No instructions configured for this service.
            </p>
          )}

          {!detail.isLoading && !detail.error && description !== '' && (
            <div
              className="prose prose-sm max-w-none break-words text-fg [&_img]:max-w-full [&_img]:h-auto [&_table]:block [&_table]:max-w-full [&_table]:overflow-x-auto"
              // Legacy instructions are stored as rich HTML authored via Trix
              // by staff; rendering requires dangerouslySetInnerHTML. Authors
              // are privileged admin users gated by services_manage permission.
              dangerouslySetInnerHTML={{ __html: description }}
            />
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
