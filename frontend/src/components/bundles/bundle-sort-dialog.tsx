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
import { GripVertical, Loader2 } from 'lucide-react';
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

/** Flat-list reorder for Bundles. Unlike Services, there's no parent-child
 *  tree — bundles are a single sortable sequence driven by `sort_number`. */

type BundleSortItem = { id: number; name: string; sort_number: number };

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function BundleSortDialog({ open, onOpenChange }: Props) {
  const qc = useQueryClient();
  const [items, setItems] = useState<BundleSortItem[]>([]);
  const [dirty, setDirty] = useState(false);

  const tree = useQuery({
    queryKey: ['bundles', 'sort-list'],
    queryFn: () => api.get<BundleSortItem[]>('/api/bundles/sort/get'),
    enabled: open,
    refetchOnWindowFocus: false,
  });

  useEffect(() => {
    if (tree.data) {
      setItems(tree.data);
      setDirty(false);
    }
  }, [tree.data]);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const save = useMutation({
    mutationFn: () =>
      api.post('/api/bundles/sort/save', { item_ids: items.map((i) => i.id) }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['bundles', 'datatable'] });
      qc.invalidateQueries({ queryKey: ['bundles', 'sort-list'] });
      onOpenChange(false);
    },
  });

  const handleDragEnd = (e: DragEndEvent) => {
    const { active, over } = e;
    if (!over || active.id === over.id) return;
    setItems((list) => {
      const oldIndex = list.findIndex((p) => p.id === Number(active.id));
      const newIndex = list.findIndex((p) => p.id === Number(over.id));
      if (oldIndex < 0 || newIndex < 0) return list;
      return arrayMove(list, oldIndex, newIndex);
    });
    setDirty(true);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle>Reorder bundles</DialogTitle>
          <DialogDescription>
            Drag to reorder. The new order applies to every screen that
            lists bundles in their default sequence.
          </DialogDescription>
        </DialogHeader>

        <div className="max-h-[60vh] overflow-y-auto rounded-lg ring-1 ring-inset ring-hairline">
          {tree.isLoading && (
            <div className="space-y-2 p-3">
              {Array.from({ length: 6 }).map((_, i) => (
                <Skeleton key={i} className="h-9 w-full" />
              ))}
            </div>
          )}
          {!tree.isLoading && items.length === 0 && (
            <div className="p-6 text-center text-[13px] text-fg-muted">No bundles to sort.</div>
          )}
          {!tree.isLoading && items.length > 0 && (
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
              <SortableContext items={items.map((b) => b.id)} strategy={verticalListSortingStrategy}>
                <ul className="divide-y divide-hairline/60">
                  {items.map((b) => (
                    <BundleRow key={b.id} bundle={b} />
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
            disabled={!dirty || save.isPending}
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

function BundleRow({ bundle }: { bundle: BundleSortItem }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: bundle.id,
  });
  const style = { transform: CSS.Transform.toString(transform), transition };

  return (
    <li ref={setNodeRef} style={style} className={cn('bg-elevated', isDragging && 'opacity-60')}>
      <div className="flex items-center gap-2 px-2 py-2">
        <button
          type="button"
          {...attributes}
          {...listeners}
          aria-label={`Reorder ${bundle.name}`}
          className="inline-flex size-7 cursor-grab items-center justify-center rounded-md text-fg-subtle hover:bg-surface hover:text-fg active:cursor-grabbing focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue"
        >
          <GripVertical className="size-3.5" />
        </button>
        <div className="flex-1 truncate text-[13px] font-medium text-fg">{bundle.name}</div>
      </div>
    </li>
  );
}
