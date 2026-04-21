import { useState } from 'react';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { api } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import type {
  PatientAppointmentDatatableResponse,
  PatientAppointmentRow,
} from './types';

/**
 * Generic patient-scoped appointments table. Used by all three tabs
 * (Consultations, Treatments, all Appointments) — they hit different
 * endpoints but the row shape is identical.
 *
 * Row fields are pre-formatted strings on the server (poorly-named
 * `_id` fields actually contain the LABEL, not the foreign key id).
 */
interface PatientAppointmentsTableProps {
  patientId: number | string;
  /**
   * Endpoint suffix:
   *   "consultations-datatable" | "treatments-datatable" | "appointments-datatable"
   */
  endpoint: 'consultations-datatable' | 'treatments-datatable' | 'appointments-datatable';
  emptyHint?: string;
}

const PAGE_SIZE = 25;

export function PatientAppointmentsTable({
  patientId,
  endpoint,
  emptyHint,
}: PatientAppointmentsTableProps) {
  const [page, setPage] = useState(1);

  const data = useQuery({
    queryKey: ['patients', 'appointments', endpoint, patientId, page],
    placeholderData: keepPreviousData,
    queryFn: () =>
      api.post<PatientAppointmentDatatableResponse>(
        `/api/patients/${patientId}/${endpoint}`,
        { pagination: { page, perpage: PAGE_SIZE } },
        { raw: true },
      ),
  });

  const rows = data.data?.data ?? [];
  const total = data.data?.meta?.total ?? 0;
  const totalPages = Math.max(1, Math.ceil(total / PAGE_SIZE));

  if (data.isLoading) {
    return (
      <div className="space-y-2">
        {Array.from({ length: 4 }).map((_, i) => (
          <Skeleton key={i} className="h-12 w-full" />
        ))}
      </div>
    );
  }

  if (rows.length === 0) {
    return (
      <div className="rounded-lg border border-dashed border-hairline px-4 py-10 text-center text-[12.5px] text-fg-muted">
        {emptyHint ?? 'No records.'}
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <Table>
        <TableHeader>
          <TableRow className="border-b border-hairline">
            <TableHead className="w-[16%] pl-3">Date</TableHead>
            <TableHead className="w-[26%]">Service</TableHead>
            <TableHead className="hidden w-[20%] md:table-cell">Doctor</TableHead>
            <TableHead className="hidden w-[18%] lg:table-cell">Centre</TableHead>
            <TableHead className="w-[14%]">Status</TableHead>
            <TableHead className="hidden w-[10%] xl:table-cell text-end">Invoice</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.map((row: PatientAppointmentRow) => (
            <TableRow key={row.id}>
              <TableCell className="py-2.5 pl-3 text-[12.5px] text-fg-muted">
                <span className="block truncate" title={String(row.scheduled_date ?? '')}>
                  {String(row.scheduled_date ?? '—')}
                </span>
              </TableCell>
              <TableCell className="py-2.5 text-[13px] text-fg">
                <span className="line-clamp-2">{readName(row.service_id) || readName(row.service) || '—'}</span>
              </TableCell>
              <TableCell className="hidden py-2.5 text-[12.5px] text-fg-muted md:table-cell">
                <span className="line-clamp-1">{readName(row.doctor_id) || readName(row.doctor) || '—'}</span>
              </TableCell>
              <TableCell className="hidden py-2.5 text-[12.5px] text-fg-muted lg:table-cell">
                <span className="line-clamp-1">{readName(row.location_id) || readName(row.location) || '—'}</span>
              </TableCell>
              <TableCell className="py-2.5">
                <StatusPill label={readName(row.appointment_status_id) || readName(row.status)} />
              </TableCell>
              <TableCell className="hidden py-2.5 text-end text-[12.5px] xl:table-cell">
                {row.invoice_id && Number(row.invoice_id) > 0 ? (
                  <span className="font-medium text-brand-blue nums-tabular">#{row.invoice_id}</span>
                ) : (
                  <span className="text-fg-subtle">—</span>
                )}
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      {totalPages > 1 && (
        <div className="flex items-center justify-between gap-3 px-1">
          <div className="text-[12px] text-fg-muted nums-tabular">
            {(page - 1) * PAGE_SIZE + 1}–{Math.min(page * PAGE_SIZE, total)} of {total.toLocaleString()}
          </div>
          <div className="flex items-center gap-1">
            <Button
              variant="secondary"
              size="sm"
              disabled={page <= 1 || data.isFetching}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
            >
              <ChevronLeft className="size-3.5" /> Prev
            </Button>
            <div className="px-2 text-[12px] text-fg-muted">
              Page <span className="font-medium text-fg">{page}</span> of {totalPages}
            </div>
            <Button
              variant="secondary"
              size="sm"
              disabled={page >= totalPages || data.isFetching}
              onClick={() => setPage((p) => p + 1)}
            >
              Next <ChevronRight className="size-3.5" />
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}

function readName(v: unknown): string {
  if (!v) return '';
  if (typeof v === 'string') return v;
  if (typeof v === 'object' && v !== null && 'name' in v && typeof (v as { name: unknown }).name === 'string') {
    return (v as { name: string }).name;
  }
  return '';
}

function StatusPill({ label }: { label: string }) {
  if (!label) return <span className="text-[12px] text-fg-subtle">—</span>;
  const l = label.toLowerCase();
  let variant: 'success' | 'warning' | 'danger' | 'brand' | 'neutral' = 'neutral';
  if (l.includes('cancel')) variant = 'danger';
  else if (l.includes('arrived') || l.includes('completed') || l.includes('done')) variant = 'success';
  else if (l.includes('scheduled') || l.includes('book')) variant = 'brand';
  else if (l.includes('pending') || l.includes('wait')) variant = 'warning';
  return <Badge variant={variant} dot>{label}</Badge>;
}
