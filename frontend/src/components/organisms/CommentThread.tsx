import { useState } from 'react';
import { Link } from 'react-router-dom';
import { AnimatePresence, motion } from 'framer-motion';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import { Skeleton } from '@/components/atoms/Skeleton';
import { CommentComposer } from '@/components/molecules/CommentComposer';
import { REACTION_COLOR, reactionIcon, ReactionPicker, useHoverIntent } from '@/components/molecules/Reactions';
import { Tooltip } from '@/components/molecules/Tooltip';
import { relativeTime, compactNumber } from '@/lib/format';
import { useAuthStore } from '@/store/auth';
import { cn } from '@/lib/utils';
import {
  useComments,
  useCreateComment,
  useReplies,
  useToggleReaction,
} from '@/api/queries';
import type { Comment, ID } from '@/types/api';

/** Facebook caps visible nesting at three levels (0, 1, 2). */
export const MAX_DEPTH = 2;

/**
 * The comment thread under a post.
 *
 * Top-level comments are sorted by Facebook's own rule — most relevant first,
 * which in practice means reactions, then replies, then recency. Replies are
 * fetched lazily when a thread is expanded, so opening a post does not pull the
 * whole tree.
 */
export function CommentThread({ postId, expanded, onToggle }: { postId: ID; expanded: boolean; onToggle: () => void }) {
  const { data, isLoading } = useComments(postId);

  const comments = data?.pages.flatMap((page) => page.data) ?? [];
  const visible = expanded ? comments : comments.slice(-2);

  return (
    <div className="border-t border-divider px-4 py-3">
      <div className="mb-3 flex items-center justify-between">
        <div className="flex items-center gap-1 text-secondary">
          <Tooltip content="Most relevant">
            <button type="button" className="rounded-control px-1 text-sm font-semibold hover:bg-hover/[0.05]">
              Most relevant
            </button>
          </Tooltip>
        </div>
      </div>

      {comments.length > 2 && (
        <button
          type="button"
          onClick={onToggle}
          className="mb-3 flex items-center gap-1.5 text-base font-semibold text-secondary hover:underline"
        >
          <Icon name={expanded ? 'ChevronUp' : 'ChevronDown'} size={16} />
          {expanded ? 'Hide comments' : `View ${comments.length - 2} more comments`}
        </button>
      )}

      {isLoading ? (
        <div className="space-y-4">
          {Array.from({ length: 3 }).map((_, index) => (
            <div key={index} className="flex gap-2">
              <Skeleton className="h-8 w-8 rounded-full" />
              <div className="flex-1 space-y-2">
                <Skeleton className="h-8 w-48 rounded-bubble" />
              </div>
            </div>
          ))}
        </div>
      ) : (
        <ul className={cn('space-y-3', expanded && 'animate-fade-in')}>
          {visible.map((comment) => (
            <CommentItem key={comment.id} comment={comment} postId={postId} />
          ))}
        </ul>
      )}
    </div>
  );
}

