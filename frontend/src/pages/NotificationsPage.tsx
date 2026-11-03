import { Link } from 'react-router-dom';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon, type IconName } from '@/components/atoms/Icon';
import { Skeleton } from '@/components/atoms/Skeleton';
import { relativeTime } from '@/lib/format';
import { useMarkAllNotificationsRead, useNotifications } from '@/api/queries';
import { cn } from '@/lib/utils';

const ICONS: Record<string, IconName> = {
  like: 'like-fill',
  comment: 'MessageSquare',
  share: 'Share2',
  friend: 'UserPlus',
  tag: 'Tag',
  group: 'UsersRound',
  birthday: 'Gift',
  message: 'MessageCircle',
  event: 'CalendarDays',
  page: 'Store',
};

const COLORS: Record<string, string> = {
  like: '#0866FF',
  comment: '#31A24C',
  share: '#65676B',
  friend: '#0866FF',
  tag: '#F02849',
  group: '#F7B125',
  birthday: '#F7B125',
  message: '#0866FF',
  event: '#F02849',
  page: '#65676B',
};

export function NotificationsPage() {
  const { data, isLoading } = useNotifications();
  const markAll = useMarkAllNotificationsRead();

  const items = data?.data ?? [];

  return (
    <div className="mx-auto w-full max-w-feed px-2 sm:px-0">
      <header className="mb-4 flex items-center justify-between">
        <h1 className="text-2xl font-bold">Notifications</h1>

        <button
          type="button"
          onClick={() => markAll.mutate()}
          className="rounded-control px-3 py-1.5 text-base font-semibold text-accent hover:bg-hover/[0.05]"
        >
          Mark all as read
        </button>
      </header>

      {isLoading ? (
        <div className="space-y-2">
          {Array.from({ length: 8 }).map((_, index) => (
            <Skeleton key={index} className="h-20 w-full rounded-card" />
          ))}
        </div>
      ) : (
        <ul className="space-y-2">
          {items.map((notification) => (
            <li
              key={notification.id}
              className={cn(
                'flex items-center gap-3 rounded-card bg-surface p-3 shadow-card',
                !notification.is_read && 'ring-1 ring-facebook/20',
              )}
            >
              <span className="relative shrink-0">
                <Avatar
                  src={notification.actor?.avatar_url}
                  name={notification.actor?.name ?? 'A'}
                  size="md"
                />

                <span
                  className="absolute -bottom-1 -right-1 grid h-6 w-6 place-items-center rounded-full text-white ring-2 ring-surface"
                  style={{ backgroundColor: COLORS[notification.icon] ?? '#0866FF' }}
                >
                  <Icon name={ICONS[notification.icon] ?? 'Bell'} size={13} />
                </span>
              </span>

              <Link to={notification.target_url ?? '#'} className="min-w-0 flex-1">
                <p className="text-md">
                  <span className="font-semibold">{notification.text.split(' ')[0]}</span>{' '}
                  {notification.text.slice(notification.text.indexOf(' ') + 1)}
                </p>

                <p className={cn('mt-0.5 text-sm', !notification.is_read ? 'font-semibold text-facebook' : 'text-secondary')}>
                  {relativeTime(notification.created_at)}
                </p>
              </Link>

              {!notification.is_read && (
                <span className="h-2.5 w-2.5 shrink-0 rounded-full bg-facebook" aria-label="Unread" />
              )}

              <button
                type="button"
                aria-label="Notification options"
                className="grid h-9 w-9 shrink-0 place-items-center rounded-full text-secondary hover:bg-hover/[0.08]"
              >
                <Icon name="Ellipsis" size={20} />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
