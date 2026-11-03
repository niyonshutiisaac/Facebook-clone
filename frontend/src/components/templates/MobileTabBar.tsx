import { NavLink } from 'react-router-dom';
import { Icon } from '@/components/atoms/Icon';
import { cn } from '@/lib/utils';

/** Bottom tab bar shown below 1024px, where the header nav collapses. */
export function MobileTabBar() {
  const items = [
    { to: '/', label: 'Home', icon: 'House' as const, end: true },
    { to: '/friends', label: 'Friends', icon: 'Users' as const },
    { to: '/watch', label: 'Watch', icon: 'Tv' as const },
    { to: '/groups', label: 'Groups', icon: 'UsersRound' as const },
    { to: '/menu', label: 'Menu', icon: 'Menu' as const },
  ];

  return (
    <nav
      aria-label="Primary"
      className="fixed inset-x-0 bottom-0 z-header border-t border-divider bg-surface pb-[env(safe-area-inset-bottom)] lg:hidden"
    >
      <ul className="flex items-center justify-around">
        {items.map((item) => (
          <li key={item.to} className="flex-1">
            <NavLink
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                cn(
                  'flex h-14 flex-col items-center justify-center gap-0.5 text-2xs font-medium transition-colors',
                  isActive ? 'text-facebook' : 'text-secondary',
                )
              }
            >
              {({ isActive }) => (
                <>
                  <Icon name={item.icon} size={24} fill={isActive ? 'currentColor' : 'none'} />
                  {item.label}
                </>
              )}
            </NavLink>
          </li>
        ))}
      </ul>
    </nav>
  );
}
