import { useEffect, useRef, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import { Spinner } from '@/components/atoms/Spinner';
import { REACTION_COLOR, ReactionPicker, reactionIcon } from '@/components/molecules/Reactions';
import { compactNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ID, ReactionType, Reel } from '@/types/api';

/**
 * One reel: a 9:16 surface that owns its own playback.
 *
 * The parent decides *which* reel is active (it observes the scroll container);
 * the card only reacts to that decision — playing when it becomes the active
 * one, pausing and reporting how much of it was actually watched when it stops.
 *
 * Everything Facebook puts on the surface is here: the right-hand action rail,
 * the creator row with Follow, the caption, the spinning audio disc, and the
 * hairline progress bar along the bottom edge.
 */
export function ReelCard({
  reel,
  active,
  muted,
  onToggleMute,
  onReact,
  onWatched,
  onShare,
  onOpenComments,
  onFollow,
  onOpenProfile,
}: {
  reel: Reel;
  active: boolean;
  muted: boolean;
  onToggleMute: () => void;
  onReact: (reelId: ID, reaction: ReactionType | null) => void;
  onWatched: (reelId: ID, seconds: number) => void;
  onShare: (reelId: ID) => void;
  onOpenComments: (reelId: ID) => void;
  onFollow: (userId: ID, following: boolean) => void;
  onOpenProfile: (username: string) => void;
}) {
  const video = useRef<HTMLVideoElement>(null);
  const lastTime = useRef(0);
  const watched = useRef(0);
  const hoverTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const [progress, setProgress] = useState(0);
  const [failed, setFailed] = useState(false);
  const [buffering, setBuffering] = useState(true);
  const [paused, setPaused] = useState(false);
  const [expanded, setExpanded] = useState(false);
  const [pickerOpen, setPickerOpen] = useState(false);

  const following = reel.creator.is_following ?? false;
  const liked = reel.viewer_reaction !== null;
  const reactionColour = reel.viewer_reaction ? REACTION_COLOR[reel.viewer_reaction] : undefined;

  // ------------------------------------------------------------- playback
  useEffect(() => {
    const element = video.current;

    if (!element) return undefined;

    if (active) {
      setPaused(false);
      setProgress(0);
      lastTime.current = 0;
      watched.current = 0;

      // Autoplay is only permitted while muted; a rejected promise here means
      // the browser blocked it, which the on-screen play affordance resolves.
      // (jsdom returns nothing from play(), hence the guard.)
      const playing = element.play() as Promise<void> | undefined;

      if (playing && typeof playing.catch === 'function') {
        playing.catch(() => setPaused(true));
      }
    } else {
      element.pause();
      element.currentTime = 0;
    }

    return undefined;
  }, [active]);

  // Flush the watch time when the reel stops being the one on screen. This is
  // what feeds completion-rate ranking, so it must not fire on mount.
  useEffect(() => {
    if (active) return undefined;

    return () => {
      if (watched.current >= 1) onWatched(reel.id, watched.current);

      watched.current = 0;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [active, reel.id]);

  const onTimeUpdate = () => {
    const element = video.current;

    if (!element) return;

    const { currentTime, duration } = element;

    if (Number.isFinite(duration) && duration > 0) {
      setProgress(Math.min(1, currentTime / duration));
    }

    // Count forward jumps only — seeking backwards should not inflate the
    // completion rate.
    const delta = currentTime - lastTime.current;

    if (delta > 0 && delta < 1) watched.current += delta;

    lastTime.current = currentTime;
  };

  const togglePlay = () => {
    const element = video.current;

    if (!element || failed) return;

    if (element.paused) {
      const playing = element.play() as Promise<void> | undefined;

      if (playing && typeof playing.catch === 'function') playing.catch(() => undefined);

      setPaused(false);
    } else {
      element.pause();
      setPaused(true);
    }
  };

  const toggleReaction = () => onReact(reel.id, liked ? null : 'like');

  const openPickerAfterHold = () => {
    hoverTimer.current = setTimeout(() => setPickerOpen(true), 400);
  };

  const cancelPicker = () => {
    if (hoverTimer.current) clearTimeout(hoverTimer.current);
  };

  return (
    <article
      className="relative h-full w-full snap-start overflow-hidden bg-black"
      aria-label={`Reel by ${reel.creator.name}`}
      aria-roledescription="video"
    >
      {/* ------------------------------------------------------------ media */}
      <div className="absolute inset-0" onClick={togglePlay}>
        {failed ? (
          <img
            src={reel.poster_url ?? ''}
            alt=""
            className="h-full w-full object-cover opacity-70 motion-safe:animate-ken-burns"
          />
        ) : (
          <video
            ref={video}
            src={reel.url}
            poster={reel.poster_url ?? undefined}
            className="h-full w-full object-cover"
            loop
            playsInline
            muted={muted}
            preload={active ? 'auto' : 'metadata'}
            onTimeUpdate={onTimeUpdate}
            onWaiting={() => setBuffering(true)}
            onPlaying={() => setBuffering(false)}
            onCanPlay={() => setBuffering(false)}
            onError={() => {
              setFailed(true);
              setBuffering(false);
            }}
          />
        )}
      </div>

      {/* Scrims: one at the top for the chrome, one at the bottom for text. */}
      <div className="pointer-events-none absolute inset-x-0 top-0 h-32 bg-gradient-to-b from-black/55 to-transparent" />
      <div className="pointer-events-none absolute inset-x-0 bottom-0 h-56 bg-gradient-to-t from-black/70 via-black/25 to-transparent" />

      {buffering && !failed && (
        <span className="pointer-events-none absolute inset-0 grid place-items-center">
          <Spinner size={28} className="text-white/70" />
        </span>
      )}

      <AnimatePresence>
        {paused && !failed && (
          <motion.span
            initial={{ opacity: 0, scale: 0.8 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.9 }}
            className="pointer-events-none absolute inset-0 grid place-items-center"
          >
            <span className="grid h-16 w-16 place-items-center rounded-full bg-black/45 text-white backdrop-blur-sm">
              <Icon name="Play" size={30} />
            </span>
          </motion.span>
        )}
      </AnimatePresence>

      {/* --------------------------------------------------------- top row */}
      <header className="absolute inset-x-0 top-0 z-10 flex items-center gap-2 px-3 pt-3">
        <button
          type="button"
          onClick={() => onOpenProfile(reel.creator.username ?? reel.creator.id)}
          className="flex min-w-0 flex-1 items-center gap-2 text-left"
        >
          <Avatar src={reel.creator.avatar_url} name={reel.creator.name} size={36} ring />
          <span className="min-w-0">
            <span className="flex items-center gap-1 text-base font-semibold text-white">
              <span className="truncate">{reel.creator.name}</span>
              {reel.creator.is_verified && <Icon name="BadgeCheck" size={14} className="text-facebook-action" />}
            </span>
          </span>
        </button>

        {reel.creator.id === 'me' ? null : (
          <button
            type="button"
            onClick={() => onFollow(reel.creator.id, !following)}
            aria-label={following ? `Unfollow ${reel.creator.name}` : `Follow ${reel.creator.name}`}
            className={cn(
              'shrink-0 rounded-control border px-3 py-1 text-base font-semibold transition-colors',
              following
                ? 'border-white/40 text-white hover:bg-white/10'
                : 'border-white bg-white text-black hover:bg-white/85',
            )}
          >
            {following ? 'Following' : 'Follow'}
          </button>
        )}

        <button
          type="button"
          onClick={onToggleMute}
          aria-label={muted ? 'Unmute' : 'Mute'}
          title={muted ? 'Unmute (m)' : 'Mute (m)'}
          className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-black/35 text-white backdrop-blur-sm hover:bg-black/55"
        >
          <Icon name={muted ? 'VolumeX' : 'Volume2'} size={18} />
        </button>
      </header>

      {/* -------------------------------------------------------- right rail */}
      <div className="absolute bottom-24 right-2 z-10 flex flex-col items-center gap-4">
        <div className="relative">
          <button
            type="button"
            onClick={toggleReaction}
            onPointerEnter={openPickerAfterHold}
            onPointerLeave={() => {
              cancelPicker();
              setPickerOpen(false);
            }}
            aria-label={liked ? 'Remove reaction' : 'Like'}
            aria-pressed={liked}
            style={reactionColour ? { color: reactionColour } : undefined}
            className={cn('flex w-11 flex-col items-center gap-0.5 transition-transform active:scale-90', 'text-white')}
          >
            <Icon
              name={liked ? reactionIcon(reel.viewer_reaction) : 'Heart'}
              size={32}
              fill={liked ? (reactionColour ?? '#f02849') : 'none'}
            />
          </button>

          <ReactionPicker
            open={pickerOpen}
            current={reel.viewer_reaction}
            onSelect={(reaction) => {
              onReact(reel.id, reaction);
              setPickerOpen(false);
            }}
            onOpenChange={setPickerOpen}
          />

          <span className="mt-0.5 block text-center text-sm font-semibold text-white drop-shadow">
            {compactNumber(reel.likes_count)}
          </span>
        </div>

        <button
          type="button"
          onClick={() => onOpenComments(reel.id)}
          aria-label={`Comments (${reel.comments_count})`}
          className="flex w-11 flex-col items-center gap-0.5 text-white transition-transform active:scale-90"
        >
          <Icon name="MessageCircle" size={30} />
          <span className="mt-0.5 block text-center text-sm font-semibold drop-shadow">
            {compactNumber(reel.comments_count)}
          </span>
        </button>

        <button
          type="button"
          onClick={() => onShare(reel.id)}
          aria-label={`Share (${reel.shares_count})`}
          className="flex w-11 flex-col items-center gap-0.5 text-white transition-transform active:scale-90"
        >
          <Icon name="Share2" size={28} />
          <span className="mt-0.5 block text-center text-sm font-semibold drop-shadow">
            {compactNumber(reel.shares_count)}
          </span>
        </button>

        {reel.allows_remix && (
          <button
            type="button"
            aria-label="Remix this reel"
            title="Remix"
            className="grid h-11 w-11 place-items-center text-white transition-transform active:scale-90"
          >
            <Icon name="Repeat" size={26} />
          </button>
        )}

        <button
          type="button"
          aria-label="More options"
          className="grid h-11 w-11 place-items-center text-white transition-transform active:scale-90"
        >
          <Icon name="Ellipsis" size={24} />
        </button>

        {reel.audio && (
          <motion.span
            className="grid h-9 w-9 place-items-center rounded-full bg-white/20 text-white backdrop-blur-sm"
            animate={active ? { rotate: 360 } : { rotate: 0 }}
            transition={{ repeat: active ? Infinity : 0, duration: 5, ease: 'linear' }}
          >
            <Icon name="Music2" size={16} />
          </motion.span>
        )}
      </div>

      {/* ------------------------------------------------------ bottom left */}
      <div className="absolute inset-x-0 bottom-0 z-10 max-w-[calc(100%-5.5rem)] px-3 pb-4">
        {reel.audio && (
          <p className="mb-1 flex items-center gap-1.5 text-base text-white/95">
            <Icon name="Music2" size={14} />
            <span className="truncate font-medium">
              {reel.audio.title}
              {reel.audio.artist ? ` · ${reel.audio.artist}` : ''}
            </span>
          </p>
        )}

        {reel.caption && (
          <p className="text-base text-white">
            <span className={cn(!expanded && 'line-clamp-2')}>{reel.caption}</span>
            {reel.caption.length > 90 && (
              <button
                type="button"
                onClick={() => setExpanded((value) => !value)}
                className="ml-1 font-semibold text-white/80 hover:underline"
              >
                {expanded ? 'See less' : 'See more'}
              </button>
            )}
          </p>
        )}

        <p className="mt-1.5 flex items-center gap-1.5 text-sm text-white/75">
          <Icon name="Play" size={12} />
          {compactNumber(reel.views_count)} views
          <span aria-hidden="true">·</span>
          {reel.creator.username ? `@${reel.creator.username}` : reel.creator.name}
        </p>
      </div>

      {/* --------------------------------------------------------- progress */}
      <div className="absolute inset-x-0 bottom-0 z-20 h-[3px] bg-white/20">
        <div className="h-full bg-white transition-[width] duration-100 ease-linear" style={{ width: `${progress * 100}%` }} />
      </div>

      {failed && (
        <p className="absolute left-3 top-1/2 z-20 -translate-y-1/2 rounded-pill bg-black/60 px-3 py-1 text-base text-white/90">
          Preview unavailable — showing the cover frame.
        </p>
      )}
    </article>
  );
}
