import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Download, Loader2, Upload } from 'lucide-react';
import { api, ApiError } from '@/lib/api';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

/**
 * Lead import dialog — hits the same endpoint the legacy Blade modal
 * posts to (resources/views/admin/leads/import.blade.php), with two
 * parity details preserved:
 *   • "Skip lead statuses" is disabled until "Update existing records"
 *     is checked — skip is a no-op on creates, so keeping it locked
 *     prevents a confusing toggle. Matches legacy behaviour.
 *   • Unchecking "Update existing records" auto-clears skip so hidden
 *     state can't leak back in.
 *
 * Payload:
 *   POST /api/leads/upload  (multipart/form-data)
 *     leads_file          File   required, CSV only, ≤2MB
 *     update_records      '1'|'' overwrite rows matched by phone
 *     skip_lead_statuses  '1'|'' preserve existing statuses on update
 *   Response shape (non-standard envelope):
 *     { status: boolean, message: string }
 *
 * Sample template: /assets/files/SampleLeads.csv.
 */

type UploadResponse = { status?: boolean; message?: string };

interface LeadImportDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function LeadImportDialog({ open, onOpenChange }: LeadImportDialogProps) {
  const queryClient = useQueryClient();
  const [file, setFile] = useState<File | null>(null);
  const [updateRecords, setUpdateRecords] = useState(false);
  const [skipLeadStatuses, setSkipLeadStatuses] = useState(false);
  const [result, setResult] = useState<{ ok: boolean; message: string } | null>(null);

  const mutation = useMutation({
    mutationFn: async () => {
      if (!file) throw new Error('Please choose a file to import.');
      const formData = new FormData();
      formData.append('leads_file', file);
      formData.append('update_records', updateRecords ? '1' : '');
      formData.append('skip_lead_statuses', skipLeadStatuses ? '1' : '');
      // The upload endpoint returns { status, message } — not the standard
      // { success, data } envelope — so we opt out of envelope unwrapping.
      return api.post<UploadResponse>('/api/leads/upload', formData, { raw: true });
    },
    onSuccess: (res) => {
      const message = res?.message ?? 'Leads imported.';
      const ok = res?.status !== false;
      setResult({ ok, message });
      if (ok) {
        queryClient.invalidateQueries({ queryKey: ['leads'] });
      }
    },
    onError: (err) => {
      const message =
        err instanceof ApiError
          ? err.message
          : err instanceof Error
            ? err.message
            : 'Import failed.';
      setResult({ ok: false, message });
    },
  });

  const reset = () => {
    setFile(null);
    setUpdateRecords(false);
    setSkipLeadStatuses(false);
    setResult(null);
    mutation.reset();
  };

  const handleClose = (next: boolean) => {
    if (mutation.isPending) return;
    if (!next) reset();
    onOpenChange(next);
  };

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-[480px]">
        <DialogHeader>
          <DialogTitle>Import leads</DialogTitle>
          <DialogDescription>
            Upload an Excel or CSV file. Phone is matched against existing leads to detect duplicates.
          </DialogDescription>
        </DialogHeader>

        <form
          className="space-y-4"
          onSubmit={(e) => {
            e.preventDefault();
            if (!mutation.isPending) mutation.mutate();
          }}
        >
          <div className="space-y-2">
            <Label htmlFor="import-file">
              File <span className="text-fg-subtle">(.csv — max 2MB)</span>
            </Label>
            <input
              id="import-file"
              type="file"
              accept=".csv,text/csv"
              onChange={(e) => {
                setFile(e.target.files?.[0] ?? null);
                setResult(null);
              }}
              className="block w-full text-[13px] file:mr-3 file:rounded-md file:border-0 file:bg-surface file:px-3 file:py-1.5 file:text-[13px] file:font-medium file:text-fg hover:file:bg-elevated"
            />
            <a
              href="/assets/files/SampleLeads.csv"
              target="_blank"
              rel="noreferrer"
              className="inline-flex items-center gap-1 text-[12px] text-accent-cyan hover:underline"
            >
              <Download className="size-3" /> Download sample template
            </a>
          </div>

          <label className="flex items-start gap-2 text-[13px]">
            <input
              type="checkbox"
              checked={updateRecords}
              onChange={(e) => {
                const next = e.target.checked;
                setUpdateRecords(next);
                // Skip is only meaningful when updating — clear it on uncheck
                // so users don't leave a hidden-disabled checkbox armed.
                if (!next) setSkipLeadStatuses(false);
              }}
              className="mt-0.5"
            />
            <span>
              <span className="font-medium">Update existing records</span>
              <span className="block text-[12px] text-fg-muted">
                When phone matches an existing lead, overwrite its fields instead of skipping.
              </span>
            </span>
          </label>

          <label
            className={
              'flex items-start gap-2 text-[13px] transition-opacity ' +
              (updateRecords ? '' : 'opacity-50')
            }
          >
            <input
              type="checkbox"
              checked={skipLeadStatuses}
              disabled={!updateRecords}
              onChange={(e) => setSkipLeadStatuses(e.target.checked)}
              className="mt-0.5 disabled:cursor-not-allowed"
            />
            <span>
              <span className="font-medium">Skip lead statuses</span>
              <span className="block text-[12px] text-fg-muted">
                Preserve each existing lead's current status during update.
                {!updateRecords && ' Enable "Update existing records" to use this.'}
              </span>
            </span>
          </label>

          {result && (
            <div
              role="alert"
              className={
                result.ok
                  ? 'rounded-md bg-success/10 px-3 py-2 text-[12.5px] text-success'
                  : 'rounded-md bg-danger/10 px-3 py-2 text-[12.5px] text-danger'
              }
            >
              {result.message}
            </div>
          )}

          <DialogFooter>
            <Button
              type="button"
              variant="secondary"
              onClick={() => handleClose(false)}
              disabled={mutation.isPending}
            >
              {result?.ok ? 'Close' : 'Cancel'}
            </Button>
            <Button type="submit" disabled={!file || mutation.isPending || result?.ok}>
              {mutation.isPending ? (
                <>
                  <Loader2 className="size-3.5 animate-spin" /> Importing…
                </>
              ) : (
                <>
                  <Upload className="size-3.5" /> Import
                </>
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
