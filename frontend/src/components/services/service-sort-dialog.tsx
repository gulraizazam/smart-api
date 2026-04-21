import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core';
import {
  SortableContext,
  arrayMove,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ChevronDown, ChevronRight, GripVertical, Loader2 } from 'lucide-react';
import { api } from '@/lib/api';
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
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Drag-drop reordering for Services. Two concurrent sortable contexts:
 *   1. Parent categories — reorder among themselves (saved via
 *      POST /api/services_category_sort_save).
 *   2. Children within a parent — reorder inside their parent only
 *      (saved via POST /api/services/sort/save per affected parent).
 *
 * Cross-parent moves are not supported — the legacy admin doesn't allow
 * them either, and changing a service's parent is a data-edit concern
 * handled by the form dialog, not the sort UI.
 */

type Child = { id: number; name: string; parent_id: number; sort_number: number };
type Parent = { id: number; name: string; children: Child[] };

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function ServiceSortDialog({ open, onOpenChange }: Props) {
  const qc = useQueryClient();
  const [parents, setParents] = useState<Parent[]>([]);
  const [expanded, setExpanded] = useState<Record<number, boolean>>({});
  const [dirtyParents, setDirtyParents] = useState<Set<number>>(new Set());
  const [parentOrderDirty, setParentOrderDirty] = useState(false);

  const tree = useQuery({
    queryKey: ['services', 'sort-tree'],
    queryFn: () => api.get<Parent[]>('/api/services/sort/get'),
    enabled: open,
    refetchOnWindowFocus: false,
  });

  useEffect(() => {
    if (tree.data) {
      setParents(tree.data);
      setDirtyParents(new Set());
      setParentOrderDirty(false);
    }
  }, [tree.data]);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const save = useMutation({
    mutationFn: async () => {
      const tasks: Promise<unknown>[] = [];

      if (parentOrderDirty) {
        tasks.push(
          api.post('/api/services_category_sort_save', {
            item_ids: parents.map((p) => p.id),
          }),
        );
      }

      for (const p of parents) {
        if (!dirtyParents.has(p.id)) continue;
        tasks.push(
          api.post('/api/services/sort/save', {
            parent_id: p.id,
            item_ids: p.children.map((c) => c.id),
          }),
        );
      }

      await Promise.all(tasks);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['services', 'datatable'] });
      qc.invalidateQueries({ queryKey: ['services', 'sort-tree'] });
      onOpenChange(false);
    },
  });

  const handleParentDragEnd = (e: DragEndEvent) => {
    const { active, over } = e;
    if (!over || active.id === over.id) return;
    setParents((list) => {
      const oldIndex = list.findIndex((p) => p.id === Number(active.id));
      const newIndex = list.findIndex((p) => p.id === Number(over.id));
      if (oldIndex < 0 || newIndex < 0) return list;
      return arrayMove(list, oldIndex, newIndex);
    });
    setParentOrderDirty(true);
  };

  const handleChildDragEnd = (parentId: number) => (e: DragEndEvent) => {
    const { active, over } = e;
    if (!over || active.id === over.id) return;
    setParents((list) =>
      list.map((p) => {
        if (p.id !== parentId) return p;
        const oldIndex = p.children.findIndex((c) => c.id === Number(active.id));
        const newIndex = p.children.findIndex((c) => c.id === Number(over.id));
        if (oldIndex < 0 || newIndex < 0) return p;
        return { ...p, children: arrayMove(p.children, oldIndex, newIndex) };
      }),
    );
    setDirtyParents((d) => new Set(d).add(parentId));
  };

  const hasChanges = parentOrderDirty || dirtyParents.size > 0;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Reorder services</DialogTitle>
          <DialogDescription>
            Drag parent categories to reorder them; expand a parent to reorder its services.
            Changing a service's parent is done from the edit dialog, not here.
          </DialogDescription>
        </DialogHeader>

        <div className="max-h-[60vh] overflow-y-auto rounded-lg ring-1 ring-inset ring-hairline">
          {tree.isLoading && (
            <div className="space-y-2 p-3">
              {Array.from({ length: 5 }).map((_, i) => (
                <Skeleton key={i} className="h-10 w-full" />
              ))}
            </div>
          )}
          {!tree.isLoading && parents.length === 0 && (
            <div className="p-6 text-center text-[13px] text-fg-muted">No services to sort.</div>
          )}
          {!tree.isLoading && parents.length > 0 && (
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleParentDragEnd}>
              <SortableContext items={parents.map((p) => p.id)} strategy={verticalListSortingStrategy}>
                <ul className="divide-y divide-hairline/60">
                  {parents.map((p) => (
                    <ParentRow
                      key={p.id}
                      parent={p}
                      expanded={!!expanded[p.id]}
                      onToggle={() =>
                        setExpanded((e) => ({ ...e, [p.id]: !e[p.id] }))
                      }
                      onChildDragEnd={handleChildDragEnd(p.id)}
                      sensors={sensors}
                    />
                  ))}
                </ul>
              </SortableContext>
            </DndContext>
          )}
        </div>

        {save.error && (
          <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
            Couldn’t save order: {(save.error as Error).message}
          </div>
        )}

        <DialogFooter>
          <Button type="button" variant="secondary" size="sm" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button
            type="button"
            size="sm"
            disabled={!hasChanges || save.isPending}
            onClick={() => save.mutate()}
          >
            {save.isPending && <Loader2 className="size-3.5 animate-spin" />}
            Save order
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function ParentRow({
  parent,
  expanded,
  onToggle,
  onChildDragEnd,
  sensors,
}: {
  parent: Parent;
  expanded: boolean;
  onToggle: () => void;
  onChildDragEnd: (e: DragEndEvent) => void;
  sensors: ReturnType<typeof useSensors>;
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: parent.id,
  });
  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  };

  return (
    <li ref={setNodeRef} style={style} className={cn('bg-elevated', isDragging && 'opacity-60')}>
      <div className="flex items-center gap-2 px-2 py-2">
        <button
          type="button"
          {...attributes}
          {...listeners}
          aria-label={`Reorder ${parent.name}`}
          className="inline-flex size-7 cursor-grab items-center justify-center rounded-md text-fg-subtle hover:bg-surface hover:text-fg active:cursor-grabbing focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"
        >
          <GripVertical className="size-3.5" />
        </button>
        <button
          type="button"
          onClick={onToggle}
          aria-expanded={expanded}
          aria-label={expanded ? `Collapse ${parent.name}` : `Expand ${parent.name}`}
          className="inline-flex size-7 items-center justify-center rounded-md text-fg-subtle hover:bg-surface hover:text-fg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"
        >
          {expanded ? <ChevronDown className="size-3.5" /> : <ChevronRight className="size-3.5" />}
        </button>
        <div className="flex-1 truncate text-[13px] font-medium text-fg">{parent.name}</div>
        <div className="text-[11.5px] text-fg-subtle">
          {parent.children.length} service{parent.children.length === 1 ? '' : 's'}
        </div>
      </div>

      {expanded && parent.children.length > 0 && (
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onChildDragEnd}>
          <SortableContext items={parent.children.map((c) => c.id)} strategy={verticalListSortingStrategy}>
            <ul className="border-t border-hairline/60 bg-surface">
              {parent.children.map((c) => (
                <ChildRow key={c.id} child={c} />
              ))}
            </ul>
          </SortableContext>
        </DndContext>
      )}
      {expanded && parent.children.length === 0 && (
        <div className="border-t border-hairline/60 bg-surface px-10 py-2 text-[12px] text-fg-subtle">
          No child services.
        </div>
      )}
    </li>
  );
}

function ChildRow({ child }: { child: Child }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: child.id,
  });
  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
  };
  return (
    <li
      ref={setNodeRef}
      style={style}
      className={cn(
        'flex items-center gap-2 pl-10 pr-3 py-1.5 text-[12.5px] text-fg',
        isDragging && 'opacity-60',
      )}
    >
      <button
        type="button"
        {...attributes}
        {...listeners}
        aria-label={`Reorder ${child.name}`}
        className="inline-flex size-6 cursor-grab items-center justify-center rounded-md text-fg-subtle hover:bg-elevated hover:text-fg active:cursor-grabbing focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"
      >
        <GripVertical className="size-3" />
      </button>
      <span className="truncate">{child.name}</span>
    </li>
  );
}
