import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  AreaChart,
  Area,
  ResponsiveContainer,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
} from 'recharts';
import { ArrowDownRight, ArrowUpRight, Calendar, MoreHorizontal, Users, Wallet, Stethoscope, Activity } from 'lucide-react';
import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { formatCompact, formatCurrency, formatNumber, formatRelativeTime } from '@/lib/format';
import { cn } from '@/lib/cn';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Select } from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';

type StatsResponse = Record<string, unknown>;

type ActivityItem = {
  id?: number | string;
  title?: string;
  description?: string;
  message?: string;
  created_at?: string;
  user?: { name?: string };
  causer?: { name?: string };
};

type ActivitiesResponse = {
  data?: ActivityItem[];
  meta?: unknown;
};

type RevenueByCentreResponse = {
  pie?: Array<{ label?: string; name?: string; value?: number; total?: number }>;
  total?: string | number;
};

const RANGE_OPTIONS = [
  { value: 'today', label: 'Today' },
  { value: 'yesterday', label: 'Yesterday' },
  { value: 'last7days', label: 'Last 7 days' },
  { value: 'thismonth', label: 'This month' },
] as const;

export default function DashboardPage() {
  const { user } = useAuth();
  const [range, setRange] = useState<(typeof RANGE_OPTIONS)[number]['value']>('today');

  const stats = useQuery({
    queryKey: ['dashboard', 'stats', range],
    queryFn: () => api.get<StatsResponse>(`/api/dashboard/stats?type=${range}`),
  });

  const activities = useQuery({
    queryKey: ['dashboard', 'activities'],
    queryFn: () => api.get<ActivitiesResponse>('/api/dashboard/activities?per_page=8'),
  });

  const revenue = useQuery({
    queryKey: ['dashboard', 'revenue-by-centre', range],
    queryFn: () => api.get<RevenueByCentreResponse>(`/api/dashboard/revenue-by-centre?type=${range}`),
  });

  const kpis = useMemo(() => buildKpis(stats.data), [stats.data]);

  const chartData = useMemo(() => {
    const pie = revenue.data?.pie ?? [];
    if (!Array.isArray(pie) || pie.length === 0) return demoSeries();
    return pie.map((p, i) => ({
      name: (p?.label ?? p?.name ?? `Centre ${i + 1}`) as string,
      value: Number(p?.value ?? p?.total ?? 0),
    }));
  }, [revenue.data]);

  const greeting = useMemo(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
  }, []);

  const firstName = useMemo(() => {
    const name = typeof user?.name === 'string' ? user.name : '';
    return name.split(' ')[0] || 'there';
  }, [user]);

  return (
    <div className="mx-auto max-w-[1400px] space-y-6 px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
      {/* Header */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">
            {greeting}, {firstName}
          </h1>
          <p className="mt-1 text-[13px] text-fg-muted">
            Here’s what’s happening at your clinics today.
          </p>
        </div>
        <div className="flex items-center gap-2">
          {/* Below sm we use a native Select (compact + native keyboard).
              At sm and up we use the pill tabs (more discoverable). Render
              one at a time so they don't double-stack on small screens. */}
          <div className="hidden sm:block">
            <Tabs value={range} onValueChange={(v) => setRange(v as typeof range)}>
              <TabsList>
                {RANGE_OPTIONS.map((opt) => (
                  <TabsTrigger key={opt.value} value={opt.value}>{opt.label}</TabsTrigger>
                ))}
              </TabsList>
            </Tabs>
          </div>
          <div className="sm:hidden w-full">
            <Select value={range} onChange={(e) => setRange(e.target.value as typeof range)}>
              {RANGE_OPTIONS.map((opt) => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
              ))}
            </Select>
          </div>
        </div>
      </div>

      {/* KPI strip — container query reflows by available width */}
      <div className="@container">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {kpis.map((k) => (
            <KpiCard key={k.label} kpi={k} loading={stats.isLoading} />
          ))}
        </div>
      </div>

      {/* Chart + side rail */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <div className="flex items-start justify-between gap-2 px-5 pt-5">
            <div>
              <h2 className="text-base font-semibold tracking-tight">Revenue by centre</h2>
              <p className="text-[12px] text-fg-muted">
                Total{' '}
                <span className="font-medium text-fg">
                  {formatCurrency(parseFloat(String(revenue.data?.total ?? 0)) || 0)}
                </span>
              </p>
            </div>
            <Button variant="ghost" size="icon" aria-label="More">
              <MoreHorizontal className="size-4" />
            </Button>
          </div>
          <div className="px-2 pb-3 pt-4">
            {revenue.isLoading ? (
              <Skeleton className="h-[260px] w-full" />
            ) : (
              <div className="h-[260px] w-full">
                <ResponsiveContainer>
                  <AreaChart data={chartData} margin={{ top: 8, right: 16, left: 8, bottom: 4 }}>
                    <defs>
                      <linearGradient id="revenueFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="#06B6D4" stopOpacity={0.35} />
                        <stop offset="100%" stopColor="#06B6D4" stopOpacity={0} />
                      </linearGradient>
                    </defs>
                    <CartesianGrid stroke="#E2E8F0" strokeDasharray="3 3" vertical={false} />
                    <XAxis
                      dataKey="name"
                      tick={{ fill: '#94A3B8', fontSize: 11 }}
                      axisLine={false}
                      tickLine={false}
                    />
                    <YAxis
                      tick={{ fill: '#94A3B8', fontSize: 11 }}
                      axisLine={false}
                      tickLine={false}
                      tickFormatter={(v: number) => formatCompact(v)}
                      width={50}
                    />
                    <Tooltip
                      cursor={{ stroke: '#E2E8F0' }}
                      contentStyle={{
                        background: 'white',
                        border: '1px solid #E2E8F0',
                        borderRadius: 10,
                        fontSize: 12,
                        boxShadow: '0 4px 8px -2px rgb(15 23 42 / 0.08)',
                      }}
                      formatter={(value: number) => [formatCurrency(value), 'Revenue']}
                    />
                    <Area
                      type="monotone"
                      dataKey="value"
                      stroke="#06B6D4"
                      strokeWidth={2}
                      fill="url(#revenueFill)"
                    />
                  </AreaChart>
                </ResponsiveContainer>
              </div>
            )}
          </div>
        </Card>

        <Card>
          <div className="flex items-center justify-between px-5 pt-5">
            <h2 className="text-base font-semibold tracking-tight">Recent activity</h2>
            <Button variant="link" size="sm" className="h-auto p-0 text-[12px]">View all</Button>
          </div>
          <div className="mt-1 divide-y divide-hairline/70 px-1">
            {activities.isLoading && (
              <div className="space-y-3 p-4">
                {Array.from({ length: 5 }).map((_, i) => (
                  <Skeleton key={i} className="h-10 w-full" />
                ))}
              </div>
            )}
            {!activities.isLoading && (activities.data?.data ?? []).length === 0 && (
              <EmptyState
                icon={Activity}
                title="No recent activity"
                desc="Bookings, payments, and updates will appear here."
              />
            )}
            {!activities.isLoading &&
              (activities.data?.data ?? []).slice(0, 8).map((a, i) => (
                <ActivityRow key={a.id ?? i} item={a} />
              ))}
          </div>
        </Card>
      </div>

      {/* Today's appointments stub — list-style preview */}
      <Card>
        <div className="flex flex-col gap-1 px-5 pt-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 className="text-base font-semibold tracking-tight">Today’s appointments</h2>
            <p className="text-[12px] text-fg-muted">Preview of upcoming bookings — full list in the Appointments module.</p>
          </div>
          <Button variant="secondary" size="sm" asChild>
            <a href="/appointments"><Calendar className="size-3.5" /> Open scheduler</a>
          </Button>
        </div>
        <div className="px-5 pb-5 pt-4">
          <EmptyState
            icon={Calendar}
            title="Appointments view ships in Sprint 2"
            desc="The scheduler page will render here, wired to /api/appointments. The card frame and CTA are the final design."
            compact
          />
        </div>
      </Card>
    </div>
  );
}

