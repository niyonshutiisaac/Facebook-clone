import { useEffect, useRef, useState } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { AnimatePresence, motion } from 'framer-motion';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import { IconButton } from '@/components/atoms/IconButton';
import { Menu } from '@/components/molecules/Menu';
import { SearchInput } from '@/components/molecules/SearchInput';
import { Tooltip } from '@/components/molecules/Tooltip';
import { useAuthStore } from '@/store/auth';
import { useUiStore } from '@/store/ui';
import { useSearchSuggestions } from '@/api/queries';
import type { SearchSuggestion } from '@/types/api';
import { cn } from '@/lib/utils';
import { NotificationMenu } from './NotificationMenu';
import { AccountMenu } from './AccountMenu';

/** Where a type-ahead result leads: profiles, groups and (later) pages. */
function suggestionHref(suggestion: SearchSuggestion): string {
  if (suggestion.type === 'group') return `/groups/${suggestion.id}`;

  return `/profile/${suggestion.username ?? suggestion.id}`;
}

/**
 * The persistent top bar.
 *
 * Facebook's header is a three-zone grid (search + nav + actions) that stays
 * fixed at 56px tall with a translucent blur. On narrow screens the middle nav
 * collapses into a bottom tab bar (see MobileTabBar).
 */
export function Header() {
  const { user, unreadMessages, unreadNotifications } = useAuthStore();
  const { theme, toggleTheme, toggleMobileNav, openChat } = useUiStore();
  const navigate = useNavigate();

  const [query, setQuery] = useState('');
  const [searchOpen, setSearchOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  const { data: suggestionsData } = useSearchSuggestions(query);
  const suggestions = suggestionsData ?? [];

  // Close the dropdown on outside click or Escape.
  useEffect(() => {
    const onClick = (event: MouseEvent) => {
      if (!containerRef.current?.contains(event.target as Node)) setSearchOpen(false);
    };

    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setSearchOpen(false);
    };

    document.addEventListener('mousedown', onClick);
    document.addEventListener('keydown', onKey);

    return () => {
      document.removeEventListener('mousedown', onClick);
      document.removeEventListener('keydown', onKey);
    };
  }, []);

  const navItems = [
    { to: '/', label: 'Home', icon: 'House' as const, end: true },
    { to: '/friends', label: 'Friends', icon: 'Users' as const },
    { to: '/watch', label: 'Watch', icon: 'Tv' as const },
    { to: '/marketplace', label: 'Marketplace', icon: 'Store' as const },
    { to: '/groups', label: 'Groups', icon: 'UsersRound' as const },
  ];

  return (
    <header className="fixed inset-x-0 top-0 z-header h-header glass shadow-card">
      <div className="mx-auto flex h-full items-center gap-2 px-2 sm:px-4">
        {/* ---------------------------------------------------------- brand */}
        <Link to="/" className="flex items-center gap-2" aria-label="Avimoney home">
          <span className="text-facebook">
            <Icon name="facebook" size={40} />
          </span>
        </Link>

        {/* ---------------------------------------------------------- search */}
        <div
          ref={containerRef}
          className="relative ml-1 hidden sm:block sm:w-60 lg:w-72"
          onKeyDown={(event) => {
            if (event.key !== 'Enter') return;

            const term = query.trim();

            if (!term) return;

            setSearchOpen(false);
            navigate(`/search?q=${encodeURIComponent(term)}`);
          }}
        >
          <SearchInput
            value={query}
            onChange={setQuery}
            onFocusChange={setSearchOpen}
            placeholder="Search Avimoney"
          />

          <AnimatePresence>
            {searchOpen && (
              <motion.div
                initial={{ opacity: 0, y: -6 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -6 }}
                transition={{ duration: 0.14 }}
                className="absolute inset-x-0 top-[calc(100%+8px)] max-h-[70vh] overflow-y-auto rounded-card bg-surface p-2 shadow-dropdown scrollbar-thin"
              >
                {query.trim().length < 2 ? (
                  <p className="px-3 py-8 text-center text-sm text-secondary">
                    Start typing to search for people, posts and groups.
                  </p>
                ) : suggestions.length === 0 ? (
                  <p className="px-3 py-8 text-center text-sm text-secondary">No results for “{query}”.</p>
                ) : (
                  <>
                    {suggestions.map((suggestion) => (
                      <Link
                        key={`${suggestion.type}-${suggestion.id}`}
                        to={suggestionHref(suggestion)}
                        onClick={() => {
                          setSearchOpen(false);
                          setQuery('');
                        }}
                        className="flex items-center gap-3 rounded-control px-2 py-2 hover:bg-hover/[0.05]"
                      >
                        <Avatar
                          src={suggestion.avatar_url}
                          name={suggestion.title}
                          size="sm"
                          square={suggestion.type !== 'user'}
                        />

                        <span className="min-w-0">
                          <span className="block truncate text-md font-medium">{suggestion.title}</span>
                          {suggestion.subtitle && (
                            <span className="block truncate text-sm text-secondary">{suggestion.subtitle}</span>
                          )}
                        </span>
                      </Link>
                    ))}

                    <Link
                      to={`/search?q=${encodeURIComponent(query.trim())}`}
                      onClick={() => setSearchOpen(false)}
                      className="mt-1 flex items-center justify-center gap-2 rounded-control bg-subtle px-2 py-2.5 text-base font-semibold text-accent hover:bg-hover/[0.08]"
                    >
                      <Icon name="Search" size={15} />
                      See all results for “{query.trim()}”
                    </Link>
                  </>
                )}
              </motion.div>
            )}
          </AnimatePresence>
        </div>

        {/* ------------------------------------------------------------- nav */}
        <nav className="ml-2 hidden flex-1 items-center justify-center gap-1 lg:flex" aria-label="Main">
          {navItems.map((item) => (
            <Tooltip key={item.to} content={item.label}>
              <NavLink
                to={item.to}
                end={item.end}
                className={({ isActive }) =>
                  cn(
                    'relative grid h-12 w-[100px] place-items-center rounded-control transition-colors',
                    isActive ? 'text-facebook' : 'text-secondary hover:bg-hover/[0.05]',
                  )
                }
              >
                {({ isActive }) => (
                  <>
                    <Icon name={item.icon} size={24} />

                    {isActive && (
                      <motion.span
                        layoutId="header-underline"
                        className="absolute -bottom-1 h-[3px] w-[56px] rounded-t-full bg-facebook"
                        transition={{ type: 'spring', stiffness: 480, damping: 38 }}
                      />
                    )}
                  </>
                )}
              </NavLink>
            </Tooltip>
          ))}
        </nav>

        {/* --------------------------------------------------------- actions */}
        <div className="ml-auto flex items-center gap-1.5">
          <button
            type="button"
            onClick={() => toggleMobileNav()}
            className="grid h-10 w-10 place-items-center rounded-full bg-elevated text-primary lg:hidden"
            aria-label="Open menu"
          >
            <Icon name="Menu" size={20} />
          </button>

          <div className="lg:hidden">
            <IconButton
              label="Search"
              onClick={() => navigate('/search')}
            >
              <Icon name="Search" size={20} />
            </IconButton>
          </div>

          <Tooltip content="Messenger">
            <IconButton
              label="Messenger"
              badge={unreadMessages}
              onClick={() => openChat('__list__')}
            >
              <Icon name="MessageCircle" size={22} />
            </IconButton>
          </Tooltip>

          <NotificationMenu count={unreadNotifications} />

          <Menu
            align="end"
            items={[
              {
                label: theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode',
                icon: 'dark-mode',
                onSelect: toggleTheme,
              },
              {
                label: 'Settings & privacy',
                icon: 'Settings',
                onSelect: () => navigate('/settings'),
              },
              { label: 'Activity log', icon: 'History', onSelect: () => navigate('/activity') },
              { divider: true, label: '' },
              {
                label: 'Give feedback',
                icon: 'MessageSquareQuote',
                onSelect: () => undefined,
              },
              { label: 'Help & support', icon: 'CircleHelp', onSelect: () => navigate('/help') },
              { label: 'Display & accessibility', icon: 'Palette', onSelect: () => navigate('/settings/display') },
              { divider: true, label: '' },
              { label: 'Log out', icon: 'LogOut', onSelect: () => void useAuthStore.getState().logout() },
            ]}
            trigger={
              <button
                type="button"
                className="grid h-10 w-10 place-items-center rounded-full bg-elevated text-secondary"
                aria-label="Account"
              >
                <Icon name="LayoutGrid" size={20} />
              </button>
            }
          />

          <AccountMenu user={user} />
        </div>
      </div>
    </header>
  );
}
