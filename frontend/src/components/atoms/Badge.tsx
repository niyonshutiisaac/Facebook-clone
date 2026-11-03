import { cn } from '@/lib/utils';

export function Badge({
  children,
  tone = 'default',
  className,
}: {
  children: React.ReactNode;
  tone?: 'default' | 'accent' | 'success' | 'danger';
  className?: string;
}) {
  const tones = {
    default: 'bg-elevated text-secondary',
    accent: 'bg-facebook/12 text-facebook',
    success: 'bg-facebook-green/15 text-facebook-green',
    danger: 'bg-facebook-red/15 text-facebook-red',
  } as const;

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-pill px-2 py-0.5 text-2xs font-semibold',
        tones[tone],
        className,
      )}
    >
      {children}
    </span>
  );
}
