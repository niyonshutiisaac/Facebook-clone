import { useState } from 'react';
import { cn } from '@/lib/utils';
import { initials } from '@/lib/format';

export type AvatarSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl' | number;

const SIZES: Record<Exclude<AvatarSize, number>, string> = {
  xs: 'h-6 w-6 text-2xs',
  sm: 'h-8 w-8 text-xs',
  md: 'h-10 w-10 text-sm',
  lg: 'h-14 w-14 text-lg',
  xl: 'h-[10.5rem] w-[10.5rem] text-4xl',
};

export interface AvatarProps {
  src?: string | null;
  name: string;
  size?: AvatarSize;
  /** Green dot in the bottom-right corner (friends currently online). */
  online?: boolean;
  /** Round the square corners — profile photos use an 8px radius. */
  square?: boolean;
  className?: string;
  ring?: boolean;
}

/**
 * Avatar with a deterministic initials fallback.
 *
 * Every avatar renders a coloured circle with initials first and swaps to the
 * image once it has decoded, which means a slow or broken image never shows an
 * empty hole — the same behaviour Facebook has.
 */
export function Avatar({
  src,
  name,
  size = 'md',
  online = false,
  square = false,
  className,
  ring = false,
}: AvatarProps) {
  const [failed, setFailed] = useState(false);
  const [loaded, setLoaded] = useState(false);

  const resolvedSize = typeof size === 'number' ? { width: size, height: size } : {};
  const sizeClass = typeof size === 'number' ? '' : SIZES[size];
  const fontSize = typeof size === 'number' ? size * 0.4 : undefined;

  const showFallback = !src || failed || !loaded;

  return (
    <span
      className={cn('relative inline-flex shrink-0 select-none', sizeClass, className)}
      style={resolvedSize}
    >
      <span
        className={cn(
          'grid h-full w-full place-items-center overflow-hidden bg-elevated font-semibold text-secondary',
          square ? 'rounded-card' : 'rounded-full',
          ring && 'ring-2 ring-surface',
        )}
        style={fontSize ? { fontSize } : undefined}
        aria-hidden={!showFallback}
      >
        {showFallback ? initials(name) : null}
      </span>

      {src && !failed && (
        <img
          src={src}
          alt={name}
          loading="lazy"
          decoding="async"
          onLoad={() => setLoaded(true)}
          onError={() => setFailed(true)}
          className={cn(
            'absolute inset-0 h-full w-full object-cover transition-opacity duration-150',
            square ? 'rounded-card' : 'rounded-full',
            ring && 'ring-2 ring-surface',
            loaded ? 'opacity-100' : 'opacity-0',
          )}
        />
      )}

      {online && (
        <span
          className="absolute bottom-0 right-0 block rounded-full border-[2.5px] border-surface bg-[#31A24C]"
          style={{ width: '32%', height: '32%', minWidth: 8, minHeight: 8 }}
          aria-label="Active now"
          title="Active now"
        />
      )}
    </span>
  );
}
