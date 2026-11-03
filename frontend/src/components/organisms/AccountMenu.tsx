import { useNavigate } from 'react-router-dom';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import { Menu } from '@/components/molecules/Menu';
import { useAuthStore } from '@/store/auth';
import { useUiStore } from '@/store/ui';
import type { User } from '@/types/api';

export function AccountMenu({ user }: { user: User | null }) {
  const navigate = useNavigate();
  const { theme, toggleTheme } = useUiStore();

  if (!user) return null;

  return (
    <Menu
      align="end"
      items={[
        {
          label: user.name,
          content: (
            <button
              type="button"
              onClick={() => navigate('/me')}
              className="flex w-full items-center gap-3 text-left"
            >
              <Avatar src={user.avatar_url} name={user.name} size="lg" />

              <span className="min-w-0 flex-1">
                <span className="block truncate text-base font-semibold">{user.name}</span>
                <span className="block text-sm text-secondary">See your profile</span>
              </span>
            </button>
          ),
        },
        { divider: true, label: '' },
        {
          label: 'Settings & privacy',
          icon: 'Settings',
          onSelect: () => navigate('/settings'),
        },
        { label: 'Activity log', icon: 'History', onSelect: () => navigate('/activity') },
        {
          label: theme === 'dark' ? 'Light mode' : 'Dark mode',
          icon: theme === 'dark' ? 'Sun' : 'dark-mode',
          onSelect: toggleTheme,
        },
        { divider: true, label: '' },
        {
          label: 'Log out',
          icon: 'LogOut',
          onSelect: () => void useAuthStore.getState().logout(),
        },
      ]}
      trigger={
        <button type="button" aria-label="Your account" className="rounded-full ring-offset-2 hover:opacity-90">
          <Avatar src={user.avatar_url} name={user.name} size={40} online={user.is_online} />
        </button>
      }
    />
  );
}

export function HeaderLogoIcon() {
  return <Icon name="facebook" size={40} className="text-facebook" />;
}
