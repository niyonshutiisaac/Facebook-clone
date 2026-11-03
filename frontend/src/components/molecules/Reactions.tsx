import { useEffect, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Icon, type IconName } from '@/components/atoms/Icon';
import { cn } from '@/lib/utils';
import type { ReactionType } from '@/types/api';

export const REACTIONS: Array<{ type: ReactionType; label: string; icon: IconName; color: string }> = [
  { type: 'like', label: 'Like', icon: 'like-fill', color: '#0866FF' },
  { type: 'love', label: 'Love', icon: 'love-fill', color: '#F33E58' },
  { type: 'care', label: 'Care', icon: 'care-fill', color: '#F7B125' },
  { type: 'haha', label: 'Haha', icon: 'haha-fill', color: '#F7B125' },
  { type: 'wow', label: 'Wow', icon: 'wow-fill', color: '#F7B125' },
  { type: 'sad', label: 'Sad', icon: 'sad-fill', color: '#F7B125' },
  { type: 'angry', label: 'Angry', icon: 'angry-fill', color: '#E9710F' },
];

export const REACTION_COLOR: Record<ReactionType, string> = Object.fromEntries(
  REACTIONS.map((r) => [r.type, r.color]),
) as Record<ReactionType, string>;

export const reactionIcon = (type: ReactionType | null | undefined): IconName =>
  REACTIONS.find((r) => r.type === type)?.icon ?? 'like-fill';

export const reactionLabel = (type: ReactionType): string =>
  REACTIONS.find((r) => r.type === type)?.label ?? 'Like';

/**
 * The reaction tray that flies out on hover (desktop) and long-press (mobile).
 *
 * Facebook delays the tray by 400ms on hover so a cursor passing over the Like
 * button does not trigger it; we do the same.
 */
export function ReactionPicker({
  onSelect,
  current,
  open,
  onOpenChange,
}: {
  onSelect: (reaction: ReactionType) => void;
  current?: ReactionType | null;
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
}) {
  const [internalOpen, setInternalOpen] = useState(false);
  const isOpen = open ?? internalOpen;
  const setOpen = onOpenChange ?? setInternalOpen;

  return (
    <AnimatePresence>
      {isOpen && (
        <motion.div
          initial={{ opacity: 0, y: 8, scale: 0.9 }}
          animate={{ opacity: 1, y: 0, scale: 1 }}
          exit={{ opacity: 0, y: 4, scale: 0.95 }}
          transition={{ type: 'spring', stiffness: 520, damping: 34 }}
          className="absolute bottom-full left-0 z-popover mb-2 flex origin-bottom-left items-end gap-1 rounded-pill bg-surface px-2 py-1.5 shadow-dropdown"
          onMouseLeave={() => setOpen(false)}
          role="menu"
          aria-label="Choose a reaction"
        >
          {REACTIONS.map((reaction, index) => (
            <motion.button
              key={reaction.type}
              type="button"
              role="menuitem"
              aria-label={reaction.label}
              title={reaction.label}
              initial={{ opacity: 0, y: 12, scale: 0.5 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              transition={{ delay: index * 0.02, type: 'spring', stiffness: 600, damping: 22 }}
              whileHover={{ scale: 1.35, y: -8 }}
              whileTap={{ scale: 0.95 }}
              onClick={() => {
                onSelect(reaction.type);
                setOpen(false);
              }}
              className={cn(
                'grid h-9 w-9 place-items-center rounded-full transition-transform',
                current === reaction.type && 'ring-2 ring-facebook',
              )}
              style={{ color: reaction.color }}
            >
              <Icon name={reaction.icon} size={36} />
            </motion.button>
          ))}
        </motion.div>
      )}
    </AnimatePresence>
  );
}

/**
 * The overlapping emoji stack above the Like/Comment row.
 */
export function ReactionStack({
  types,
  total,
  className,
}: {
  types: ReactionType[];
  total: number;
  className?: string;
}) {
  return (
    <div className={cn('flex items-center gap-0.5', className)}>
      <div className="flex">
        {types.slice(0, 3).map((type, index) => (
          <span
            key={type}
            className="grid h-[18px] w-[18px] place-items-center rounded-full ring-2 ring-surface"
            style={{
              color: REACTION_COLOR[type],
              marginLeft: index === 0 ? 0 : -6,
              zIndex: 3 - index,
              backgroundColor: 'rgb(var(--surface))',
            }}
          >
            <Icon name={reactionIcon(type)} size={18} />
          </span>
        ))}
      </div>

      {total > 0 && (
        <span className="ml-1 text-sm text-secondary" title={`${total} reactions`}>
          {total}
        </span>
      )}
    </div>
  );
}

/**
 * "Ava Chen and 14 others" — the tooltip Facebook shows over the reaction
 * count. Built from whatever the API gives us.
 */
export function ReactionSummaryLabel({
  summary,
  total,
}: {
  summary: Partial<Record<ReactionType, number>>;
  total: number;
}) {
  const [top] = Object.entries(summary).sort((a, b) => (b[1] ?? 0) - (a[1] ?? 0));

  return <>{top ? `${top[1]} ${reactionLabel(top[0] as ReactionType)}` : total}</>;
}

/** Hover-intent helper: opens the tray only after the pointer settles. */
export function useHoverIntent(delay = 400) {
  const [intent, setIntent] = useState(false);
  const [timer, setTimer] = useState<ReturnType<typeof setTimeout> | null>(null);

  const onEnter = () => {
    if (timer) clearTimeout(timer);
    setTimer(setTimeout(() => setIntent(true), delay));
  };

  const onLeave = () => {
    if (timer) clearTimeout(timer);
    setIntent(false);
  };

  useEffect(() => () => {
    if (timer) clearTimeout(timer);
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  return { intent, onEnter, onLeave };
}
