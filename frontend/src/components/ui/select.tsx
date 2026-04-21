import { forwardRef } from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/cn';

/* Lightweight native <select> styled to match Input. We pull in
   @radix-ui/react-select only when we need a true combobox (keyboard nav,
   filtering). For Sprint 1 filters this is sufficient and ships zero JS. */
export const Select = forwardRef<HTMLSelectElement, React.SelectHTMLAttributes<HTMLSelectElement>>(
  ({ className, children, ...props }, ref) => (
    <div className="relative">
      <select
        ref={ref}
        className={cn(
          'flex h-10 w-full appearance-none rounded-lg bg-elevated pl-3 pr-9 text-sm text-fg ring-1 ring-inset ring-hairline transition-colors',
          'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-blue focus-visible:ring-offset-0',
          'disabled:cursor-not-allowed disabled:opacity-50',
          className,
        )}
        {...props}
      >
        {children}
      </select>
      <ChevronDown className="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-fg-subtle" />
    </div>
  ),
);
Select.displayName = 'Select';
