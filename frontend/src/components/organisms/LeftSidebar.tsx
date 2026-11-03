import { Link, useLocation } from 'react-router-dom';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon, type IconName } from '@/components/atoms/Icon';
import { Skeleton } from '@/components/atoms/Skeleton';
import { useAuthStore } from '@/store/auth';
import { cn } from '@/lib/utils';
import { useSuggestions } from '@/api/queries';

interface Shortcut {
  label: string;
  to: string;
  icon: IconName;
  /** Rendered instead of an icon — used for group and page thumbnails. */
  image?: string;
}

const SHORTCUTS: Shortcut[] = [
  { label: 'Friends', to: '/friends', icon: 'Users' },
  { label: 'Groups', to: '/groups', icon: 'UsersRound' },
  { label: 'Marketplace', to: '/marketplace', icon: 'Store' },
  { label: 'Reels', to: '/reels', icon: 'Film' },
  { label: 'Watch', to: '/watch', icon: 'Tv' },
  { label: 'Memories', to: '/memories', icon: 'History' },
  { label: 'Saved', to: '/saved', icon: 'Bookmark' },
  { label: 'Events', to: '/events', icon: 'CalendarDays' },
  { label: 'Pages', to: '/pages', icon: 'Flag' },
];

/**
 * The 360px left rail: identity row, shortcuts, and (on wide screens) the
 * "People you may know" strip. It scrolls independently of the feed, exactly
 * like Facebook's.
 */
export function LeftSidebar({ className }: { className?: string }) {
  const { user } = useAuthStore();
  const location = useLocation();
  const { data: suggestions, isLoading } = useSuggestions();

  if (!user) return null;

  return (
    <nav
      aria-label="Shortcuts"
      className={cn(
        'h-[calc(100vh-3.5rem)] overflow-y-auto pb-6 pr-1 pt-4 scrollbar-thin',
        className,
      )}
    >
      <Link
        to="/me"
        className="flex items-center gap-3 rounded-control px-2 py-2 transition-colors hover:bg-hover/[0.05]"
      >
        <Avatar src={user.avatar_url} name={user.name} size={36} online={user.is_online} />
        <span className="truncate text-base font-semibold">{user.name}</span>
      </Link>

      {SHORTCUTS.map((shortcut) => (
        <Link
          key={shortcut.label}
          to={shortcut.to}
          className={cn(
            'flex items-center gap-3 rounded-control px-2 py-2 transition-colors hover:bg-hover/[0.05]',
            location.pathname === shortcut.to && 'bg-hover/[0.06]',
          )}
        >
          <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-elevated text-primary">
            <Icon name={shortcut.icon} size={22} />
          </span>

          <span className="truncate text-base font-medium">{shortcut.label}</span>
        </Link>
      ))}

      <button
        type="button"
        className="flex w-full items-center gap-3 rounded-control px-2 py-2 text-left transition-colors hover:bg-hover/[0.05]"
      >
        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-elevated text-primary">
          <Icon name="ChevronDown" size={22} />
        </span>
        <span className="text-base font-medium">See more</span>
      </button>

      <hr className="my-3 border-divider" />

      <div className="px-2">
        <div className="mb-2 flex items-center justify-between">
          <h2 className="text-lg font-bold text-secondary">People you may know</h2>
        </div>

        {isLoading ? (
          <div className="space-y-3">
            {Array.from({ length: 3 }).map((_, index) => (
              <div key={index} className="flex items-center gap-2">
                <Skeleton className="h-9 w-9 rounded-full" />
                <Skeleton className="h-3 w-24" />
              </div>
            ))}
          </div>
        ) : (
          <ul className="space-y-1">
            {(suggestions ?? []).slice(0, 4).map((person) => (
              <li key={person.id} className="flex items-center gap-2 rounded-control p-1 hover:bg-hover/[0.04]">
                <Avatar src={person.avatar_url} name={person.name} size={36} />

                <div className="min-w-0 flex-1">
                  <p className="truncate text-base font-medium">{person.name}</p>
                  <p className="truncate text-sm text-secondary">Suggested for you</p>
                </div>

                <button
                  type="button"
                  className="shrink-0 rounded-control px-2.5 py-1.5 text-sm font-semibold text-accent hover:bg-hover/[0.05]"
                >
                  Add
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      <footer className="mt-4 px-2 text-xs leading-relaxed text-tertiary">
        <p>
          Privacy · Terms · Advertising · Cookies · Avimoney © {new Date().getFullYear()}
        </p>
      </footer>
    </nav>
  );
}
