import { Link, useNavigate } from 'react-router-dom';
import * as Popover from '@radix-ui/react-popover';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon, type IconName } from '@/components/atoms/Icon';
import { IconButton } from '@/components/atoms/IconButton';
import { Skeleton } from '@/components/atoms/Skeleton';
import { relativeTime } from '@/lib/format';
import { useMarkAllNotificationsRead, useNotifications } from '@/api/queries';
import { cn } from '@/lib/utils';

/** Maps the API's `icon` field onto our icon set. */
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

const ICON_COLORS: Record<string, string> = {
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

export function NotificationMenu({ count }: { count: number }) {
  const navigate = useNavigate();
  const { data, isLoading } = useNotifications();
  const markAll = useMarkAllNotificationsRead();

  const items = data?.data ?? [];

  return (
    <Popover.Root>
      <Popover.Trigger asChild>
        <IconButton label="Notifications" badge={count}>
          <Icon name="Bell" size={22} />
        </IconButton>
      </Popover.Trigger>

      <Popover.Portal>
        <Popover.Content
          align="end"
          sideOffset={8}
          className="z-dropdown w-[22rem] max-w-[calc(100vw-1rem)] overflow-hidden rounded-card bg-surface shadow-dropdown animate-scale-in"
        >
          <div className="flex items-center justify-between px-4 pb-2 pt-3">
            <h2 className="text-xl font-bold">Notifications</h2>

            <button
              type="button"
              onClick={() => markAll.mutate()}
              className="rounded-control px-2 py-1 text-sm font-semibold text-accent hover:bg-hover/[0.05]"
            >
              Mark all as read
            </button>
          </div>

          <div className="max-h-[70vh] overflow-y-auto scrollbar-thin">
            {isLoading ? (
              <div className="space-y-2 p-3">
                {Array.from({ length: 5 }).map((_, index) => (
                  <div key={index} className="flex items-center gap-3">
                    <Skeleton className="h-14 w-14 rounded-full" />
                    <div className="flex-1 space-y-2">
                      <Skeleton className="h-3 w-3/4" />
                      <Skeleton className="h-3 w-1/2" />
                    </div>
                  </div>
                ))}
              </div>
            ) : items.length === 0 ? (
              <p className="px-4 py-10 text-center text-md text-secondary">
                Nothing new right now. Check back later.
              </p>
            ) : (
              items.map((notification) => (
                <Link
                  key={notification.id}
                  to={notification.target_url ?? '#'}
                  className={cn(
                    'flex items-start gap-3 px-3 py-2.5 transition-colors hover:bg-hover/[0.05]',
                    !notification.is_read && 'bg-facebook/[0.06]',
                  )}
                >
                  <span className="relative">
                    <Avatar
                      src={notification.actor?.avatar_url}
                      name={notification.actor?.name ?? 'A'}
                      size="md"
                    />

                    <span
                      className="absolute -bottom-0.5 -right-0.5 grid h-5 w-5 place-items-center rounded-full ring-2 ring-surface"
                      style={{ backgroundColor: ICON_COLORS[notification.icon] ?? '#0866FF', color: '#fff' }}
                    >
                      <Icon name={ICONS[notification.icon] ?? 'Bell'} size={12} />
                    </span>
                  </span>

                  <span className="min-w-0 flex-1">
                    <span className="text-md">
                      <span className="font-semibold">{notification.text.split(' ')[0]}</span>
                      {notification.text.slice(notification.text.indexOf(' ') + 1)}
                    </span>

                    <span className={cn('mt-0.5 block text-sm', !notification.is_read ? 'text-facebook' : 'text-secondary')}>
                      {relativeTime(notification.created_at)}
                    </span>
                  </span>

                  {!notification.is_read && (
                    <span className="mt-2 h-2.5 w-2.5 shrink-0 rounded-full bg-facebook" aria-label="Unread" />
                  )}
                </Link>
              ))
            )}
          </div>

          <button
            type="button"
            onClick={() => navigate('/notifications')}
            className="w-full border-t border-divider py-3 text-center text-md font-semibold text-accent hover:bg-hover/[0.05]"
          >
            See all
          </button>
        </Popover.Content>
      </Popover.Portal>
    </Popover.Root>
  );
}

/** Compact bell used by the mobile tab bar. */
export function MobileNotifications({ count }: { count: number }) {
  return (
    <Link
      to="/notifications"
      className="relative grid h-10 w-10 place-items-center rounded-full bg-elevated"
      aria-label={`Notifications (${count} unread)`}
    >
      <Icon name="Bell" size={22} />

      {count > 0 && (
        <span className="absolute -right-0.5 -top-0.5 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-facebook-red px-1 text-2xs font-semibold text-white">
          {count > 99 ? '99+' : count}
        </span>
      )}
    </Link>
  );
}
