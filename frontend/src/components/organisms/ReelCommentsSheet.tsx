import { motion } from 'framer-motion';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import { Skeleton } from '@/components/atoms/Skeleton';
import { CommentComposer } from '@/components/molecules/CommentComposer';
import { useAuthStore } from '@/store/auth';
import { compactNumber, relativeTime } from '@/lib/format';
import { useCreateReelComment, useReelComments } from '@/api/queries';
import type { Reel } from '@/types/api';

/**
 * Reel comments live in a drawer over the lower half of the player rather than
 * on a page of their own — the reel keeps playing behind them, which is the
 * whole point of the surface.
 */
export function ReelCommentsSheet({ reel, onClose }: { reel: Reel; onClose: () => void }) {
  const user = useAuthStore((state) => state.user);
  const { data, isLoading } = useReelComments(reel.id);
  const create = useCreateReelComment(reel.id);

  const comments = data?.data ?? [];

  return (
    <motion.aside
      initial={{ y: '100%' }}
      animate={{ y: 0 }}
      exit={{ y: '100%' }}
      transition={{ type: 'spring', stiffness: 420, damping: 38 }}
      className="absolute inset-x-0 bottom-0 z-30 flex max-h-[68%] flex-col rounded-t-2xl bg-surface shadow-modal"
      role="dialog"
      aria-label={`Comments on ${reel.creator.name}'s reel`}
    >
      <div className="flex items-center justify-between px-4 pb-2 pt-3">
        <h2 className="text-base font-semibold">
          {compactNumber(reel.comments_count)} {reel.comments_count === 1 ? 'comment' : 'comments'}
        </h2>

        <button
          type="button"
          onClick={onClose}
          aria-label="Close comments"
          className="grid h-8 w-8 place-items-center rounded-full text-secondary hover:bg-hover/[0.08]"
        >
          <Icon name="X" size={20} />
        </button>
      </div>

      <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 pb-2 scrollbar-thin">
        {isLoading &&
          Array.from({ length: 3 }, (_, i) => (
            <div key={i} className="flex gap-2">
              <Skeleton className="h-8 w-8 rounded-full" />
              <div className="flex-1 space-y-1.5">
                <Skeleton className="h-3 w-28" />
                <Skeleton className="h-8 w-full rounded-bubble" />
              </div>
            </div>
          ))}

        {!isLoading && comments.length === 0 && (
          <p className="py-6 text-center text-base text-secondary">No comments yet. Be the first.</p>
        )}

        {comments.map((comment) => (
          <article key={comment.id} className="flex gap-2">
            <Avatar src={comment.author.avatar_url} name={comment.author.name} size={32} />

            <div className="min-w-0 flex-1">
              <div className="rounded-bubble bg-subtle px-3 py-1.5">
                <p className="text-base font-semibold leading-tight">{comment.author.name}</p>
                <p className="whitespace-pre-wrap break-words text-md">{comment.body}</p>
              </div>

              <p className="mt-1 pl-1 text-xs text-secondary">
                {relativeTime(comment.created_at)}
                {comment.likes_count > 0 && (
                  <>
                    {' · '}
                    {compactNumber(comment.likes_count)} likes
                  </>
                )}
              </p>
            </div>
          </article>
        ))}
      </div>

      {user && (
        <div className="border-t border-divider px-3 py-2">
          <CommentComposer
            author={user}
            compact
            placeholder="Add a comment…"
            submitting={create.isPending}
            onSubmit={(body) => create.mutate(body)}
          />
        </div>
      )}
    </motion.aside>
  );
}
