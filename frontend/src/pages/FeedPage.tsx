import { useEffect, useRef, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { PostComposer } from '@/components/organisms/PostComposer';
import { PostCard } from '@/components/organisms/PostCard';
import { StoryTray } from '@/components/molecules/StoryTray';
import { StoryViewer } from '@/components/organisms/StoryViewer';
import { PostSkeleton, Skeleton } from '@/components/atoms/Skeleton';
import { Icon } from '@/components/atoms/Icon';
import {
  useFeed,
  useReactToStory,
  useReplyToStory,
  useStoryTrays,
  useViewStory,
} from '@/api/queries';
import { cn } from '@/lib/utils';
import type { ReactionType } from '@/types/api';

/**
 * The news feed.
 *
 * Pages are appended as the sentinel scrolls into view; react-virtuoso is not
 * used here because Facebook's feed is a *finite, curated* list per session
 * rather than an infinite one — the ranked cursor model in the backend returns
 * ~500 candidates, which a few pages of infinite scroll covers comfortably.
 */
export function FeedPage() {
  const { data, isLoading, isFetchingNextPage, hasNextPage, fetchNextPage, refetch, isRefetching } = useFeed();
  const { data: trays, isLoading: traysLoading } = useStoryTrays();

  const sentinel = useRef<HTMLDivElement>(null);
  const [showComposer, setShowComposer] = useState(false);

  // Story viewer: which person, and which item inside their tray.
  const [viewer, setViewer] = useState<{ tray: number; story: number } | null>(null);

  const viewStory = useViewStory();
  const reactToStory = useReactToStory();
  const replyToStory = useReplyToStory();

  // Infinite scroll: the sentinel sits two cards from the bottom so the next
  // page is usually ready before the user needs it.
  useEffect(() => {
    const node = sentinel.current;

    if (!node) return;

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasNextPage && !isFetchingNextPage) {
          void fetchNextPage();
        }
      },
      { rootMargin: '600px 0px' },
    );

    observer.observe(node);

    return () => observer.disconnect();
  }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

  // Reveal the inline composer once the first paint settles: it is the heaviest
  // component on the page and should not block the feed.
  useEffect(() => {
    const timer = setTimeout(() => setShowComposer(true), 60);

    return () => clearTimeout(timer);
  }, []);

  const posts = data?.pages.flatMap((page) => page.data) ?? [];

  return (
    <div className="mx-auto w-full max-w-feed px-2 sm:px-0">
      <StoryTray
        trays={trays ?? []}
        loading={traysLoading}
        onOpen={(index) => setViewer({ tray: index, story: 0 })}
      />

      {showComposer ? (
        <PostComposer onCreated={() => void refetch()} />
      ) : (
        <Skeleton className="mb-4 h-[7.5rem] w-full rounded-card" />
      )}

      {isRefetching && !isLoading && (
        <p className="mb-2 text-center text-sm text-secondary">Updating your feed…</p>
      )}

      {isLoading ? (
        <div className="space-y-4">
          <PostSkeleton />
          <PostSkeleton />
        </div>
      ) : (
        <div className="space-y-4">
          <AnimatePresence initial={false}>
            {posts.map((post) => (
              <motion.div
                key={post.id}
                layout="position"
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.2, ease: [0.08, 0.52, 0.37, 0.98] }}
              >
                <PostCard post={post} />
              </motion.div>
            ))}
          </AnimatePresence>

          {isFetchingNextPage && <PostSkeleton />}

          <div ref={sentinel} aria-hidden="true" className="h-4" />

          {!hasNextPage && posts.length > 0 && (
            <p className="py-6 text-center text-md text-secondary">
              You&apos;re all caught up. Check back later for more.
            </p>
          )}
        </div>
      )}

      <AnimatePresence>
        {viewer && trays && (
          <StoryViewer
            trays={trays}
            trayIndex={viewer.tray}
            storyIndex={viewer.story}
            onNavigateTray={(index) => setViewer({ tray: index, story: 0 })}
            onNavigateStory={(index) => setViewer({ tray: viewer.tray, story: index })}
            onClose={() => setViewer(null)}
            onView={(storyId) => viewStory.mutate(storyId)}
            onReact={(storyId, reaction: ReactionType) => reactToStory.mutate({ storyId, reaction })}
            onReply={(storyId, body) => replyToStory.mutate({ storyId, body })}
          />
        )}
      </AnimatePresence>
    </div>
  );
}

/** The "N new posts" pill that floats under the header. */
export function NewPostsPill({ count, onClick }: { count: number; onClick: () => void }) {
  if (count <= 0) return null;

  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'sticky top-[calc(3.5rem+0.5rem)] z-sticky mx-auto mb-3 flex items-center gap-2',
        'rounded-pill bg-facebook-action px-4 py-2 text-base font-semibold text-white shadow-modal',
        'animate-slide-up hover:bg-facebook-action-hover',
      )}
    >
      <Icon name="ArrowUp" size={16} />
      {count} new {count === 1 ? 'post' : 'posts'}
    </button>
  );
}
