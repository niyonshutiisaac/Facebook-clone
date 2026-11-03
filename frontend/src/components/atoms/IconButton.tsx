import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

export interface IconButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  label: string;
  children: ReactNode;
  size?: number;
  active?: boolean;
  /** Renders the badge (unread count) Facebook uses in the header. */
  badge?: number;
}

/**
 * Facebook's circular header/chrome buttons: a 40px hit area with a 36px
 * hover circle, used for search, messenger, notifications and account.
 */
export const IconButton = forwardRef<HTMLButtonElement, IconButtonProps>(function IconButton(
  { label, children, size = 40, active = false, badge, className, ...props },
  ref,
) {
  return (
    <button
      ref={ref}
      type="button"
      title={label}
      aria-label={label}
      className={cn(
        'relative grid place-items-center rounded-full transition-colors duration-100',
        active ? 'bg-facebook/10 text-facebook' : 'bg-elevated text-primary hover:bg-hover/[0.08]',
        className,
      )}
      style={{ width: size, height: size }}
      {...props}
    >
      {children}

      {badge !== undefined && badge > 0 && (
        <span
          className="absolute -right-0.5 -top-0.5 grid min-w-[18px] place-items-center rounded-full bg-facebook-red px-1 text-2xs font-semibold text-white"
          style={{ height: 18 }}
        >
          {badge > 99 ? '99+' : badge}
        </span>
      )}
    </button>
  );
});
