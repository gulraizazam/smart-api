import { useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router';
import { ArrowLeft, Download } from 'lucide-react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Badge } from '@/components/ui/badge';

/**
 * Plan activity log. Source: POST /api/plans/planDatatable/{id}
 *
 * The server returns a pre-formatted finance log. Each entry has the action,
 * user, timestamps, field before/after values (already decoded by the server's
 * Financelog::Calculate_Val helper), and a nested detail_log for sub-changes.
 *
 * Excel export uses the legacy web route (/admin/plans/log/{id}/excel) in a
 * new tab — the controller streams an XLSX via Spreadsheet_Excel_Writer,
 * which can't be re-exposed easily as JSON.
 */

type LogEntry = {
  'sr no': number;
  id: number;
  action: string;
  table: string;
  user_id: string;
  created_at_orignal: string;
  updated_at_orignal: string;
  cash_flow?: { before?: string; after?: string };
  payment_mode_id?: { before?: string; after?: string };
  cash_amount?: { before?: string; after?: string };
  created_at?: { before?: string; after?: string };
  detail_log?: Record<
    string,
    { field_name: string; field_before?: string; field_after?: string }
  >;
};

type LogResp = {
  data?: Record<string, LogEntry>;
  meta?: {
    total: number;
    page: number;
    pages: number;
  };
};

export default function PlanLogPage() {
  const { id } = useParams();
  const planId = Number(id);

  const logQuery = useQuery({
    queryKey: ['plans', 'log', planId],
    queryFn: () =>
      api.post<LogResp>(
        `/api/plans/planDatatable/${planId}`,
        {
          pagination: { page: 1, perpage: 200 },
          sort: { field: 'created_at', sort: 'desc' },
        },
        { raw: true },
      ),
    enabled: !!planId,
  });

  const entries: LogEntry[] = useMemo(
    () => (logQuery.data?.data ? Object.values(logQuery.data.data) : []),
    [logQuery.data],
  );

  const handleExport = () => {
    window.open(`/admin/plans/log/${planId}/excel`, '_blank', 'noopener');
  };

  const actionTone = (action: string): 'success' | 'warning' | 'danger' | 'neutral' | 'brand' => {
    switch (action) {
      case 'Create':
        return 'success';
      case 'Edit':
        return 'brand';
      case 'Delete':
      case 'Cancel':
        return 'danger';
      case 'Inactive':
        return 'warning';
      case 'Active':
        return 'success';
      default:
        return 'neutral';
    }
  };

  return (
    <div className="mx-auto max-w-[1200px] space-y-3 px-4 py-4 sm:px-6 lg:px-8">
      <div className="flex flex-wrap items-center gap-3">
        <Button asChild variant="secondary" size="sm">
          <Link to="/plans">
            <ArrowLeft className="size-3.5" /> Back
          </Link>
        </Button>
        <div>
          <h1 className="text-xl font-semibold tracking-tight">Plan #{planId} — activity log</h1>
          <p className="text-[12.5px] text-fg-subtle">
            Every payment edit, refund, and settlement touching this plan.
          </p>
        </div>
        <div className="ml-auto">
          <Button size="sm" variant="secondary" onClick={handleExport}>
            <Download className="size-3.5" /> Export Excel
          </Button>
        </div>
      </div>

      <Card className="overflow-hidden">
        {logQuery.isLoading && (
          <div className="p-4">
            <Skeleton className="h-40 w-full" />
          </div>
        )}
        {logQuery.error && (
          <div className="border-l-4 border-danger bg-danger-soft px-4 py-3 text-[12.5px] text-danger">
            Couldn't load log: {(logQuery.error as Error).message}
          </div>
        )}
        {!logQuery.isLoading && entries.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <h3 className="text-[14px] font-medium text-fg">No activity recorded</h3>
            <p className="mt-1 text-[12.5px] text-fg-muted">
              Activity rows appear once payments, refunds, or settlements are recorded.
            </p>
          </div>
        )}

        {entries.length > 0 && (
          <ul className="divide-y divide-hairline/70">
            {entries.map((entry) => (
              <li key={entry.id} className="px-4 py-3">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge variant={actionTone(entry.action)}>{entry.action}</Badge>
                  <span className="text-[12.5px] font-medium text-fg">{entry.user_id}</span>
                  <span className="text-[11.5px] text-fg-subtle">
                    {entry.created_at_orignal}
                  </span>
                </div>

                {/* Top-level field changes */}
                <div className="mt-2 grid grid-cols-1 gap-2 text-[12.5px] sm:grid-cols-2">
                  {entry.cash_flow && (
                    <FieldChange label="Cash flow" change={entry.cash_flow} />
                  )}
                  {entry.cash_amount && (
                    <FieldChange label="Amount" change={entry.cash_amount} />
                  )}
                  {entry.payment_mode_id && (
                    <FieldChange label="Payment mode" change={entry.payment_mode_id} />
                  )}
                  {entry.created_at && (
                    <FieldChange label="Date" change={entry.created_at} />
                  )}
                </div>

                {/* Nested detail_log (granular field-by-field change set) */}
                {entry.detail_log && Object.keys(entry.detail_log).length > 0 && (
                  <details className="mt-2 text-[12px] text-fg-subtle">
                    <summary className="cursor-pointer">
                      Details ({Object.keys(entry.detail_log).length})
                    </summary>
                    <ul className="mt-2 space-y-1 pl-3">
                      {Object.values(entry.detail_log).map((d, i) => (
                        <li key={i} className="flex items-center gap-2">
                          <span className="font-medium">{d.field_name}:</span>
                          <code className="text-[11px]">{d.field_before ?? '—'}</code>
                          <span>→</span>
                          <code className="text-[11px]">{d.field_after ?? '—'}</code>
                        </li>
                      ))}
                    </ul>
                  </details>
                )}
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}

function FieldChange({
  label,
  change,
}: {
  label: string;
  change: { before?: string; after?: string };
}) {
  return (
    <div className="flex items-start gap-2">
      <span className="shrink-0 text-fg-subtle">{label}:</span>
      <div className="flex flex-wrap items-center gap-1">
        <code className="rounded bg-surface px-1 text-[11px]">{change.before ?? '—'}</code>
        <span className="text-fg-subtle">→</span>
        <code className="rounded bg-surface px-1 text-[11px]">{change.after ?? '—'}</code>
      </div>
    </div>
  );
}
