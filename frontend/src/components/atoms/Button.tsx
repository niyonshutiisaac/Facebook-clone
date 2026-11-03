import { forwardRef, type ButtonHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';
import { Spinner } from './Spinner';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'success' | 'text';
type Size = 'sm' | 'md' | 'lg';

const VARIANTS: Record<Variant, string> = {
  primary: 'bg-facebook-action text-white hover:bg-facebook-action-hover',
  secondary: 'bg-elevated text-primary hover:bg-hover/[0.08]',
  ghost: 'bg-transparent text-secondary hover:bg-hover/[0.05] active:bg-hover/[0.1]',
  danger: 'bg-facebook-red text-white hover:bg-facebook-red-hover',
  success: 'bg-facebook-green text-white hover:bg-facebook-green-hover',
  text: 'bg-transparent text-accent hover:bg-hover/[0.05]',
};

const SIZES: Record<Size, string> = {
  sm: 'h-8 px-3 text-sm',
  md: 'h-9 px-4 text-base',
  lg: 'h-11 px-6 text-lg',
};

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: Size;
  loading?: boolean;
  fullWidth?: boolean;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  { variant = 'secondary', size = 'md', loading = false, fullWidth, className, children, disabled, ...props },
  ref,
) {
  return (
    <button
      ref={ref}
      className={cn(
        'inline-flex select-none items-center justify-center gap-2 rounded-control font-semibold',
        'transition-all duration-100 ease-fb active:scale-[0.98]',
        'disabled:pointer-events-none disabled:opacity-50',
        VARIANTS[variant],
        SIZES[size],
        fullWidth && 'w-full',
        className,
      )}
      disabled={disabled || loading}
      {...props}
    >
      {loading && <Spinner size={size === 'lg' ? 20 : 16} />}
      {children}
    </button>
  );
});
