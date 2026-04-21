import { useMemo, useState } from 'react';
import { Link, useParams, useNavigate, useSearchParams } from 'react-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  ArrowLeft,
  CalendarDays,
  ClipboardList,
  CreditCard,
  Crown,
  FileSignature,
  FileText,
  Gift,
  History,
  Loader2,
  Phone,
  Pencil,
  Plus,
  Power,
  PowerOff,
  Receipt,
  ScrollText,
  Stethoscope,
  Syringe,
  User,
} from 'lucide-react';
import { api, ApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
import { formatPhone, formatTableDate, initials } from '@/lib/format';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { PatientFormDialog } from '@/components/patients/patient-form-dialog';
import { PatientNotesSection } from '@/components/patients/patient-notes-section';
import { PatientAppointmentsTable } from '@/components/patients/patient-appointments-table';
import { PatientActivityTab } from '@/components/patients/patient-activity-tab';
import { PatientDocumentsTab } from '@/components/patients/patient-documents-tab';
import {
  AddReferralDialog,
  AssignMembershipDialog,
  AssignVoucherDialog,
} from '@/components/patients/patient-action-dialogs';
import type { PatientDetailResponse } from '@/components/patients/types';
import { PatientPlansInner } from '@/components/plans/patient-plans-inner';

const TABS = [
  { id: 'profile', label: 'Profile', icon: User },
  { id: 'consultations', label: 'Consultations', icon: Stethoscope },
  { id: 'treatments', label: 'Treatments', icon: Syringe },
  { id: 'plans', label: 'Plans', icon: ClipboardList },
  { id: 'invoices', label: 'Invoices', icon: Receipt },
  { id: 'refunds', label: 'Refunds', icon: CreditCard },
  { id: 'documents', label: 'Documents', icon: FileText },
  { id: 'activity', label: 'Activity', icon: History },
] as const;

type TabId = (typeof TABS)[number]['id'];

export default function PatientDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const qc = useQueryClient();
  const [searchParams, setSearchParams] = useSearchParams();
  const tab = (searchParams.get('tab') as TabId) || 'profile';
  const setTab = (next: TabId) => setSearchParams({ tab: next }, { replace: true });

  const [editOpen, setEditOpen] = useState(false);
  const [membershipOpen, setMembershipOpen] = useState(false);
  const [voucherOpen, setVoucherOpen] = useState(false);
  const [referralOpen, setReferralOpen] = useState(false);

  const detail = useQuery({
    queryKey: ['patients', 'detail', id],
    queryFn: () => api.get<PatientDetailResponse>(`/api/patients/${id}`),
    enabled: !!id,
  });

  const toggleStatus = useMutation({
    mutationFn: (newStatus: 0 | 1) =>
      api.post('/api/patients/status', { id: Number(id), status: newStatus }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['patients', 'detail', id] });
      qc.invalidateQueries({ queryKey: ['patients', 'datatable'] });
    },
  });

  const patient = detail.data?.patient;
  const membership = detail.data?.membership;
  const perms = detail.data?.permissions ?? {};

  const created = useMemo(
    () => (patient?.created_at ? formatTableDate(patient.created_at) : null),
    [patient?.created_at],
  );

  if (detail.isLoading) {
    return (
      <div className="mx-auto max-w-[1200px] space-y-4 px-4 py-5 sm:px-6 lg:px-8">
        <Skeleton className="h-9 w-48" />
        <Card className="p-5"><Skeleton className="h-32 w-full" /></Card>
      </div>
    );
  }

  if (detail.error || !patient) {
    return (
      <div className="mx-auto max-w-xl px-4 py-12 text-center">
        <Card className="p-6">
          <h2 className="text-base font-semibold">Patient not found</h2>
          <p className="mt-1 text-[13px] text-fg-muted">
            {(detail.error as ApiError | undefined)?.message || `No patient with id ${id}.`}
          </p>
          <Button variant="secondary" size="sm" className="mt-4" asChild>
            <Link to="/patients"><ArrowLeft className="size-3.5" /> Back to patients</Link>
          </Button>
        </Card>
      </div>
    );
  }

  const isActive = !!patient.active;
  const showPhone = perms.contact !== false;

  return (
    <div className="mx-auto max-w-[1200px] space-y-4 px-4 py-5 sm:px-6 lg:px-8">
      {/* Breadcrumb-ish back link */}
      <button
        onClick={() => navigate('/patients')}
        className="inline-flex items-center gap-1 text-[12.5px] text-fg-muted transition-colors hover:text-fg"
      >
        <ArrowLeft className="size-3.5" /> All patients
      </button>

      {/* Header card */}
      <Card className="overflow-hidden">
        <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-start">
          <Avatar className="size-16 shrink-0">
            <AvatarFallback className="text-lg">{initials(patient.name)}</AvatarFallback>
          </Avatar>
          <div className="min-w-0 flex-1 space-y-2">
            <div className="flex flex-wrap items-baseline gap-2">
              <h1 className="text-xl font-semibold tracking-tight">{patient.name}</h1>
              <span className="text-[13px] text-fg-subtle nums-tabular">C-{patient.id}</span>
              {isActive ? (
                <Badge variant="success" dot>Active</Badge>
              ) : (
                <Badge variant="danger" dot>Inactive</Badge>
              )}
              {membership?.code && (
                <Badge variant={membership.type?.toLowerCase().includes('gold') ? 'warning' : 'brand'}>
                  <Crown className="size-3" />
                  {membership.type || 'Member'} · {membership.code}
                </Badge>
              )}
            </div>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-[12.5px] text-fg-muted">
              {showPhone && patient.phone && (
                <span className="inline-flex items-center gap-1 nums-tabular">
                  <Phone className="size-3" /> {formatPhone(patient.phone)}
                </span>
              )}
              {patient.email && <span className="truncate">{patient.email}</span>}
              {patient.gender_label && <span>· {patient.gender_label}</span>}
              {created && <span>· joined {created.date}</span>}
            </div>
          </div>
          <div className="flex flex-wrap gap-1.5">
            {perms.edit !== false && (
              <Button variant="secondary" size="sm" onClick={() => setEditOpen(true)}>
                <Pencil className="size-3.5" /> Edit
              </Button>
            )}
            {(isActive ? perms.inactive : perms.active) !== false && (
              <Button
                variant="secondary"
                size="sm"
                onClick={() => toggleStatus.mutate(isActive ? 0 : 1)}
                disabled={toggleStatus.isPending}
                className={cn(isActive ? 'text-danger hover:bg-danger-soft' : 'text-success hover:bg-success-soft')}
              >
                {toggleStatus.isPending ? <Loader2 className="size-3.5 animate-spin" /> :
                  isActive ? <PowerOff className="size-3.5" /> : <Power className="size-3.5" />}
                {isActive ? 'Deactivate' : 'Activate'}
              </Button>
            )}
          </div>
        </div>

        {/* Tab nav */}
        <nav
          aria-label="Patient sections"
          className="flex overflow-x-auto border-t border-hairline bg-surface/50 px-2"
        >
          {TABS.map((t) => {
            const Icon = t.icon;
            const active = tab === t.id;
            return (
              <button
                key={t.id}
                type="button"
                onClick={() => setTab(t.id)}
                className={cn(
                  'flex shrink-0 items-center gap-1.5 px-3 py-2.5 text-[13px] font-medium transition-colors relative',
                  active ? 'text-fg' : 'text-fg-muted hover:text-fg',
                )}
              >
                <Icon className="size-3.5" />
                {t.label}
                {active && (
                  <span className="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-accent-cyan" />
                )}
              </button>
            );
          })}
        </nav>
      </Card>

      {/* Tab content */}
      <Card className="p-5">
        {tab === 'profile' && (
          <ProfileTab
            patient={patient}
            membership={membership}
            permissions={perms}
            onAssignMembership={() => setMembershipOpen(true)}
            onAssignVoucher={() => setVoucherOpen(true)}
            onAddReferral={() => setReferralOpen(true)}
          />
        )}
        {tab === 'consultations' && (
          <PatientAppointmentsTable
            patientId={patient.id}
            endpoint="consultations-datatable"
            emptyHint="No consultations recorded for this patient."
          />
        )}
        {tab === 'treatments' && (
          <PatientAppointmentsTable
            patientId={patient.id}
            endpoint="treatments-datatable"
            emptyHint="No treatments recorded for this patient."
          />
        )}
        {tab === 'plans' && id && (
          <PatientPlansInner patientId={Number(id)} patientName={patient?.name} />
        )}
        {tab === 'invoices' && (
          <ComingSoonStub
            icon={Receipt}
            label="Invoices"
            note="Patient-scoped invoices ship with the Invoices module sprint. The legacy /admin patient page still hosts them."
          />
        )}
        {tab === 'refunds' && (
          <ComingSoonStub
            icon={FileSignature}
            label="Refunds"
            note="Patient-scoped refunds ship with the Refunds module sprint."
          />
        )}
        {tab === 'documents' && <PatientDocumentsTab patientId={patient.id} />}
        {tab === 'activity' && <PatientActivityTab patientId={patient.id} />}
      </Card>

      {/* Modals */}
      <PatientFormDialog
        mode="edit"
        patientId={editOpen ? patient.id : undefined}
        open={editOpen}
        onOpenChange={setEditOpen}
      />
      <AssignMembershipDialog patientId={patient.id} open={membershipOpen} onOpenChange={setMembershipOpen} />
      <AssignVoucherDialog patientId={patient.id} open={voucherOpen} onOpenChange={setVoucherOpen} />
      <AddReferralDialog patientId={patient.id} open={referralOpen} onOpenChange={setReferralOpen} />
    </div>
  );
}