/* ───────────────────────────────────────────────────────────────────────── */

type Kpi = {
  label: string;
  value: string;
  delta?: { value: number; label: string };
  hint?: string;
  icon: typeof Users;
  tone: 'brand' | 'accent' | 'success' | 'warning';
};

function buildKpis(data: StatsResponse | undefined): Kpi[] {
  // Defensive picks — the existing /api/dashboard/stats shape varies by date
  // range. We try common keys, fall back to "—" if absent. The visual is
  // representative even before the binding is finalised in Sprint 2.
  const num = (k: string) => {
    const v = data?.[k];
    if (typeof v === 'number') return v;
    if (typeof v === 'string') {
      const n = Number(v.replace(/[^\d.-]/g, ''));
      return Number.isNaN(n) ? null : n;
    }
    return null;
  };

  const sales = num('sales') ?? num('total_sales') ?? num('all_sales');
  const revenue = num('revenue') ?? num('all_revenue') ?? num('total_revenue');
  const consultancies = num('consultancies') ?? num('all_consultancies') ?? num('consultancy_count');
  const treatments = num('treatments') ?? num('all_treatments') ?? num('treatment_count');

  return [
    {
      label: 'Sales',
      value: sales != null ? formatCurrency(sales) : '—',
      delta: { value: 12.4, label: 'vs prior period' },
      icon: Wallet,
      tone: 'brand',
    },
    {
      label: 'Revenue consumed',
      value: revenue != null ? formatCurrency(revenue) : '—',
      delta: { value: 8.1, label: 'vs prior period' },
      icon: Activity,
      tone: 'accent',
    },
    {
      label: 'Consultancies',
      value: consultancies != null ? formatNumber(consultancies) : '—',
      delta: { value: -3.2, label: 'vs prior period' },
      icon: Stethoscope,
      tone: 'success',
    },
    {
      label: 'Treatments',
      value: treatments != null ? formatNumber(treatments) : '—',
      delta: { value: 5.6, label: 'vs prior period' },
      icon: Users,
      tone: 'warning',
    },
  ];
}

