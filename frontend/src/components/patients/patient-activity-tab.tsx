import { useQuery } from '@tanstack/react-query';
import { Activity } from 'lucide-react';
import { api } from '@/lib/api';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Activity log — server returns rich HTML in `description` (with classes
 * like .act-tag.act-tag--red, .act-actor). We render it as HTML and
 * provide the matching styles in the wrapper so the legacy CSS doesn't
 * need to be ported.
 *
 * Security note: description comes straight from server-rendered audit
 * trails; the only HTML is hand-written by ActivityLogger. Treat it as
 * trusted server output. CUTERA-REVIEW: if user-supplied content ever
 * reaches the description field, escape it server-side or sanitize here.
 */

type ActivityItem = {
  type?: string;
  description?: string;
  created_at?: string;
  time_formatted?: string;
  time_short?: string;
};

interface PatientActivityTabProps {
  patientId: number | string;
}

export function PatientActivityTab({ patientId }: PatientActivityTabProps) {
  const activity = useQuery({
    queryKey: ['patients', 'activity', patientId],
    queryFn: () => api.get<ActivityItem[]>(`/api/patients/${patientId}/activity-history`),
  });

  if (activity.isLoading) {
    return (
      <div className="space-y-2">
        {Array.from({ length: 6 }).map((_, i) => (
          <Skeleton key={i} className="h-10 w-full" />
        ))}
      </div>
    );
  }

  if (activity.error) {
    return (
      <div className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
        Couldn't load activity: {(activity.error as Error).message}
      </div>
    );
  }

  const items = activity.data ?? [];
  if (items.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-hairline px-4 py-12 text-center">
        <Activity className="size-5 text-fg-subtle" />
        <div className="mt-2 text-[13px] font-medium text-fg">No activity yet</div>
        <div className="mt-0.5 text-[12px] text-fg-muted">Bookings, payments, and updates will appear here.</div>
      </div>
    );
  }

  return (
    <>
      {/* Inline styles for the server-rendered tag spans. Kept scoped
          via [data-patient-activity] so they don't leak globally. */}
      <style>{`
        [data-patient-activity] .act-tag {
          display: inline-flex; align-items: center;
          padding: 1px 6px; margin-inline-end: 6px;
          border-radius: 999px;
          font-size: 9.5px; font-weight: 600;
          text-transform: uppercase; letter-spacing: 0.04em;
          background: var(--color-surface);
          color: var(--color-fg-muted);
        }
        [data-patient-activity] .act-tag--red    { background: var(--color-danger-soft);  color: var(--color-danger); }
        [data-patient-activity] .act-tag--green  { background: var(--color-success-soft); color: var(--color-success); }
        [data-patient-activity] .act-tag--cyan   { background: var(--color-accent-cyan-soft); color: var(--color-accent-cyan-hover); }
        [data-patient-activity] .act-tag--amber  { background: var(--color-warning-soft); color: var(--color-warning); }
        [data-patient-activity] .act-tag--blue   { background: var(--color-brand-blue-soft); color: var(--color-brand-blue); }
        [data-patient-activity] .act-actor       { color: var(--color-fg-subtle); font-style: italic; }
      `}</style>
      <ul data-patient-activity className="relative space-y-3">
        <div className="absolute left-[5px] top-2 bottom-2 w-px bg-hairline" aria-hidden />
        {items.map((item, i) => (
          <li key={i} className="relative flex gap-3 pl-6">
            <span className="absolute left-0 top-1.5 size-2.5 rounded-full bg-accent-cyan ring-2 ring-elevated" />
            <div className="min-w-0 flex-1">
              <div
                className="text-[13px] leading-relaxed text-fg [&_strong]:font-medium"
                dangerouslySetInnerHTML={{ __html: item.description ?? '' }}
              />
              <div className="mt-0.5 text-[11px] text-fg-subtle nums-tabular">
                {item.time_formatted || item.created_at}
              </div>
            </div>
          </li>
        ))}
      </ul>
    </>
  );
}