function CommentItem({ comment, postId }: { comment: Comment; postId: ID }) {
  const { user } = useAuthStore();
  const [showReply, setShowReply] = useState(false);
  const [showReplies, setShowReplies] = useState(false);

  const create = useCreateComment(postId);
  const toggleReaction = useToggleReaction();
  const { intent, onEnter, onLeave } = useHoverIntent(400);

  const { data: replies = [], isLoading } = useReplies(comment.id, showReplies);

  const liked = comment.viewer_reaction !== null && comment.viewer_reaction !== undefined;

  return (
    <li>
      <div className="flex gap-2">
        <Link to={`/profile/${comment.author.username}`}>
          <Avatar src={comment.author.avatar_url} name={comment.author.name} size="sm" />
        </Link>

        <div className="min-w-0 flex-1">
          <div
            className={cn(
              'relative inline-block rounded-bubble bg-subtle px-3 py-2',
              comment.is_deleted && 'italic text-secondary',
            )}
          >
            <Link
              to={`/profile/${comment.author.username}`}
              className="text-sm font-semibold hover:underline"
            >
              {comment.author.name}
            </Link>

            <p className="whitespace-pre-wrap break-words text-md">{comment.body}</p>
          </div>

          <div className="mt-1 flex items-center gap-3 pl-3 text-sm font-semibold text-secondary">
            <div
              className="relative"
              onMouseEnter={onEnter}
              onMouseLeave={onLeave}
            >
              <button
                type="button"
                onClick={() =>
                  toggleReaction.mutate({
                    targetType: 'comment',
                    targetId: comment.id,
                    reaction: 'like',
                  })
                }
                className={cn('transition-colors', liked && 'text-facebook')}
                aria-pressed={liked}
              >
                Like
              </button>

              <ReactionPicker
                open={intent}
                current={comment.viewer_reaction ?? null}
                onSelect={(reaction) =>
                  toggleReaction.mutate({ targetType: 'comment', targetId: comment.id, reaction })
                }
              />
            </div>

            <button type="button" onClick={() => setShowReply((value) => !value)}>
              Reply
            </button>

            {liked && (
              <span
                className="inline-flex items-center gap-1"
                style={{ color: REACTION_COLOR[comment.viewer_reaction ?? 'like'] }}
              >
                <Icon name={reactionIcon(comment.viewer_reaction ?? 'like')} size={14} />
              </span>
            )}

            <span className="font-normal text-tertiary">{relativeTime(comment.created_at)}</span>
          </div>

          {comment.likes_count > 0 && (
            <span className="ml-3 inline-flex items-center gap-1 rounded-pill bg-surface px-1.5 py-0.5 text-2xs font-semibold text-secondary shadow-card">
              <Icon name="like-fill" size={12} className="text-facebook" />
              {compactNumber(comment.likes_count)}
            </span>
          )}

          {showReply && user && (
            <div className="mt-2">
              <CommentComposer
                author={user}
                autoFocus
                compact
                placeholder={`Reply to ${comment.author.name.split(' ')[0]}…`}
                submitting={create.isPending}
                onCancel={() => setShowReply(false)}
                onSubmit={(body) => {
                  create.mutate(
                    { body, parentId: comment.id },
                    {
                      onSuccess: () => {
                        setShowReply(false);
                        setShowReplies(true);
                      },
                    },
                  );
                }}
              />
            </div>
          )}

          {(comment.replies_count ?? 0) > 0 && (
            <div className="mt-1">
              <button
                type="button"
                onClick={() => setShowReplies((value) => !value)}
                className="flex items-center gap-1.5 pl-3 text-sm font-semibold text-secondary hover:underline"
              >
                <Icon name={showReplies ? 'ChevronUp' : 'CornerDownRight'} size={14} />
                {showReplies ? 'Hide' : `View`} {comment.replies_count}{' '}
                {comment.replies_count === 1 ? 'reply' : 'replies'}
              </button>

              <AnimatePresence initial={false}>
                {showReplies && (
                  <motion.ul
                    initial={{ height: 0, opacity: 0 }}
                    animate={{ height: 'auto', opacity: 1 }}
                    exit={{ height: 0, opacity: 0 }}
                    transition={{ duration: 0.2, ease: [0.08, 0.52, 0.37, 0.98] }}
                    className="mt-2 space-y-3 overflow-hidden border-l-2 border-divider pl-3"
                  >
                    {isLoading ? (
                      <Skeleton className="h-8 w-40 rounded-bubble" />
                    ) : (
                      replies.map((reply) => (
                        <CommentItem key={reply.id} comment={reply} postId={postId} />
                      ))
                    )}
                  </motion.ul>
                )}
              </AnimatePresence>
            </div>
          )}
        </div>
      </div>
    </li>
  );
}

/** The composer pinned under the thread (only shown once the thread is open). */
export function ThreadComposer({ postId }: { postId: ID }) {
  const { user } = useAuthStore();
  const create = useCreateComment(postId);

  if (!user) return null;

  return (
    <div className="px-4 py-3">
      <CommentComposer
        author={user}
        placeholder="Write a comment…"
        submitting={create.isPending}
        onSubmit={(body) => create.mutate({ body, parentId: null })}
      />
    </div>
  );
}
