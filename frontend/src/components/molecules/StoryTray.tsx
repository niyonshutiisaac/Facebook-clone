import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import { Skeleton } from '@/components/atoms/Skeleton';
import { cn } from '@/lib/utils';
import type { StoryTray as StoryTrayType } from '@/types/api';

/**
 * The horizontal story tray above the feed.
 *
 * Cards scroll sideways with a snap; the viewer's own card is a "Create story"
 * tile. Unseen trays get the blue ring, seen ones a grey ring — the same visual
 * language Facebook uses.
 */
export function StoryTray({
  trays,
  loading = false,
  onOpen,
}: {
  trays: StoryTrayType[];
  loading?: boolean;
  onOpen: (index: number) => void;
}) {
  if (loading) {
    return (
      <div className="mb-4 flex gap-2">
        {Array.from({ length: 7 }).map((_, index) => (
          <div key={index} className="w-[112px] shrink-0">
            <Skeleton className="h-[200px] w-full rounded-card" />
          </div>
        ))}
      </div>
    );
  }

  return (
    <div className="mb-4 flex gap-2 overflow-x-auto pb-1 scrollbar-none">
      {/* Create story */}
      <button
        type="button"
        aria-label="Create a story"
        className="group relative h-[200px] w-[112px] shrink-0 overflow-hidden rounded-card bg-surface shadow-card"
      >
        <div className="h-[72%] w-full overflow-hidden bg-subtle">
          <Icon name="Plus" size={0} />
          <span className="grid h-full w-full place-items-center text-secondary">
            <span className="grid h-10 w-10 place-items-center rounded-full bg-facebook text-white">
              <Icon name="Plus" size={22} />
            </span>
          </span>
        </div>

        <span className="absolute bottom-0 left-0 right-0 grid h-[28%] place-items-center bg-surface px-2 text-center text-sm font-semibold">
          Create story
        </span>
      </button>

      {trays.map((tray, index) => (
        <button
          key={tray.user.id}
          type="button"
          aria-label={`View ${tray.user.name}'s story`}
          onClick={() => onOpen(index)}
          className="group relative h-[200px] w-[112px] shrink-0 overflow-hidden rounded-card bg-surface shadow-card"
        >
          <img
            src={tray.stories[0]?.media?.url ?? ''}
            alt=""
            loading="lazy"
            className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.04]"
          />

          <span className="absolute inset-0 bg-gradient-to-b from-black/25 via-transparent to-black/60" />

          <span
            className={cn(
              'absolute left-2 top-2 grid h-10 w-10 place-items-center rounded-full',
              tray.has_unseen ? 'bg-facebook p-[2px]' : 'bg-transparent p-[2px]',
            )}
          >
            <span className="grid h-full w-full place-items-center rounded-full bg-surface p-[2px]">
              <Avatar src={tray.user.avatar_url} name={tray.user.name} size={32} />
            </span>
          </span>

          <span className="absolute bottom-2 left-2 right-2 truncate text-left text-sm font-semibold text-white drop-shadow">
            {tray.user.id === tray.user.username ? 'Your story' : tray.user.name.split(' ')[0]}
          </span>
        </button>
      ))}
    </div>
  );
}
