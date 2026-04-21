import { useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { FileText, Loader2, Pencil, Trash2, Upload, UploadCloud, X } from 'lucide-react';
import { api, ApiError } from '@/lib/api';
import { cn } from '@/lib/cn';
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
import { Select } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Documents tab — upload, list, delete.
 *
 * Upload flow is deliberately progressive so document type never gets
 * picked by accident. There is no default type: the form starts as a
 * simple dropzone, and the type picker only appears once a file is
 * chosen. The upload button stays disabled until the user makes an
 * explicit type selection, and the "Uploading as X" confirmation line
 * spells out the choice one last time before commit.
 *
 * Server contract (PatientService / PatientDocumentRequest):
 *   POST   /api/patients/{id}/upload-document  { file, document_type }
 *     document_type ∈ consent_form | consultation_form | others
 *   GET    /api/patients/{id}/documents        → DocItem[]
 *   DELETE /api/patients/{id}/documents/{docId}
 */

const DOCUMENT_TYPES = [
  { value: 'consent_form', label: 'Consent form' },
  { value: 'consultation_form', label: 'Consultation form' },
  { value: 'others', label: 'Other' },
] as const;

type DocumentType = (typeof DOCUMENT_TYPES)[number]['value'];

const ACCEPTED_EXTS = '.jpg,.jpeg,.png,.pdf,.docx,.xlsx';
const MAX_SIZE_BYTES = 10 * 1024 * 1024; // keep in sync with PHP upload_max_filesize / Laravel `max:10240`

interface PatientDocumentsTabProps {
  patientId: number | string;
}

type DocItem = {
  id: number;
  name?: string;
  document_type?: string;
  url?: string;
  created_at?: string;
};

export function PatientDocumentsTab({ patientId }: PatientDocumentsTabProps) {
  const qc = useQueryClient();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);
  const [type, setType] = useState<DocumentType | null>(null);
  const [isDragging, setIsDragging] = useState(false);
  const [clientError, setClientError] = useState<string | null>(null);
  const [editingDoc, setEditingDoc] = useState<DocItem | null>(null);

  const docs = useQuery({
    queryKey: ['patients', 'documents', patientId],
    queryFn: () => api.get<DocItem[]>(`/api/patients/${patientId}/documents`),
  });

  const upload = useMutation({
    mutationFn: () => {
      if (!file) throw new Error('Choose a file first.');
      if (!type) throw new Error('Pick a document type first.');
      const fd = new FormData();
      fd.append('file', file);
      fd.append('document_type', type);
      return api.post(`/api/patients/${patientId}/upload-document`, fd);
    },
    onSuccess: () => {
      resetForm();
      qc.invalidateQueries({ queryKey: ['patients', 'documents', patientId] });
    },
  });

  const remove = useMutation({
    mutationFn: (id: number) => api.delete(`/api/patients/${patientId}/documents/${id}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['patients', 'documents', patientId] }),
  });

  const resetForm = () => {
    setFile(null);
    setType(null);
    setClientError(null);
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  const pickFile = (picked: File | null) => {
    setClientError(null);
    if (!picked) {
      setFile(null);
      setType(null);
      return;
    }
    if (picked.size > MAX_SIZE_BYTES) {
      setClientError(`File is too large — max ${formatBytes(MAX_SIZE_BYTES)}.`);
      return;
    }
    setFile(picked);
    // Force the user to re-choose a type for every file — any previous
    // selection shouldn't carry over silently.
    setType(null);
  };

  const handleDrop = (e: React.DragEvent<HTMLElement>) => {
    e.preventDefault();
    setIsDragging(false);
    pickFile(e.dataTransfer.files?.[0] ?? null);
  };

  const items = docs.data ?? [];

  return (
    <div className="space-y-4">
      {/* Upload panel ----------------------------------------------------- */}
      <div className="rounded-xl bg-elevated p-4 ring-1 ring-inset ring-hairline">
        <div className="mb-2 text-[10.5px] font-semibold uppercase tracking-wider text-fg-subtle">
          Upload document
        </div>

        {!file ? (
          /* Step 1 — dropzone. No type picker here; picking a file is the
             only action available, so the user can't miss the next step. */
          <label
            onDrop={handleDrop}
            onDragOver={(e) => {
              e.preventDefault();
              setIsDragging(true);
            }}
            onDragLeave={() => setIsDragging(false)}
            className={cn(
              'flex cursor-pointer flex-col items-center justify-center gap-1.5 rounded-lg border-2 border-dashed px-4 py-8 text-center transition-colors',
              isDragging
                ? 'border-accent-cyan bg-accent-cyan/5'
                : 'border-hairline hover:border-fg-subtle hover:bg-surface/40',
            )}
          >
            <UploadCloud
              className={cn(
                'size-6 transition-colors',
                isDragging ? 'text-accent-cyan' : 'text-fg-subtle',
              )}
              aria-hidden
            />
            <div className="text-[13px] font-medium text-fg">
              Drop a file here, or click to browse
            </div>
            <div className="text-[11.5px] text-fg-subtle">
              PDF, DOC, JPG, PNG · up to {formatBytes(MAX_SIZE_BYTES)}
            </div>
            <input
              ref={fileInputRef}
              type="file"
              accept={ACCEPTED_EXTS}
              onChange={(e) => pickFile(e.target.files?.[0] ?? null)}
              className="sr-only"
            />
          </label>
        ) : (
          /* Step 2 — file chosen. Show filename + mandatory type picker. */
          <div className="space-y-3">
            <div className="flex items-center gap-3 rounded-lg bg-surface px-3 py-2 ring-1 ring-inset ring-hairline">
              <FileText className="size-4 shrink-0 text-accent-cyan" aria-hidden />
              <div className="min-w-0 flex-1">
                <div className="truncate text-[13px] font-medium text-fg">{file.name}</div>
                <div className="text-[11px] text-fg-subtle">{formatBytes(file.size)}</div>
              </div>
              <button
                type="button"
                onClick={resetForm}
                aria-label="Remove file"
                className="inline-flex size-7 items-center justify-center rounded-md text-fg-subtle transition-colors hover:bg-elevated hover:text-fg"
              >
                <X className="size-3.5" />
              </button>
            </div>

            <div>
              <div className="mb-1.5 flex items-center gap-1 text-[12.5px] font-medium text-fg">
                What kind of document is this?
                <span className="text-danger" aria-hidden>
                  *
                </span>
              </div>
              <div
                role="radiogroup"
                aria-label="Document type"
                aria-required="true"
                className="flex flex-wrap gap-1.5"
              >
                {DOCUMENT_TYPES.map((t) => {
                  const active = type === t.value;
                  return (
                    <button
                      key={t.value}
                      type="button"
                      role="radio"
                      aria-checked={active}
                      onClick={() => setType(t.value)}
                      className={cn(
                        'inline-flex items-center rounded-full px-3 py-1.5 text-[12.5px] font-medium ring-1 ring-inset transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50',
                        active
                          ? 'bg-accent-cyan/10 text-accent-cyan ring-accent-cyan/40'
                          : 'bg-surface text-fg-muted ring-hairline hover:text-fg hover:ring-fg-subtle',
                      )}
                    >
                      {t.label}
                    </button>
                  );
                })}
              </div>
            </div>

            <div className="flex items-center justify-between gap-3 pt-1">
              <p
                className={cn(
                  'text-[12px]',
                  type ? 'text-fg-muted' : 'text-fg-subtle italic',
                )}
              >
                {type ? (
                  <>
                    Uploading as{' '}
                    <span className="font-medium text-fg">
                      {DOCUMENT_TYPES.find((t) => t.value === type)?.label}
                    </span>
                  </>
                ) : (
                  'Pick a type to continue'
                )}
              </p>
              <Button
                size="sm"
                onClick={() => upload.mutate()}
                disabled={!type || upload.isPending}
              >
                {upload.isPending ? (
                  <Loader2 className="size-4 animate-spin" aria-hidden />
                ) : (
                  <Upload className="size-3.5" aria-hidden />
                )}
                Upload
              </Button>
            </div>
          </div>
        )}

        {(clientError || upload.error) && (
          <p role="alert" className="mt-2 text-[12px] text-danger">
            {clientError ?? (upload.error as ApiError).message}
          </p>
        )}
      </div>

      {/* Documents list --------------------------------------------------- */}
      {docs.isLoading ? (
        <div className="space-y-2">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-14 w-full" />
          ))}
        </div>
      ) : items.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-hairline px-4 py-10 text-center">
          <FileText className="size-5 text-fg-subtle" aria-hidden />
          <div className="mt-2 text-[13px] font-medium text-fg">No documents yet</div>
          <div className="mt-0.5 max-w-xs text-[12px] text-fg-muted">
            Upload prescriptions, lab reports, or consent forms here.
          </div>
        </div>
      ) : (
        <ul className="space-y-2">
          {items.map((d) => (
            <li
              key={d.id}
              className="group flex items-center gap-3 rounded-lg bg-surface px-3 py-2.5 ring-1 ring-inset ring-hairline"
            >
              <FileText className="size-4 shrink-0 text-fg-subtle" aria-hidden />
              <div className="min-w-0 flex-1">
                <div className="truncate text-[13px] font-medium text-fg">
                  {d.name || `Document #${d.id}`}
                </div>
                <div className="flex items-center gap-1.5 text-[11px] text-fg-subtle">
                  <span>{humanizeType(d.document_type)}</span>
                  {d.created_at && (
                    <>
                      <span aria-hidden>·</span>
                      <time dateTime={d.created_at} title={new Date(d.created_at).toLocaleString()}>
                        {formatRelative(d.created_at)}
                      </time>
                    </>
                  )}
                </div>
              </div>
              {d.url && (
                <a
                  href={d.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-[12px] font-medium text-brand-blue hover:underline"
                >
                  Open
                </a>
              )}
              <button
                type="button"
                aria-label={`Edit ${d.name ?? 'document'}`}
                onClick={() => setEditingDoc(d)}
                className="inline-flex size-7 items-center justify-center rounded-md text-fg-subtle transition-colors hover:bg-elevated hover:text-fg"
              >
                <Pencil className="size-3.5" aria-hidden />
              </button>
              <button
                type="button"
                aria-label={`Delete ${d.name ?? 'document'}`}
                onClick={() => {
                  if (window.confirm(`Delete "${d.name ?? `Document #${d.id}`}"?`)) {
                    remove.mutate(d.id);
                  }
                }}
                className="inline-flex size-7 items-center justify-center rounded-md text-fg-subtle transition-colors hover:bg-elevated hover:text-danger"
              >
                <Trash2 className="size-3.5" aria-hidden />
              </button>
            </li>
          ))}
        </ul>
      )}

      <DocumentEditDialog
        patientId={patientId}
        doc={editingDoc}
        onClose={() => setEditingDoc(null)}
      />
    </div>
  );
}

