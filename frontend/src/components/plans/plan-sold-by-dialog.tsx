import { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Loader2 } from 'lucide-react';
import { api, ApiError } from '@/lib/api';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Reassign the sold-by user for a single package_service line.
 *
 * Source:
 *   GET  /api/packages/getsoldbydata?package_service_id=X&location_id=Y
 *   POST /api/packages/updatesoldby { package_service_id, sold_by }
 *
 * The allowed-users list is filtered by doctor_has_locations + FDM role
 * scoped to the plan's centre, and the currently-assigned user is always
 * included even if they've since gone inactive (audit continuity).
 */

type SoldByResp = {
  users: Record<string, string>;
  current_sold_by: number | null;
  package_services: { id: number; sold_by: number | null }[];
};

interface Props {
  packageId: number;
  packageServiceId: number | null;
  locationId: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function PlanSoldByDialog({ packageId, packageServiceId, locationId, open, onOpenChange }: Props) {
  const qc = useQueryClient();
  const [soldBy, setSoldBy] = useState<string>('');

  const data = useQuery({
    queryKey: ['plans', 'sold-by-options', packageServiceId, locationId],
    queryFn: () => {
      const qs = new URLSearchParams({
        package_service_id: String(packageServiceId ?? ''),
        location_id: String(locationId),
      }).toString();
      return api.get<SoldByResp>(`/api/packages/getsoldbydata?${qs}`);
    },
    enabled: open && !!packageServiceId && !!locationId,
  });

  useEffect(() => {
    if (data.data?.current_sold_by != null) {
      setSoldBy(String(data.data.current_sold_by));
    }
  }, [data.data]);

  const save = useMutation({
    mutationFn: () =>
      api.post('/api/packages/updatesoldby', {
        package_service_id: packageServiceId,
        sold_by: Number(soldBy),
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['plans', 'detail', packageId] });
      onOpenChange(false);
    },
  });

  const users = Object.entries(data.data?.users ?? {});

  return (
    <Dialog open={open} onOpenChange={(o) => !o && !save.isPending && onOpenChange(false)}>
      <DialogContent className="max-w-md" aria-describedby={undefined}>
        <DialogHeader>
          <DialogTitle>Reassign sold by</DialogTitle>
        </DialogHeader>

        {data.isLoading ? (
          <Skeleton className="h-20 w-full" />
        ) : (
          <div className="space-y-3">
            <div>
              <Label htmlFor="sold-by">Sold by</Label>
              <Select
                id="sold-by"
                value={soldBy}
                onChange={(e) => setSoldBy(e.target.value)}
                disabled={save.isPending}
              >
                <option value="">Select…</option>
                {users.map(([id, name]) => (
                  <option key={id} value={id}>{name}</option>
                ))}
              </Select>
            </div>

            {save.error && (
              <div role="alert" className="rounded-lg bg-danger-soft px-3 py-2 text-[12.5px] text-danger ring-1 ring-inset ring-danger/20">
                {save.error instanceof ApiError ? save.error.message : (save.error as Error).message}
              </div>
            )}
          </div>
        )}

        <DialogFooter>
          <Button variant="secondary" size="sm" onClick={() => onOpenChange(false)} disabled={save.isPending}>
            Cancel
          </Button>
          <Button
            size="sm"
            onClick={() => save.mutate()}
            disabled={save.isPending || data.isLoading || !soldBy}
          >
            {save.isPending && <Loader2 className="size-4 animate-spin" />}
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
