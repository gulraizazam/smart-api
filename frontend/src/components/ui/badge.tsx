import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/cn';

const badgeVariants = cva(
  'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset',
  {
    variants: {
      variant: {
        neutral: 'bg-surface text-fg-muted ring-hairline',
        brand: 'bg-brand-blue-soft text-brand-blue ring-brand-blue/20',
        accent: 'bg-accent-cyan-soft text-accent-cyan-hover ring-accent-cyan/20',
        success: 'bg-success-soft text-success ring-success/20',
        warning: 'bg-warning-soft text-warning ring-warning/30',
        danger: 'bg-danger-soft text-danger ring-danger/20',
        outline: 'bg-transparent text-fg ring-hairline-strong',
      },
    },
    defaultVariants: { variant: 'neutral' },
  },
);

export interface BadgeProps
  extends React.HTMLAttributes<HTMLSpanElement>,
    VariantProps<typeof badgeVariants> {
  dot?: boolean;
}

export function Badge({ className, variant, dot, children, ...props }: BadgeProps) {
  return (
    <span className={cn(badgeVariants({ variant, className }))} {...props}>
      {dot && <span className="size-1.5 rounded-full bg-current" />}
      {children}
    </span>
  );
}

export { badgeVariants };
