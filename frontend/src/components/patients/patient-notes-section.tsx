import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2, Pin, PinOff, Send, Pencil, Trash2, X, Check } from 'lucide-react';
import { api, ApiError } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { cn } from '@/lib/cn';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { PatientNote } from './types';

/**
 * Notes section — adds, edits, deletes, pins/unpins. Server enforces that
 * only the note creator (or super-admin) can edit/delete; we mirror that
 * client-side so the affordances aren't shown to users who can't use them.
 */
interface PatientNotesSectionProps {
  patientId: number | string;
}

type NotesResponse = PatientNote[];

export function PatientNotesSection({ patientId }: PatientNotesSectionProps) {
  const qc = useQueryClient();
  const { user } = useAuth();
  const currentUserId = typeof user?.id === 'number' ? user.id : 0;
  const isSuperAdmin =
    typeof user?.role === 'string' && user.role.toLowerCase().includes('super');

  const [draft, setDraft] = useState('');
  const [editingId, setEditingId] = useState<number | null>(null);
  const [editDraft, setEditDraft] = useState('');

  const notes = useQuery({
    queryKey: ['patients', 'notes', patientId],
    queryFn: () => api.get<NotesResponse>(`/api/patients/${patientId}/notes`),
  });

  const refresh = () => qc.invalidateQueries({ queryKey: ['patients', 'notes', patientId] });

  const add = useMutation({
    mutationFn: (note: string) => api.post(`/api/patients/${patientId}/notes`, { note }),
    onSuccess: () => { setDraft(''); refresh(); },
  });

  const update = useMutation({
    mutationFn: (vars: { id: number; note: string }) =>
      api.put(`/api/patients/${patientId}/notes/${vars.id}`, { note: vars.note }),
    onSuccess: () => { setEditingId(null); setEditDraft(''); refresh(); },
  });

  const remove = useMutation({
    mutationFn: (id: number) => api.delete(`/api/patients/${patientId}/notes/${id}`),
    onSuccess: refresh,
  });

  const togglePin = useMutation({
    mutationFn: (id: number) => api.post(`/api/patients/${patientId}/notes/${id}/toggle-pin`),
    onSuccess: refresh,
  });

  const items = (notes.data ?? []).slice().sort((a, b) => {
    if (a.is_pinned !== b.is_pinned) return a.is_pinned ? -1 : 1;
    return new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime();
  });

  return (
    <section>
      <h3 className="mb-2 flex items-center gap-2 text-[10.5px] font-semibold uppercase tracking-wider text-fg-subtle">
        Notes
        {items.length > 0 && (
          <span className="rounded-full bg-surface px-1.5 py-px text-[10px] font-medium text-fg-muted ring-1 ring-inset ring-hairline">
            {items.length}
          </span>
        )}
      </h3>

      {notes.isLoading ? (
        <div className="space-y-2">
          <Skeleton className="h-12 w-full" />
          <Skeleton className="h-12 w-full" />
        </div>
      ) : items.length === 0 ? (
        <div className="rounded-lg border border-dashed border-hairline px-3 py-4 text-center text-[12.5px] text-fg-muted">
          No notes yet.
        </div>
      ) : (
        <ul className="space-y-2.5">
          {items.map((n) => {
            const isOwner = n.created_by?.id === currentUserId;
            const canEdit = isOwner || isSuperAdmin;
            const isEditing = editingId === n.id;
            return (
              <li
                key={n.id}
                className={cn(
                  'rounded-lg p-3 ring-1 ring-inset',
                  n.is_pinned ? 'bg-accent-cyan-soft ring-accent-cyan/20' : 'bg-surface ring-hairline',
                )}
              >
                <div className="flex items-center justify-between gap-2">
                  <div className="flex items-center gap-2">
                    <span className="text-[12px] font-medium text-fg">{n.created_by?.name || 'Unknown'}</span>
                    {n.is_pinned && (
                      <span className="inline-flex items-center gap-0.5 rounded-full bg-accent-cyan-hover/15 px-1.5 py-px text-[10px] font-medium text-accent-cyan-hover">
                        <Pin className="size-2.5" /> Pinned
                      </span>
                    )}
                  </div>
                  <div className="flex items-center gap-0.5">
                    {canEdit && (
                      <button
                        type="button"
                        aria-label={n.is_pinned ? 'Unpin' : 'Pin'}
                        title={n.is_pinned ? 'Unpin' : 'Pin'}
                        onClick={() => togglePin.mutate(n.id)}
                        className="inline-flex size-6 items-center justify-center rounded text-fg-subtle hover:bg-elevated hover:text-fg"
                      >
                        {n.is_pinned ? <PinOff className="size-3" /> : <Pin className="size-3" />}
                      </button>
                    )}
                    {canEdit && !isEditing && (
                      <button
                        type="button"
                        aria-label="Edit note"
                        title="Edit"
                        onClick={() => { setEditingId(n.id); setEditDraft(n.note); }}
                        className="inline-flex size-6 items-center justify-center rounded text-fg-subtle hover:bg-elevated hover:text-fg"
                      >
                        <Pencil className="size-3" />
                      </button>
                    )}
                    {canEdit && (
                      <button
                        type="button"
                        aria-label="Delete note"
                        title="Delete"
                        onClick={() => {
                          if (window.confirm('Delete this note?')) remove.mutate(n.id);
                        }}
                        className="inline-flex size-6 items-center justify-center rounded text-fg-subtle hover:bg-elevated hover:text-danger"
                      >
                        <Trash2 className="size-3" />
                      </button>
                    )}
                  </div>
                </div>
                <div className="text-[11px] text-fg-subtle">{n.created_at}</div>

                {isEditing ? (
                  <div className="mt-2 space-y-2">
                    <textarea
                      value={editDraft}
                      onChange={(e) => setEditDraft(e.target.value)}
                      rows={3}
                      className="w-full rounded-lg bg-elevated px-3 py-2 text-[13px] text-fg ring-1 ring-inset ring-hairline focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50"
                    />
                    <div className="flex items-center gap-2">
                      <Button
                        size="sm"
                        disabled={!editDraft.trim() || update.isPending}
                        onClick={() => update.mutate({ id: n.id, note: editDraft.trim() })}
                      >
                        {update.isPending ? <Loader2 className="size-3.5 animate-spin" /> : <Check className="size-3.5" />}
                        Save
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => { setEditingId(null); setEditDraft(''); }}
                      >
                        <X className="size-3.5" /> Cancel
                      </Button>
                    </div>
                  </div>
                ) : (
                  <div className="mt-1 whitespace-pre-wrap text-[13px] text-fg-muted">{n.note}</div>
                )}
              </li>
            );
          })}
        </ul>
      )}

      {/* Composer */}
      <form
        className="mt-3 flex items-end gap-2"
        onSubmit={(e) => {
          e.preventDefault();
          const v = draft.trim();
          if (v) add.mutate(v);
        }}
      >
        <textarea
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          placeholder="Add a note…"
          rows={2}
          disabled={add.isPending}
          className="flex-1 rounded-lg bg-elevated px-3 py-2 text-[13px] text-fg ring-1 ring-inset ring-hairline placeholder:text-fg-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/50 disabled:opacity-50"
        />
        <Button
          type="submit"
          size="icon"
          variant="accent"
          disabled={!draft.trim() || add.isPending}
          aria-label="Post note"
        >
          {add.isPending ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
        </Button>
      </form>
      {add.error && (
        <p role="alert" className="mt-1 text-[12px] text-danger">
          {(add.error as ApiError).message}
        </p>
      )}
    </section>
  );
}
