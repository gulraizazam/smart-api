import { forwardRef } from 'react';
import { cn } from '@/lib/cn';

export const Table = forwardRef<HTMLTableElement, React.HTMLAttributes<HTMLTableElement>>(
  ({ className, ...props }, ref) => (
    <div className="relative w-full">
      {/* No horizontal scroll — the table is always sized to its container.
          table-fixed makes <th> widths authoritative and lets long cell
          content truncate inside its column instead of stretching it.
          Pages should hide less-critical columns at narrow breakpoints
          (hidden lg:table-cell etc.) rather than relying on overflow. */}
      <table ref={ref} className={cn('w-full caption-bottom text-[13px] table-fixed', className)} {...props} />
    </div>
  ),
);
Table.displayName = 'Table';

export const TableHeader = forwardRef<HTMLTableSectionElement, React.HTMLAttributes<HTMLTableSectionElement>>(
  ({ className, ...props }, ref) => (
    <thead
      ref={ref}
      className={cn('sticky top-0 z-10 bg-surface/95 backdrop-blur', className)}
      {...props}
    />
  ),
);
TableHeader.displayName = 'TableHeader';

export const TableBody = forwardRef<HTMLTableSectionElement, React.HTMLAttributes<HTMLTableSectionElement>>(
  ({ className, ...props }, ref) => (
    <tbody ref={ref} className={cn('[&_tr:last-child]:border-0', className)} {...props} />
  ),
);
TableBody.displayName = 'TableBody';

export const TableRow = forwardRef<HTMLTableRowElement, React.HTMLAttributes<HTMLTableRowElement>>(
  ({ className, ...props }, ref) => (
    <tr
      ref={ref}
      className={cn(
        'border-b border-hairline/70 transition-colors hover:bg-surface/60 data-[state=selected]:bg-brand-blue-soft',
        className,
      )}
      {...props}
    />
  ),
);
TableRow.displayName = 'TableRow';

export const TableHead = forwardRef<HTMLTableCellElement, React.ThHTMLAttributes<HTMLTableCellElement>>(
  ({ className, ...props }, ref) => (
    <th
      ref={ref}
      className={cn(
        'h-10 px-3 text-start align-middle text-[11px] font-semibold uppercase tracking-wide text-fg-subtle',
        className,
      )}
      {...props}
    />
  ),
);
TableHead.displayName = 'TableHead';

export const TableCell = forwardRef<HTMLTableCellElement, React.TdHTMLAttributes<HTMLTableCellElement>>(
  ({ className, ...props }, ref) => (
    <td ref={ref} className={cn('px-3 py-3 align-middle text-fg', className)} {...props} />
  ),
);
TableCell.displayName = 'TableCell';
