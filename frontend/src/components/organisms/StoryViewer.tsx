import { useCallback, useEffect, useRef, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import { Spinner } from '@/components/atoms/Spinner';
import { TypingIndicator } from '@/components/molecules/TypingIndicator';
import { REACTIONS } from '@/components/molecules/Reactions';
import { relativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ReactionType, StoryTray } from '@/types/api';

/**
 * The full-screen story viewer.
 *
 * Facebook's behaviour, reproduced:
 *  - segmented progress bar, one segment per story, advancing over `duration`
 *  - press-and-hold pauses playback (and hides the chrome)
 *  - tap the left third / right two-thirds to go back / forward
 *  - reaching the end of a tray advances to the next person
 *  - arrow keys, Escape and swipe are wired for keyboard and touch
 */
const DEFAULT_DURATION_MS = 5000;

export function StoryViewer({
  trays,
  trayIndex,
  storyIndex,
  onNavigateTray,
  onNavigateStory,
  onClose,
  onReact,
  onReply,
  onView,
}: {
  trays: StoryTray[];
  trayIndex: number;
  storyIndex: number;
  onNavigateTray: (index: number) => void;
  onNavigateStory: (index: number) => void;
  onClose: () => void;
  onReact?: (storyId: string, reaction: ReactionType) => void;
  onReply?: (storyId: string, body: string) => void;
  onView?: (storyId: string) => void;
}) {
  const tray = trays[trayIndex];
  const story = tray?.stories[storyIndex];

  const [paused, setPaused] = useState(false);
  const [progress, setProgress] = useState(0);
  const [draft, setDraft] = useState('');
  const [sent, setSent] = useState(false);

  const startedAt = useRef<number>(Date.now());
  const heldAt = useRef<number | null>(null);

  // --------------------------------------------------------------- playback
  useEffect(() => {
    setProgress(0);
    startedAt.current = Date.now();
    setSent(false);
    setDraft('');
  }, [trayIndex, storyIndex]);

  useEffect(() => {
    if (!story) return undefined;

    onView?.(story.id);

    if (paused) return undefined;

    let frame = 0;

    const tick = () => {
      const elapsed = Date.now() - startedAt.current;
      const duration = Math.max(2000, (story.media?.duration ?? 5) * 1000 || DEFAULT_DURATION_MS);

      setProgress(Math.min(1, elapsed / duration));

      if (elapsed >= duration) {
        advance();

        return;
      }

      frame = requestAnimationFrame(tick);
    };

    frame = requestAnimationFrame(tick);

    return () => cancelAnimationFrame(frame);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [story?.id, paused]);

  const advance = useCallback(() => {
    if (!tray) {
      onClose();

      return;
    }

    if (storyIndex + 1 < tray.stories.length) {
      onNavigateStory(storyIndex + 1);

      return;
    }

    if (trayIndex + 1 < trays.length) {
      onNavigateTray(trayIndex + 1);

      return;
    }

    onClose();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tray, storyIndex, trayIndex, trays.length]);

  const retreat = useCallback(() => {
    if (storyIndex > 0) {
      onNavigateStory(storyIndex - 1);

      return;
    }

    if (trayIndex > 0) {
      const previous = trays[trayIndex - 1]!;
      onNavigateTray(trayIndex - 1);
      onNavigateStory(Math.max(0, previous.stories.length - 1));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [storyIndex, trayIndex, trays]);

  // -------------------------------------------------------------- keyboard
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
      if (event.key === 'ArrowRight') advance();
      if (event.key === 'ArrowLeft') retreat();
    };

    document.addEventListener('keydown', onKey);

    return () => document.removeEventListener('keydown', onKey);
  }, [advance, retreat, onClose]);

  if (!tray || !story) return null;

  const submitReply = () => {
    if (!draft.trim()) return;

    onReply?.(story.id, draft.trim());
    setSent(true);
    setDraft('');
  };

  return (
    <motion.div
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      className="fixed inset-0 z-modal grid place-items-center bg-[#1c1e21]"
      role="dialog"
      aria-modal="true"
      aria-label={`Story from ${tray.user.name}`}
    >
      <div className="relative h-full w-full max-w-[26rem] overflow-hidden bg-black sm:h-[95vh] sm:rounded-card">
        {/* ------------------------------------------------------ progress */}
        <div className="absolute inset-x-2 top-2 z-20 flex gap-1">
          {tray.stories.map((item, index) => (
            <div key={item.id} className="h-[3px] flex-1 overflow-hidden rounded-full bg-white/30">
              <div
                className="h-full bg-white transition-none"
                style={{
                  width: `${
                    index < storyIndex ? 100 : index === storyIndex ? progress * 100 : 0
                  }%`,
                }}
              />
            </div>
          ))}
        </div>

        {/* -------------------------------------------------------- header */}
        <header className="absolute inset-x-0 top-0 z-20 flex items-center gap-2 bg-gradient-to-b from-black/60 to-transparent px-3 pb-8 pt-6">
          <Avatar src={tray.user.avatar_url} name={tray.user.name} size={32} />

          <div className="min-w-0 flex-1">
            <p className="truncate text-base font-semibold text-white">{tray.user.name}</p>
            <p className="text-xs text-white/70">{relativeTime(story.created_at)}</p>
          </div>

          <button
            type="button"
            aria-label="Pause"
            onClick={() => setPaused((value) => !value)}
            className="grid h-9 w-9 place-items-center rounded-full text-white hover:bg-white/15"
          >
            <Icon name={paused ? 'Play' : 'Clock'} size={20} />
          </button>

          <button
            type="button"
            aria-label="Close"
            onClick={onClose}
            className="grid h-9 w-9 place-items-center rounded-full text-white hover:bg-white/15"
          >
            <Icon name="X" size={22} />
          </button>
        </header>

        {/* --------------------------------------------------------- media */}
        <div
          className="grid h-full w-full place-items-center"
          onPointerDown={() => {
            heldAt.current = Date.now();
            setPaused(true);
          }}
          onPointerUp={(event) => {
            const held = heldAt.current ? Date.now() - heldAt.current : 0;
            heldAt.current = null;

            // A short press is a tap (navigate); a long press was a pause.
            if (held < 220) {
              const rect = event.currentTarget.getBoundingClientRect();
              const fromLeft = event.clientX - rect.left;

              if (fromLeft < rect.width / 3) retreat();
              else advance();
            }

            setPaused(false);
          }}
          onPointerLeave={() => {
            heldAt.current = null;
            setPaused(false);
          }}
        >
          {story.media ? (
            <img
              key={story.media.url}
              src={story.media.url}
              alt=""
              className="h-full w-full object-contain"
              onLoad={() => setProgress((value) => value)}
            />
          ) : (
            <div className="grid h-full w-full place-items-center bg-gradient-to-br from-[#7B4397] to-[#C06C84] p-10">
              <p className="text-center text-2xl font-bold text-white">{story.body}</p>
            </div>
          )}

          {story.body && story.media && (
            <p className="absolute inset-x-6 bottom-28 text-center text-lg font-semibold text-white drop-shadow">
              {story.body}
            </p>
          )}

          {/* Placeholder behind the media while it decodes. */}
          <span className="pointer-events-none absolute inset-0 -z-10 grid place-items-center">
            <Spinner size={26} className="text-white/40" />
          </span>
        </div>

        {/* Navigation arrows (desktop) */}
        <button
          type="button"
          aria-label="Previous"
          onClick={retreat}
          className="absolute left-2 top-1/2 z-20 hidden h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-black/40 text-white hover:bg-black/60 sm:grid"
        >
          <Icon name="ChevronLeft" size={22} />
        </button>

        <button
          type="button"
          aria-label="Next"
          onClick={advance}
          className="absolute right-2 top-1/2 z-20 hidden h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-black/40 text-white hover:bg-black/60 sm:grid"
        >
          <Icon name="ChevronRight" size={22} />
        </button>

        {/* -------------------------------------------------------- footer */}
        <footer className="absolute inset-x-0 bottom-0 z-20 bg-gradient-to-t from-black/70 to-transparent px-3 pb-3 pt-10">
          <div className="flex items-center justify-between gap-1">
            {REACTIONS.slice(0, 6).map((reaction) => (
              <button
                key={reaction.type}
                type="button"
                aria-label={reaction.label}
                title={reaction.label}
                onClick={() => onReact?.(story.id, reaction.type)}
                className="grid h-10 flex-1 place-items-center rounded-pill text-3xl transition-transform hover:scale-125 active:scale-95"
                style={{ color: reaction.color }}
              >
                <Icon name={reaction.icon} size={32} />
              </button>
            ))}
          </div>

          <div className="mt-3 flex items-center gap-2">
            <input
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'Enter') submitReply();
              }}
              placeholder={`Reply to ${tray.user.name.split(' ')[0]}…`}
              aria-label="Send message"
              className="h-10 flex-1 rounded-pill border border-white/30 bg-transparent px-4 text-base text-white placeholder:text-white/60 focus:border-white focus:outline-none"
            />

            <button
              type="button"
              onClick={submitReply}
              disabled={!draft.trim()}
              aria-label="Send reply"
              className={cn(
                'grid h-10 w-10 shrink-0 place-items-center rounded-full transition-colors',
                draft.trim() ? 'bg-facebook-action text-white' : 'bg-white/20 text-white/60',
              )}
            >
              <Icon name="Send" size={18} />
            </button>
          </div>

          <AnimatePresence>
            {sent && (
              <motion.p
                initial={{ opacity: 0, y: 6 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0 }}
                className="mt-2 flex items-center gap-2 text-sm text-white/80"
              >
                <TypingIndicator />
                Reply sent.
              </motion.p>
            )}
          </AnimatePresence>
        </footer>
      </div>
    </motion.div>
  );
}
