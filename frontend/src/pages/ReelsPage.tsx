import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { AnimatePresence } from 'framer-motion';
import { useNavigate } from 'react-router-dom';
import { ReelCard } from '@/components/organisms/ReelCard';
import { ReelCommentsSheet } from '@/components/organisms/ReelCommentsSheet';
import { Skeleton } from '@/components/atoms/Skeleton';
import { Icon } from '@/components/atoms/Icon';
import {
  useFollowUser,
  useReactToReel,
  useReelsFeed,
  useShareReel,
  useViewReel,
} from '@/api/queries';
import { cn } from '@/lib/utils';
import type { ID, ReactionType, Reel } from '@/types/api';

/**
 * The reels surface: a dark, edge-to-edge vertical player.
 *
 * Three details separate this from "a scrolling list of videos":
 *
 *  1. Only the reel in view plays. A single IntersectionObserver owns which
 *     index is active; the cards never contend for the audio channel.
 *  2. Watch time is flushed when a reel stops being active, which is what the
 *     ranking service needs to compute completion rate.
 *  3. The container is the scroll root, so the page chrome (header, rails)
 *     never moves — the same trick Facebook uses for its three columns.
 */
export function ReelsPage() {
  const { data, isLoading, hasNextPage, fetchNextPage, isFetchingNextPage } = useReelsFeed();
  const navigate = useNavigate();

  const [active, setActive] = useState(0);
  const [muted, setMuted] = useState(true);
  const [commentsFor, setCommentsFor] = useState<ID | null>(null);

  const scroller = useRef<HTMLDivElement>(null);

  const viewReel = useViewReel();
  const reactToReel = useReactToReel();
  const shareReel = useShareReel();
  const followUser = useFollowUser();

  const reels = useMemo<Reel[]>(() => data?.pages.flatMap((page) => page.data) ?? [], [data]);

  // ---------------------------------------------------------- active reel
  useEffect(() => {
    const root = scroller.current;

    if (!root) return undefined;

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (!entry.isIntersecting) continue;

          const index = Number((entry.target as HTMLElement).dataset.reelIndex ?? '0');

          setActive(index);

          // Two from the end is early enough that the next page is ready
          // before the user reaches it.
          if (index >= reels.length - 2 && hasNextPage && !isFetchingNextPage) {
            void fetchNextPage();
          }
        }
      },
      { root, threshold: 0.6 },
    );

    for (const node of root.querySelectorAll('[data-reel-index]')) observer.observe(node);

    return () => observer.disconnect();
  }, [reels.length, hasNextPage, isFetchingNextPage, fetchNextPage]);

  const goTo = useCallback(
    (index: number) => {
      const root = scroller.current;

      if (!root) return;

      const target = root.querySelector<HTMLElement>(`[data-reel-index="${index}"]`);

      target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },
    [],
  );

  const step = useCallback(
    (delta: number) => {
      if (commentsFor) return;

      const next = Math.min(Math.max(active + delta, 0), reels.length - 1);

      goTo(next);
    },
    [active, reels.length, goTo, commentsFor],
  );

  // ------------------------------------------------------------- keyboard
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      const target = event.target as HTMLElement | null;

      if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA')) return;

      switch (event.key) {
        case 'ArrowDown':
        case 'j':
          event.preventDefault();
          step(1);
          break;
        case 'ArrowUp':
        case 'k':
          event.preventDefault();
          step(-1);
          break;
        case 'm':
          setMuted((value) => !value);
          break;
        case 'l': {
          const reel = reels[active];

          if (reel) reactToReel.mutate({ reelId: reel.id, reaction: reel.viewer_reaction ? null : 'like' });

          break;
        }
        case 'c': {
          const reel = reels[active];

          if (reel) setCommentsFor(reel.id);

          break;
        }
        case 'Escape':
          setCommentsFor(null);
          break;
        default:
          break;
      }
    };

    window.addEventListener('keydown', onKey);

    return () => window.removeEventListener('keydown', onKey);
  }, [step, reels, active, reactToReel]);

  const commenting = reels.find((reel) => reel.id === commentsFor) ?? null;

  return (
    <div className="mx-auto flex h-[calc(100dvh-9rem)] w-full max-w-[34rem] flex-col overflow-hidden rounded-card bg-[#0b0b0d] lg:h-[calc(100dvh-5.5rem)]">
      {/* ---------------------------------------------------------- header */}
      <header className="flex shrink-0 items-center gap-3 px-4 pb-2 pt-3">
        <div className="min-w-0">
          <h1 className="text-xl font-bold text-white">Reels</h1>
          <p className="truncate text-sm text-white/60">Short videos picked for you</p>
        </div>

        <span className="ml-auto flex items-center gap-1.5 rounded-pill bg-white/10 px-3 py-1.5 text-base font-semibold text-white">
          <Icon name="Sparkles" size={14} />
          For you
        </span>
      </header>

      {/* --------------------------------------------------------- player */}
      <div
        ref={scroller}
        className="relative min-h-0 flex-1 snap-y snap-mandatory overflow-y-auto overscroll-contain scrollbar-thin"
      >
        {isLoading ? (
          <div className="h-full w-full snap-start p-3">
            <Skeleton className="h-full w-full rounded-card" />
          </div>
        ) : (
          reels.map((reel, index) => (
            <section key={reel.id} data-reel-index={index} className="h-full w-full snap-start">
              <ReelCard
                reel={reel}
                active={index === active && !commentsFor}
                muted={muted}
                onToggleMute={() => setMuted((value) => !value)}
                onReact={(reelId, reaction: ReactionType | null) => reactToReel.mutate({ reelId, reaction })}
                onWatched={(reelId, seconds) => viewReel.mutate({ reelId, watchedSeconds: seconds })}
                onShare={(reelId) => shareReel.mutate(reelId)}
                onOpenComments={(reelId) => setCommentsFor(reelId)}
                onFollow={(userId, following) => followUser.mutate({ userId, following })}
                onOpenProfile={(username) => navigate(`/profile/${username}`)}
              />
            </section>
          ))
        )}

        {!hasNextPage && reels.length > 0 && (
          <section className="grid h-full w-full snap-start place-items-center bg-[#0b0b0d] px-10 text-center">
            <div>
              <p className="text-lg font-semibold text-white">That&apos;s the end of your reels.</p>
              <p className="mt-1 text-base text-white/60">
                Come back later — new clips land here as the people you follow post them.
              </p>
            </div>
          </section>
        )}

        {isFetchingNextPage && (
          <p className="absolute inset-x-0 bottom-3 text-center text-sm text-white/70">Loading more…</p>
        )}

        {/* Desktop navigation arrows. */}
        <button
          type="button"
          onClick={() => step(-1)}
          disabled={active === 0}
          aria-label="Previous reel"
          className={cn(
            'absolute right-3 top-[22%] z-20 hidden h-9 w-9 place-items-center rounded-full bg-white/15 text-white backdrop-blur-sm transition-opacity hover:bg-white/25 lg:grid',
            active === 0 && 'pointer-events-none opacity-0',
          )}
        >
          <Icon name="ChevronUp" size={20} />
        </button>

        <button
          type="button"
          onClick={() => step(1)}
          aria-label="Next reel"
          className="absolute bottom-[22%] right-3 z-20 hidden h-9 w-9 place-items-center rounded-full bg-white/15 text-white backdrop-blur-sm transition-opacity hover:bg-white/25 lg:grid"
        >
          <Icon name="ChevronDown" size={20} />
        </button>

        <AnimatePresence>
          {commenting && <ReelCommentsSheet reel={commenting} onClose={() => setCommentsFor(null)} />}
        </AnimatePresence>
      </div>
    </div>
  );
}