function ProfileTab({
  patient,
  membership,
  permissions,
  onAssignMembership,
  onAssignVoucher,
  onAddReferral,
}: {
  patient: PatientDetailResponse['patient'];
  membership: PatientDetailResponse['membership'] | undefined;
  permissions: PatientDetailResponse['permissions'];
  onAssignMembership: () => void;
  onAssignVoucher: () => void;
  onAddReferral: () => void;
}) {
  return (
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
      {/* Left: Personal info */}
      <div className="space-y-5 lg:col-span-2">
        <section>
          <h3 className="mb-2 text-[10.5px] font-semibold uppercase tracking-wider text-fg-subtle">
            Personal information
          </h3>
          <dl className="grid grid-cols-1 gap-x-4 gap-y-2.5 text-[13px] sm:grid-cols-2">
            <DetailRow label="Email" value={patient.email || '—'} />
            <DetailRow label="Gender" value={patient.gender_label || (patient.gender === 1 ? 'Male' : patient.gender === 2 ? 'Female' : '—')} />
            <DetailRow label="Date of birth" value={patient.dob || '—'} />
            <DetailRow label="CNIC" value={patient.cnic || '—'} />
            <DetailRow
              label="Referred by"
              value={
                patient.referred_by
                  ? `${patient.referrer_name ?? 'Patient'} (#${patient.referred_by})`
                  : '—'
              }
            />
          </dl>
        </section>

        <PatientNotesSection patientId={patient.id} />
      </div>

      {/* Right: Membership card + actions */}
      <aside className="space-y-3">
        <Card className="bg-gradient-to-br from-brand-navy to-brand-navy-2 text-fg-on-dark p-4 ring-0">
          <div className="flex items-center gap-2">
            <Crown className="size-4 text-accent-cyan" />
            <span className="text-[10.5px] font-semibold uppercase tracking-wider text-white/60">Membership</span>
          </div>
          {membership?.code ? (
            <>
              <div className="mt-3 text-base font-semibold tracking-tight">{membership.type}</div>
              <div className="mt-0.5 text-[12.5px] font-mono text-white/60">{membership.code}</div>
              <div className="mt-3 flex items-center gap-2 text-[11.5px]">
                {membership.is_active ? (
                  <span className="inline-flex items-center gap-1 rounded-full bg-success/20 px-2 py-px text-success">
                    <span className="size-1.5 rounded-full bg-success" /> Active
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1 rounded-full bg-warning/20 px-2 py-px text-warning">
                    Expired
                  </span>
                )}
                {membership.end_date && (
                  <span className="text-white/50">until {membership.end_date}</span>
                )}
              </div>
              {permissions.add_referrals && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={onAddReferral}
                  className="mt-3 w-full justify-center text-white hover:bg-white/10"
                >
                  <Plus className="size-3.5" /> Add referral
                </Button>
              )}
            </>
          ) : (
            <>
              <div className="mt-3 text-[13px] text-white/70">No active membership.</div>
              <Button
                variant="accent"
                size="sm"
                onClick={onAssignMembership}
                className="mt-3 w-full justify-center"
              >
                <Plus className="size-3.5" /> Assign membership
              </Button>
            </>
          )}
        </Card>

        <Card className="p-4">
          <div className="text-[10.5px] font-semibold uppercase tracking-wider text-fg-subtle">Quick actions</div>
          <div className="mt-2 grid grid-cols-1 gap-1.5">
            <Button variant="secondary" size="sm" className="justify-start" onClick={onAssignVoucher}>
              <Gift className="size-3.5" /> Assign voucher
            </Button>
            <Button variant="secondary" size="sm" className="justify-start" asChild>
              <a href={`/admin/patients/${patient.id}/card`} target="_blank" rel="noopener noreferrer">
                <ScrollText className="size-3.5" /> Open in legacy admin
              </a>
            </Button>
          </div>
        </Card>
      </aside>
    </div>
  );
}

function DetailRow({ label, value, className }: { label: string; value: React.ReactNode; className?: string }) {
  return (
    <div className={cn('grid grid-cols-[110px_1fr] items-start gap-2', className)}>
      <dt className="text-[11px] font-medium uppercase tracking-wide text-fg-subtle">{label}</dt>
      <dd className="text-fg break-words">{value}</dd>
    </div>
  );
}

function ComingSoonStub({ icon: Icon, label, note }: { icon: typeof CalendarDays; label: string; note: string }) {
  return (
    <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-hairline px-4 py-12 text-center">
      <Icon className="size-5 text-fg-subtle" />
      <div className="mt-2 text-[13px] font-medium text-fg">{label}</div>
      <div className="mt-1 max-w-md text-[12px] text-fg-muted">{note}</div>
    </div>
  );
}

