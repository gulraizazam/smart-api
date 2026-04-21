import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

/**
 * Plan SMS-log viewer. Source: GET /api/packages/sms_logs/{id}
 * Returns `{ SMSLogs: SMSLogs[] }` ordered by created_at desc.
 */

type SMSLog = {
  id: number;
  phone: string | null;
  text: string | null;
  sent: number | boolean | null;
  is_refund: number | boolean | null;
  created_at: string | null;
};

type Resp = { SMSLogs: SMSLog[] };

interface Props {
  planId?: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function PlanSmsLogsDialog({ planId, open, onOpenChange }: Props) {
  const logs = useQuery({
    queryKey: ['plans', 'sms-logs', planId],
    queryFn: () => api.get<Resp>(`/api/packages/sms_logs/${planId}`),
    enabled: open && !!planId,
  });

  const rows = logs.data?.SMSLogs ?? [];

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl" aria-describedby={undefined}>
        <DialogHeader>
          <DialogTitle>Plan SMS logs{planId ? ` — #${planId}` : ''}</DialogTitle>
        </DialogHeader>

        <div className="max-h-[60vh] space-y-2 overflow-y-auto pr-1">
          {logs.isLoading && <Skeleton className="h-40 w-full" />}
          {logs.error && (
            <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
              Couldn't load SMS logs: {(logs.error as Error).message}
            </div>
          )}
          {!logs.isLoading && rows.length === 0 && (
            <div className="flex flex-col items-center justify-center py-10 text-center text-[12.5px] text-fg-muted">
              No SMS has been sent for this plan.
            </div>
          )}
          {rows.map((log) => (
            <div key={log.id} className="rounded-lg px-3 py-2 ring-1 ring-inset ring-hairline">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-center gap-1.5">
                  <span className="text-[12.5px] font-medium text-fg">{log.phone ?? 'No phone'}</span>
                  {!!log.sent ? <Badge variant="success">Sent</Badge> : <Badge variant="warning">Pending</Badge>}
                  {!!log.is_refund && <Badge variant="danger">Refund</Badge>}
                </div>
                <div className="text-[11.5px] text-fg-subtle">{log.created_at ?? '—'}</div>
              </div>
              {log.text && (
                <div className="mt-1.5 whitespace-pre-wrap break-words text-[12.5px] text-fg-muted">{log.text}</div>
              )}
            </div>
          ))}
        </div>

        <DialogFooter>
          <Button type="button" variant="secondary" size="sm" onClick={() => onOpenChange(false)}>
            Close
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
