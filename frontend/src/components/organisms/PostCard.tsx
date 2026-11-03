import { useState } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon, type IconName } from '@/components/atoms/Icon';
import { Menu } from '@/components/molecules/Menu';
import { Lightbox, MediaGrid } from '@/components/molecules/MediaGrid';
import { LinkPreviewCard } from '@/components/molecules/LinkPreviewCard';
import { PollCard } from '@/components/molecules/PollCard';
import { RichText } from '@/components/molecules/RichText';
import { Tooltip } from '@/components/molecules/Tooltip';
import {
  REACTION_COLOR,
  ReactionPicker,
  ReactionStack,
  reactionIcon,
  reactionLabel,
  useHoverIntent,
} from '@/components/molecules/Reactions';
import { CommentThread, ThreadComposer } from './CommentThread';
import { compactNumber, relativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useToggleReaction, useToggleSave } from '@/api/queries';
import { AUDIENCES } from '@/config/audiences';
import type { Audience, Media, Post, ReactionType } from '@/types/api';

const AUDIENCE_ICON: Record<Audience, IconName> = {
  public: 'Globe',
  friends: 'Users',
  friends_except: 'UserMinus',
  specific: 'UserCheck',
  only_me: 'Lock',
};

/**
 * A single post.
 *
 * Everything the feed shows for one item lives here: author header, body with
 * See more, attachments, the reaction/action row, and the comment thread. The
 * card is deliberately a *dumb* component — all mutations go through the query
 * hooks, so the same component works for the feed, a profile timeline and a
 * permalink.
 */
