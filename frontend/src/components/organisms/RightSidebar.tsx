import { Link, useNavigate } from 'react-router-dom';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import { Skeleton } from '@/components/atoms/Skeleton';
import { Badge } from '@/components/atoms/Badge';
import { useAcceptRequest, useFriendRequests, useOnlineFriends, useTrending } from '@/api/queries';
import { compactNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * The 350px right rail: sponsored strip, friend requests, and the contacts list
 * (which doubles as the launcher for the messenger dock).
 */
export function RightSidebar({ onOpenChat }: { onOpenChat: (conversationId: string) => void }) {
  const { data: requests, isLoading: requestsLoading } = useFriendRequests();
  const { data: contacts, isLoading: contactsLoading } = useOnlineFriends();
  const { data: trending } = useTrending();
  const accept = useAcceptRequest();
  const navigate = useNavigate();

  const pending = requests ?? [];

  return (
    <aside
      aria-label="Contacts and requests"
      className="h-[calc(100vh-3.5rem)] overflow-y-auto pb-6 pl-2 pt-4 scrollbar-thin"
    >
      {/* ------------------------------------------------------- sponsored */}
      <section>
        <h2 className="px-2 text-lg font-bold text-secondary">Sponsored</h2>

        <Link
          to="/sponsored"
          className="mt-2 flex gap-3 rounded-control p-2 transition-colors hover:bg-hover/[0.05]"
        >
          <div className="h-24 w-24 shrink-0 overflow-hidden rounded-card bg-elevated">
            <img
              src="https://picsum.photos/seed/sponsored/240/240"
              alt=""
              loading="lazy"
              className="h-full w-full object-cover"
            />
          </div>

          <div className="min-w-0">
            <p className="text-base font-semibold">Studio Neat Coffee</p>
            <p className="truncate text-sm text-secondary">studioneat.example</p>
            <p className="mt-1 line-clamp-2 text-sm text-secondary">
              Slow mornings, better coffee. Free shipping on your first bag.
            </p>
          </div>
        </Link>
      </section>

      <hr className="my-4 border-divider" />

      {/* -------------------------------------------------- friend requests */}
      {requestsLoading ? (
        <Skeleton className="h-24 w-full rounded-card" />
      ) : pending.length > 0 ? (
        <section>
          <div className="flex items-center justify-between px-2">
            <h2 className="text-lg font-bold text-secondary">Friend requests</h2>
            <Link to="/friends/requests" className="text-sm font-semibold text-accent hover:underline">
              See all
            </Link>
          </div>

          <ul className="mt-1 space-y-1">
            {pending.slice(0, 4).map((request) => (
              <li key={request.id} className="flex items-start gap-2 rounded-control p-2 hover:bg-hover/[0.05]">
                <Avatar src={request.sender.avatar_url} name={request.sender.name} size="md" />

                <div className="min-w-0 flex-1">
                  <p className="truncate text-base font-semibold">{request.sender.name}</p>
                  <p className="text-sm text-secondary">{request.mutual_friends_count} mutual friends</p>

                  <div className="mt-2 flex gap-2">
                    <button
                      type="button"
                      onClick={() => accept.mutate(request.id)}
                      disabled={accept.isPending}
                      className="flex-1 rounded-control bg-facebook-action px-3 py-1.5 text-sm font-semibold text-white hover:bg-facebook-action-hover disabled:opacity-60"
                    >
                      Confirm
                    </button>

                    <button
                      type="button"
                      className="flex-1 rounded-control bg-elevated px-3 py-1.5 text-sm font-semibold hover:bg-hover/[0.08]"
                    >
                      Delete
                    </button>
                  </div>
                </div>
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      <hr className="my-4 border-divider" />

      {/* ---------------------------------------------------------- trending */}
      {trending && trending.length > 0 && (
        <>
          <section>
            <h2 className="px-2 text-lg font-bold text-secondary">Trending</h2>

            <ul className="mt-1">
              {trending.slice(0, 4).map((topic) => (
                <li key={topic.id}>
                  <button
                    type="button"
                    onClick={() => navigate(`/search?q=${encodeURIComponent(topic.name)}`)}
                    aria-label={`Search for ${topic.name}`}
                    className="flex w-full items-center gap-2 rounded-control p-2 text-left hover:bg-hover/[0.05]"
                  >
                    <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-elevated text-secondary">
                      <Icon name="TrendingUp" size={18} />
                    </span>

                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-base font-semibold">{topic.name}</span>
                      <span className="block text-sm text-secondary">
                        {compactNumber(topic.posts)} posts
                      </span>
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          </section>

          <hr className="my-4 border-divider" />
        </>
      )}

      {/* ---------------------------------------------------------- contacts */}
      <section>
        <div className="flex items-center justify-between px-2">
          <h2 className="text-lg font-bold text-secondary">Contacts</h2>

          <div className="flex items-center gap-1 text-secondary">
            <button type="button" className="rounded-full p-1.5 hover:bg-hover/[0.08]" aria-label="Video call">
              <Icon name="Video" size={16} />
            </button>
            <button type="button" className="rounded-full p-1.5 hover:bg-hover/[0.08]" aria-label="Search contacts">
              <Icon name="Search" size={16} />
            </button>
            <button type="button" className="rounded-full p-1.5 hover:bg-hover/[0.08]" aria-label="More options">
              <Icon name="Ellipsis" size={16} />
            </button>
          </div>
        </div>

        <ul className="mt-1">
          {contactsLoading &&
            Array.from({ length: 6 }).map((_, index) => (
              <li key={index} className="flex items-center gap-3 p-2">
                <Skeleton className="h-8 w-8 rounded-full" />
                <Skeleton className="h-3 w-28" />
              </li>
            ))}

          {(contacts ?? []).map((contact) => (
            <li key={contact.id}>
              <button
                type="button"
                onClick={() => onOpenChat(`contact-${contact.id}`)}
                className="flex w-full items-center gap-3 rounded-control p-2 text-left transition-colors hover:bg-hover/[0.05]"
              >
                <span className="relative">
                  <Avatar src={contact.avatar_url} name={contact.name} size={32} online />
                </span>

                <span className="truncate text-base font-medium">{contact.name}</span>
              </button>
            </li>
          ))}
        </ul>
      </section>

      <footer className="mt-6 px-2 text-xs leading-relaxed text-tertiary">
        <p className={cn('max-w-xs')}>
          Avimoney is a demonstration build. It is not affiliated with Meta Platforms, Inc.
        </p>
      </footer>
    </aside>
  );
}

export function RightSidebarBadge({ children }: { children: React.ReactNode }) {
  return <Badge tone="accent">{children}</Badge>;
}
