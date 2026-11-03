import { cn } from '@/lib/utils';

export function Divider({ className, spacing = 'md' }: { className?: string; spacing?: 'sm' | 'md' | 'lg' }) {
  const spacings = { sm: 'my-1', md: 'my-2', lg: 'my-4' } as const;

  return <hr className={cn('h-px border-0 bg-divider', spacings[spacing], className)} />;
}