/* ---------------------------------------------------------------------- *
 * Edit dialog — change a document's type and optionally swap the file.
 * Backed by POST /api/patients/{id}/update-document/{docId}, which the
 * legacy admin already uses for the same flow.
 * ---------------------------------------------------------------------- */

interface DocumentEditDialogProps {
  patientId: number | string;
  doc: DocItem | null;
  onClose: () => void;
}

function DocumentEditDialog({ patientId, doc, onClose }: DocumentEditDialogProps) {
  const qc = useQueryClient();
  const fileRef = useRef<HTMLInputElement>(null);
  const [type, setType] = useState<DocumentType>('consent_form');
  const [replaceFile, setReplaceFile] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Re-seed local state every time the dialog opens against a new doc.
  // Default the picker to whatever the row currently has so unintentional
  // type changes don't slip through on accidental save.
  const open = doc !== null;
  if (open && doc && doc.document_type) {
    const matched = DOCUMENT_TYPES.find((t) => t.value === doc.document_type)?.value;
    if (matched && type !== matched && replaceFile === null && error === null) {
      // setState during render guarded by the matched/!== checks above.
      // eslint-disable-next-line react-hooks/rules-of-hooks
      queueMicrotask(() => setType(matched));
    }
  }

  const save = useMutation({
    mutationFn: () => {
      if (!doc) throw new Error('No document selected.');
      if (replaceFile && replaceFile.size > MAX_SIZE_BYTES) {
        throw new Error(`File is too large — max ${formatBytes(MAX_SIZE_BYTES)}.`);
      }
      const fd = new FormData();
      fd.append('document_type', type);
      if (replaceFile) fd.append('file', replaceFile);
      return api.post(
        `/api/patients/${patientId}/update-document/${doc.id}`,
        fd,
      );
    },
    onSuccess: () => {
      reset();
      qc.invalidateQueries({ queryKey: ['patients', 'documents', patientId] });
      onClose();
    },
    onError: (err) => {
      setError(err instanceof Error ? err.message : 'Update failed.');
    },
  });

  const reset = () => {
    setReplaceFile(null);
    setError(null);
    if (fileRef.current) fileRef.current.value = '';
  };

  const handleClose = (next: boolean) => {
    if (save.isPending) return;
    if (!next) {
      reset();
      onClose();
    }
  };

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-[480px]">
        <DialogHeader>
          <DialogTitle>Edit document</DialogTitle>
          <DialogDescription>
            Change the document type, or upload a replacement file. The
            previous file is wiped from storage when a replacement is uploaded.
          </DialogDescription>
        </DialogHeader>

        {doc && (
          <div className="space-y-4">
            <div className="flex items-center gap-3 rounded-lg bg-surface px-3 py-2 ring-1 ring-inset ring-hairline">
              <FileText className="size-4 shrink-0 text-fg-subtle" aria-hidden />
              <div className="min-w-0 flex-1">
                <div className="truncate text-[13px] font-medium text-fg">{doc.name || `Document #${doc.id}`}</div>
                <div className="text-[11px] text-fg-subtle">Current type: {humanizeType(doc.document_type)}</div>
              </div>
            </div>

            <div className="space-y-1">
              <Label htmlFor="doc-edit-type" className="text-[11.5px]">Type</Label>
              <Select
                id="doc-edit-type"
                value={type}
                onChange={(e) => setType(e.target.value as DocumentType)}
                className="h-9"
              >
                {DOCUMENT_TYPES.map((t) => (
                  <option key={t.value} value={t.value}>{t.label}</option>
                ))}
              </Select>
            </div>

            <div className="space-y-1">
              <Label htmlFor="doc-edit-file" className="text-[11.5px]">
                Replace file <span className="text-fg-subtle">(optional)</span>
              </Label>
              <input
                ref={fileRef}
                id="doc-edit-file"
                type="file"
                accept={ACCEPTED_EXTS}
                onChange={(e) => {
                  setError(null);
                  setReplaceFile(e.target.files?.[0] ?? null);
                }}
                className="block h-9 w-full text-[12.5px] text-fg-muted file:mr-3 file:h-full file:rounded-md file:border-0 file:bg-surface file:px-3 file:py-1.5 file:text-[12px] file:text-fg hover:file:bg-hairline/40"
              />
              {replaceFile && (
                <p className="text-[11.5px] text-fg-muted">
                  Replacing with <span className="font-medium text-fg">{replaceFile.name}</span> ({formatBytes(replaceFile.size)})
                </p>
              )}
            </div>

            {error && (
              <p role="alert" className="rounded-md bg-danger/10 px-3 py-2 text-[12.5px] text-danger">
                {error}
              </p>
            )}

            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => handleClose(false)} disabled={save.isPending}>
                Cancel
              </Button>
              <Button onClick={() => save.mutate()} disabled={save.isPending}>
                {save.isPending ? <Loader2 className="size-3.5 animate-spin" /> : null}
                Save changes
              </Button>
            </DialogFooter>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}

/* ---------------------------------------------------------------------- *
 * Local formatting helpers — kept inline because they're small and used
 * only here. If any are needed in a second component, lift to
 * `frontend/src/lib/format.ts` at that point (CLAUDE.md: abstract on the
 * third real repetition).
 * ---------------------------------------------------------------------- */

function humanizeType(value?: string): string {
  if (!value) return 'Document';
  const match = DOCUMENT_TYPES.find((t) => t.value === value);
  if (match) return match.label;
  // Pre-R2 rows may have free-form strings — pretty-print as a safe fallback.
  return value
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function formatRelative(iso: string): string {
  const then = new Date(iso);
  const diff = (Date.now() - then.getTime()) / 1000;
  if (diff < 60) return 'just now';
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  if (diff < 86400 * 30) return `${Math.floor(diff / 86400)}d ago`;
  return then.toLocaleDateString();
}
