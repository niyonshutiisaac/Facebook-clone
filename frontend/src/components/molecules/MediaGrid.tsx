import { useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { Icon } from '@/components/atoms/Icon';
import { Spinner } from '@/components/atoms/Spinner';
import { cn } from '@/lib/utils';
import { formatDuration } from '@/lib/format';
import type { Media } from '@/types/api';

/**
 * Facebook's photo layouts:
 *   1 image  → full width, max 16:9-ish, capped at 500px tall
 *   2 images → two equal columns
 *   3 images → one hero left, two stacked right
 *   4+       → 2×2 with a "+N" overlay on the last tile
 * Facebook crops to 1:1 for multi-image grids; we keep the same proportions
 * but never distort, using object-cover with a fixed aspect ratio.
 */
export function MediaGrid({ media, onOpen }: { media: Media[]; onOpen?: (index: number) => void }) {
  const [failed, setFailed] = useState<Record<string, boolean>>({});
  const [loaded, setLoaded] = useState<Record<string, boolean>>({});

  if (media.length === 0) return null;

  const count = media.length;

  if (count === 1) return <SingleMedia media={media[0]!} onOpen={() => onOpen?.(0)} />;

  const visible = media.slice(0, 4);
  const hidden = count - visible.length;

  return (
    <div className="mt-3 grid grid-cols-2 gap-0.5 overflow-hidden rounded-card border border-divider">
      {visible.map((item, index) => {
        const hero = count === 3 && index === 0;

        return (
          <button
            key={item.id}
            type="button"
            onClick={() => onOpen?.(index)}
            className={cn(
              'group relative overflow-hidden bg-elevated',
              hero ? 'row-span-2 aspect-[1/2]' : 'aspect-square',
              count === 2 && 'aspect-[3/4]',
            )}
          >
            {!loaded[item.id] && !failed[item.id] && (
              <div className="absolute inset-0 grid place-items-center">
                <Spinner size={22} className="text-tertiary" />
              </div>
            )}

            {failed[item.id] ? (
              <div className="absolute inset-0 grid place-items-center text-tertiary">
                <Icon name="ImageOff" size={28} />
              </div>
            ) : (
              <img
                src={item.type === 'video' ? (item.poster_url ?? item.thumbnail_url ?? item.url) : item.url}
                alt={item.alt_text ?? ''}
                loading="lazy"
                decoding="async"
                onLoad={() => setLoaded((s) => ({ ...s, [item.id]: true }))}
                onError={() => setFailed((s) => ({ ...s, [item.id]: true }))}
                className={cn(
                  'h-full w-full object-cover transition-[transform,opacity] duration-200 group-hover:brightness-[0.94]',
                  loaded[item.id] ? 'opacity-100' : 'opacity-0',
                )}
              />
            )}

            {item.type === 'video' && (
              <>
                <span className="absolute inset-0 grid place-items-center">
                  <span className="grid h-14 w-14 place-items-center rounded-full bg-black/45 text-white backdrop-blur-sm">
                    <Icon name="Play" size={28} fill="currentColor" />
                  </span>
                </span>

                {item.duration ? (
                  <span className="absolute bottom-2 right-2 rounded bg-black/70 px-1.5 py-0.5 text-2xs font-semibold text-white">
                    {formatDuration(item.duration)}
                  </span>
                ) : null}
              </>
            )}

            {hidden > 0 && index === visible.length - 1 && (
              <span className="absolute inset-0 grid place-items-center bg-black/55 text-3xl font-bold text-white">
                +{hidden}
              </span>
            )}
          </button>
        );
      })}
    </div>
  );
}

/** A single photo or video: Facebook caps the height so tall images do not
 *  push the rest of the feed off-screen. */
function SingleMedia({ media, onOpen }: { media: Media; onOpen: () => void }) {
  const [loaded, setLoaded] = useState(false);
  const [failed, setFailed] = useState(false);
  const [playing, setPlaying] = useState(false);

  if (media.type === 'video') {
    return (
      <div className="relative mt-3 overflow-hidden rounded-card border border-divider bg-black">
        {playing ? (
          <video src={media.url} poster={media.poster_url ?? undefined} controls autoPlay className="max-h-[500px] w-full" />
        ) : (
          <button type="button" onClick={() => setPlaying(true)} className="block w-full">
            <img
              src={media.poster_url ?? media.thumbnail_url ?? ''}
              alt={media.alt_text ?? ''}
              className="max-h-[500px] w-full object-contain"
            />

            <span className="absolute inset-0 grid place-items-center">
              <span className="grid h-16 w-16 place-items-center rounded-full bg-black/45 text-white backdrop-blur-sm transition-transform hover:scale-105">
                <Icon name="Play" size={30} fill="currentColor" />
              </span>
            </span>

            {media.duration ? (
              <span className="absolute bottom-3 right-3 rounded bg-black/70 px-1.5 py-0.5 text-xs font-semibold text-white">
                {formatDuration(media.duration)}
              </span>
            ) : null}
          </button>
        )}
      </div>
    );
  }

  return (
    <div className="relative mt-3 overflow-hidden rounded-card border border-divider bg-elevated">
      {!loaded && !failed && (
        <div className="grid h-72 place-items-center">
          <Spinner size={24} className="text-tertiary" />
        </div>
      )}

      {failed ? (
        <div className="grid h-48 place-items-center text-tertiary">
          <Icon name="ImageOff" size={32} />
        </div>
      ) : (
        <img
          src={media.url}
          alt={media.alt_text ?? ''}
          loading="lazy"
          decoding="async"
          onLoad={() => setLoaded(true)}
          onError={() => setFailed(true)}
          onClick={onOpen}
          className={cn(
            'max-h-[500px] w-full cursor-pointer object-cover',
            loaded ? 'opacity-100' : 'opacity-0',
          )}
        />
      )}
    </div>
  );
}

/** Full-screen lightbox with keyboard navigation. */
export function Lightbox({
  media,
  index,
  onClose,
  onNavigate,
}: {
  media: Media[];
  index: number;
  onClose: () => void;
  onNavigate: (index: number) => void;
}) {
  const item = media[index];

  return (
    <AnimatePresence>
      {item && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 z-modal grid place-items-center bg-black/92"
          onClick={onClose}
        >
          <button
            type="button"
            aria-label="Close"
            className="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20"
            onClick={onClose}
          >
            <Icon name="X" size={22} />
          </button>

          {media.length > 1 && (
            <>
              <button
                type="button"
                aria-label="Previous"
                className="absolute left-4 grid h-12 w-12 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20"
                onClick={(event) => {
                  event.stopPropagation();
                  onNavigate((index - 1 + media.length) % media.length);
                }}
              >
                <Icon name="ChevronLeft" size={28} />
              </button>

              <button
                type="button"
                aria-label="Next"
                className="absolute right-4 grid h-12 w-12 place-items-center rounded-full bg-white/10 text-white hover:bg-white/20"
                onClick={(event) => {
                  event.stopPropagation();
                  onNavigate((index + 1) % media.length);
                }}
              >
                <Icon name="ChevronRight" size={28} />
              </button>
            </>
          )}

          <motion.img
            key={item.id}
            initial={{ opacity: 0, scale: 0.97 }}
            animate={{ opacity: 1, scale: 1 }}
            src={item.url}
            alt={item.alt_text ?? ''}
            className="max-h-[88vh] max-w-[92vw] object-contain"
            onClick={(event) => event.stopPropagation()}
          />

          <span className="absolute bottom-4 rounded-pill bg-black/60 px-3 py-1 text-sm text-white">
            {index + 1} / {media.length}
          </span>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
