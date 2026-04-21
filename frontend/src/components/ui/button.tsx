import { forwardRef } from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/cn';

/* Button design language — refined for the 2026 dashboard aesthetic.
   • Primary: deep brand navy (matches sidebar surface) with a faint
     top-light gradient + 1px white inner highlight on top and a soft
     outer shadow. Reads premium and clinical, not enterprise-blue.
   • Hover lifts subtly with a cyan glow ring (brand accent), active
     presses 1px down so it feels tactile.
   • Secondary: hairline-bordered surface card, ghost text. Quieter,
     used for non-primary actions in a row of buttons.
   • Accent: cyan — kept for occasional emphasis (dialog confirms etc.). */
const buttonVariants = cva(
  'relative inline-flex items-center justify-center gap-2 whitespace-nowrap font-medium select-none transition-all duration-150 disabled:pointer-events-none disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-cyan/60 focus-visible:ring-offset-2 [&_svg]:pointer-events-none [&_svg]:shrink-0',
  {
    variants: {
      variant: {
        primary: [
          // Soft charcoal-slate (not full navy) — premium feel without
          // dominating the page. Subtle top highlight + low drop shadow.
          'bg-gradient-to-b from-[#475569] to-[#334155] text-white',
          'shadow-[inset_0_1px_0_rgb(255_255_255/0.10),0_1px_2px_rgb(15_23_42/0.10)]',
          'hover:from-[#52617A] hover:to-[#3B475C] hover:shadow-[inset_0_1px_0_rgb(255_255_255/0.12),0_2px_5px_rgb(6_182_212/0.18)]',
          'active:translate-y-px active:shadow-[inset_0_1px_2px_rgb(0_0_0/0.18)]',
        ].join(' '),
        secondary: [
          'bg-elevated text-fg ring-1 ring-inset ring-hairline',
          'shadow-[0_1px_0_rgb(255_255_255/0.5)_inset,0_1px_1px_rgb(15_23_42/0.04)]',
          'hover:bg-surface hover:ring-hairline-strong',
          'active:translate-y-px',
        ].join(' '),
        ghost: 'text-fg hover:bg-surface',
        accent: [
          'bg-gradient-to-b from-accent-cyan to-accent-cyan-hover text-white',
          'shadow-[inset_0_1px_0_rgb(255_255_255/0.18),0_1px_2px_rgb(6_182_212/0.25),0_3px_8px_-2px_rgb(6_182_212/0.35)]',
          'hover:brightness-[1.05] active:translate-y-px',
        ].join(' '),
        danger: [
          'bg-gradient-to-b from-[#F87171] to-danger text-white',
          'shadow-[inset_0_1px_0_rgb(255_255_255/0.15),0_1px_2px_rgb(239_68_68/0.25)]',
          'hover:brightness-[1.03] active:translate-y-px',
        ].join(' '),
        link: 'text-brand-navy underline-offset-4 hover:underline hover:text-accent-cyan-hover',
      },
      size: {
        sm: 'h-8 px-3 text-[13px] rounded-lg [&_svg]:size-3.5',
        md: 'h-9 px-4 text-sm rounded-lg [&_svg]:size-4',
        lg: 'h-11 px-5 text-sm rounded-lg [&_svg]:size-4',
        icon: 'h-9 w-9 rounded-lg [&_svg]:size-4',
      },
    },
    defaultVariants: { variant: 'primary', size: 'md' },
  },
);

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {
  asChild?: boolean;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'button';
    return (
      <Comp ref={ref} className={cn(buttonVariants({ variant, size, className }))} {...props} />
    );
  },
);
Button.displayName = 'Button';

export { buttonVariants };