export function PostCard({ post, defaultExpanded = false }: { post: Post; defaultExpanded?: boolean }) {
  const [expanded, setExpanded] = useState(defaultExpanded);
  const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);
  const [clamped, setClamped] = useState(true);

  const toggleReaction = useToggleReaction();
  const toggleSave = useToggleSave();
  const { intent, onEnter, onLeave } = useHoverIntent(420);

  const current = post.viewer_reaction ?? null;
  const audienceIcon = AUDIENCE_ICON[post.audience] ?? 'Users';

  const body = post.body ?? '';
  const needsClamp = body.length > 320;
  const types = post.top_reactions.length > 0 ? post.top_reactions : ['like' as ReactionType];

  return (
    <motion.article
      layout="position"
      className="card"
      aria-label={`Post by ${post.author.name}`}
    >
      {/* ------------------------------------------------------------ header */}
      <header className="flex items-start gap-2 px-4 pt-3">
        <Link to={`/profile/${post.author.username}`} className="shrink-0">
          <Avatar src={post.author.avatar_url} name={post.author.name} size="md" online={post.author.is_online} />
        </Link>

        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-1">
            <Link
              to={`/profile/${post.author.username}`}
              className="truncate text-base font-semibold hover:underline"
            >
              {post.author.name}
            </Link>

            {post.author.is_verified && (
              <Icon name="BadgeCheck" size={16} className="shrink-0 text-facebook" aria-label="Verified" />
            )}

            {post.feelings && post.feelings.length > 0 && (
              <span className="truncate text-base text-secondary">
                {' '}is feeling{' '}
                <span className="font-medium">{post.feelings[0]!.label}</span> {post.feelings[0]!.emoji}
              </span>
            )}

            {post.type === 'life_event' && (
              <span className="truncate text-base text-secondary"> updated their life event.</span>
            )}

            {post.type === 'avatar_update' && (
              <span className="truncate text-base text-secondary"> updated their profile picture.</span>
            )}

            {post.type === 'cover_update' && (
              <span className="truncate text-base text-secondary"> updated their cover photo.</span>
            )}

            {post.group && (
              <span className="truncate text-base text-secondary">
                {' '}·{' '}
                <Link to={`/groups/${post.group.id}`} className="font-medium hover:underline">
                  {post.group.name}
                </Link>
              </span>
            )}
          </div>

          <div className="flex items-center gap-1 text-sm text-secondary">
            <Tooltip content={relativeTime(post.created_at)}>
              <Link to={`/post/${post.id}`} className="hover:underline">
                {relativeTime(post.created_at)}
              </Link>
            </Tooltip>

            <span aria-hidden="true">·</span>

            <Tooltip content={AUDIENCES.find((a) => a.value === post.audience)?.description}>
              <span className="grid place-items-center">
                <Icon name={audienceIcon} size={13} />
              </span>
            </Tooltip>

            {post.location && (
              <>
                <span aria-hidden="true">·</span>
                <span className="truncate">{post.location}</span>
              </>
            )}
          </div>
        </div>

        <div className="flex items-center gap-1">
          <Tooltip content="Save post">
            <button
              type="button"
              onClick={() => toggleSave.mutate(post.id)}
              aria-pressed={post.is_saved}
              aria-label={post.is_saved ? 'Remove from saved' : 'Save post'}
              className={cn(
                'grid h-9 w-9 place-items-center rounded-full transition-colors hover:bg-hover/[0.08]',
                post.is_saved && 'text-facebook',
              )}
            >
              <Icon name="Bookmark" size={20} fill={post.is_saved ? 'currentColor' : 'none'} />
            </button>
          </Tooltip>

          <Menu
            items={[
              { label: 'Copy link', icon: 'Link2', onSelect: () => navigator.clipboard?.writeText(`${location.origin}/post/${post.id}`) },
              { label: 'Embed', icon: 'Code', onSelect: () => undefined },
              { label: 'Who can see this post?', icon: 'Eye', onSelect: () => undefined },
              { divider: true, label: '' },
              { label: 'Hide post', icon: 'EyeOff', onSelect: () => undefined },
              { label: 'Snooze for 30 days', icon: 'Clock', onSelect: () => undefined },
              { divider: true, label: '' },
              { label: 'Report post', icon: 'Flag', danger: true, onSelect: () => undefined },
            ]}
            trigger={
              <button
                type="button"
                aria-label="Post options"
                className="grid h-9 w-9 place-items-center rounded-full text-secondary transition-colors hover:bg-hover/[0.08]"
              >
                <Icon name="Ellipsis" size={20} />
              </button>
            }
          />
        </div>
      </header>

      {/* -------------------------------------------------------------- body */}
      {body && (
        <div className="px-4 pb-1 pt-2">
          <RichText
            text={body}
            className={cn(
              'whitespace-pre-wrap break-words text-md',
              clamped && needsClamp && 'clamp-4',
            )}
          />

          {needsClamp && (
            <button
              type="button"
              onClick={() => setClamped((value) => !value)}
              className="mt-0.5 text-base font-semibold text-secondary hover:underline"
            >
              {clamped ? 'See more' : 'See less'}
            </button>
          )}
        </div>
      )}

      {/* -------------------------------------------------------- attachments */}
      <div className="px-4">
        {post.media.length > 0 && <MediaGrid media={post.media} onOpen={setLightboxIndex} />}

        {post.link?.preview && <LinkPreviewCard preview={post.link.preview} url={post.link.url} />}

        {post.poll && (
          <PollCard
            poll={post.poll}
            onVote={() => undefined}
          />
        )}

        {post.shared_post && (
          <div className="mt-3 overflow-hidden rounded-card border border-divider">
            <div className="flex items-center gap-2 px-3 pt-3">
              <Avatar
                src={post.shared_post.author.avatar_url}
                name={post.shared_post.author.name}
                size="sm"
              />

              <div className="min-w-0">
                <Link
                  to={`/profile/${post.shared_post.author.username}`}
                  className="block truncate text-base font-semibold hover:underline"
                >
                  {post.shared_post.author.name}
                </Link>
                <span className="block text-sm text-secondary">
                  {relativeTime(post.shared_post.created_at)}
                </span>
              </div>
            </div>

            <div className="px-3 pb-3 pt-2">
              {post.shared_post.body && (
                <RichText text={post.shared_post.body} className="line-clamp-3 text-md text-primary" />
              )}

              {post.shared_post.media.length > 0 && (
                <MediaGrid media={post.shared_post.media} />
              )}
            </div>
          </div>
        )}
      </div>

      {/* -------------------------------------------------- reaction summary */}
      {(post.reactions_count > 0 || post.comments_count > 0 || post.shares_count > 0) && (
        <div className="flex items-center justify-between px-4 py-2.5">
          <Tooltip content={`${post.reactions_count} people reacted`}>
            <span>
              <ReactionStack types={types} total={post.reactions_count} />
            </span>
          </Tooltip>

          <div className="flex items-center gap-3 text-sm text-secondary">
            {post.shares_count > 0 && (
              <Tooltip content={`${post.shares_count} shares`}>
                <span>{compactNumber(post.shares_count)} shares</span>
              </Tooltip>
            )}

            {post.comments_count > 0 && (
              <button type="button" onClick={() => setExpanded(true)} className="hover:underline">
                {compactNumber(post.comments_count)}{' '}
                {post.comments_count === 1 ? 'comment' : 'comments'}
              </button>
            )}
          </div>
        </div>
      )}

      <hr className="mx-4 border-divider" />

      {/* ------------------------------------------------------- action row */}
      <div className="flex items-center px-2 py-1">
        <div
          className="relative flex-1"
          onMouseEnter={onEnter}
          onMouseLeave={onLeave}
        >
          <ReactionPicker
            open={intent}
            current={current}
            onSelect={(reaction) =>
              toggleReaction.mutate({ targetType: 'post', targetId: post.id, reaction })
            }
          />

          <button
            type="button"
            onClick={() => toggleReaction.mutate({ targetType: 'post', targetId: post.id, reaction: current ?? 'like' })}
            aria-pressed={Boolean(current)}
            className={cn(
              'flex w-full items-center justify-center gap-2 rounded-control py-2 text-base font-semibold transition-colors',
              current ? 'text-facebook' : 'text-secondary hover:bg-hover/[0.05]',
            )}
          >
            {current ? (
              <span
                className="animate-reaction-pop"
                style={{ color: REACTION_COLOR[current] }}
              >
                <Icon name={reactionIcon(current)} size={20} />
              </span>
            ) : (
              <Icon name="ThumbsUp" size={20} />
            )}

            {current ? reactionLabel(current) : 'Like'}
          </button>
        </div>

        <button
          type="button"
          onClick={() => setExpanded((value) => !value)}
          aria-expanded={expanded}
          className="flex flex-1 items-center justify-center gap-2 rounded-control py-2 text-base font-semibold text-secondary transition-colors hover:bg-hover/[0.05]"
        >
          <Icon name="MessageCircle" size={20} />
          Comment
        </button>

        <button
          type="button"
          className="flex flex-1 items-center justify-center gap-2 rounded-control py-2 text-base font-semibold text-secondary transition-colors hover:bg-hover/[0.05]"
        >
          <Icon name="Share2" size={20} />
          Share
        </button>
      </div>

      {/* ------------------------------------------------------------ thread */}
      {expanded && (
        <div className="animate-fade-in">
          <ThreadComposer postId={post.id} />
          <CommentThread postId={post.id} expanded onToggle={() => setExpanded(false)} />
        </div>
      )}

      {lightboxIndex !== null && (
        <MediaLightbox
          media={post.media}
          index={lightboxIndex}
          onClose={() => setLightboxIndex(null)}
          onNavigate={setLightboxIndex}
        />
      )}
    </motion.article>
  );
}

/** Thin wrapper so `PostCard` renders one named element for the lightbox. */
function MediaLightbox(props: {
  media: Media[];
  index: number;
  onClose: () => void;
  onNavigate: (index: number) => void;
}) {
  return <Lightbox {...props} />;
}