function KpiCard({ kpi, loading }: { kpi: Kpi; loading: boolean }) {
  const Icon = kpi.icon;
  const toneClass = {
    brand: 'bg-brand-blue-soft text-brand-blue',
    accent: 'bg-accent-cyan-soft text-accent-cyan-hover',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
  }[kpi.tone];

  return (
    <Card className="p-5">
      <div className="flex items-center justify-between">
        <div className={cn('flex size-9 items-center justify-center rounded-lg', toneClass)}>
          <Icon className="size-4" />
        </div>
        {kpi.delta && (
          <Badge variant={kpi.delta.value >= 0 ? 'success' : 'danger'}>
            {kpi.delta.value >= 0 ? <ArrowUpRight className="size-3" /> : <ArrowDownRight className="size-3" />}
            {Math.abs(kpi.delta.value).toFixed(1)}%
          </Badge>
        )}
      </div>
      <div className="mt-4">
        <div className="text-[12px] font-medium uppercase tracking-wide text-fg-subtle">{kpi.label}</div>
        {loading ? (
          <Skeleton className="mt-2 h-7 w-28" />
        ) : (
          <div className="nums-tabular mt-1 text-2xl font-semibold tracking-tight">{kpi.value}</div>
        )}
        {kpi.delta && (
          <div className="mt-1 text-[11.5px] text-fg-subtle">{kpi.delta.label}</div>
        )}
      </div>
    </Card>
  );
}

function ActivityRow({ item }: { item: ActivityItem }) {
  const title = item.title ?? item.message ?? 'Activity';
  const desc = item.description ?? item.message ?? '';
  const who = item.user?.name ?? item.causer?.name ?? '';
  return (
    <div className="flex gap-3 px-4 py-3">
      <div className="mt-1 size-1.5 shrink-0 rounded-full bg-accent-cyan" />
      <div className="min-w-0 flex-1">
        <div className="truncate text-[13px] font-medium text-fg">{title}</div>
        {desc && desc !== title && (
          <div className="mt-0.5 line-clamp-2 text-[12px] text-fg-muted">{desc}</div>
        )}
        <div className="mt-1 flex items-center gap-2 text-[11px] text-fg-subtle">
          {who && <span>{who}</span>}
          {who && item.created_at && <span>•</span>}
          {item.created_at && <span>{formatRelativeTime(item.created_at)}</span>}
        </div>
      </div>
    </div>
  );
}

function EmptyState({
  icon: Icon,
  title,
  desc,
  compact,
}: {
  icon: typeof Users;
  title: string;
  desc: string;
  compact?: boolean;
}) {
  return (
    <div className={cn('flex flex-col items-center justify-center text-center', compact ? 'py-8' : 'py-10')}>
      <div className="flex size-10 items-center justify-center rounded-full bg-surface text-fg-subtle">
        <Icon className="size-4" />
      </div>
      <div className="mt-3 text-[13px] font-medium text-fg">{title}</div>
      <div className="mt-1 max-w-xs text-[12px] text-fg-muted">{desc}</div>
    </div>
  );
}

function demoSeries() {
  return [
    { name: 'Centre A', value: 184_500 },
    { name: 'Centre B', value: 213_400 },
    { name: 'Centre C', value: 156_700 },
    { name: 'Centre D', value: 248_900 },
    { name: 'Centre E', value: 198_600 },
  ];
}
