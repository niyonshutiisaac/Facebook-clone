import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Avatar } from '@/components/atoms/Avatar';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Skeleton } from '@/components/atoms/Skeleton';
import { useAcceptRequest, useFriendRequests, useFriends, useSuggestions } from '@/api/queries';
import { useUiStore } from '@/store/ui';

export function FriendsPage() {
  const [tab, setTab] = useState<'requests' | 'suggestions' | 'all'>('requests');

  const { data: requests, isLoading: requestsLoading } = useFriendRequests();
  const { data: suggestions, isLoading: suggestionsLoading } = useSuggestions();
  const { data: friends, isLoading: friendsLoading } = useFriends();
  const accept = useAcceptRequest();
  const { openChat } = useUiStore();

  const tabs = [
    { id: 'requests' as const, label: 'Friend requests', count: requests?.length ?? 0 },
    { id: 'suggestions' as const, label: 'Suggestions', count: suggestions?.length ?? 0 },
    { id: 'all' as const, label: 'All friends', count: friends?.length ?? 0 },
  ];

  return (
    <div className="mx-auto w-full max-w-feed px-2 sm:px-0">
      <header className="mb-4 flex items-center justify-between">
        <h1 className="text-2xl font-bold">Friends</h1>

        <div className="flex items-center gap-2">
          <Button variant="secondary" size="sm">
            <Icon name="Settings" size={16} />
            Manage
          </Button>
        </div>
      </header>

      <div className="mb-4 flex gap-1 overflow-x-auto scrollbar-none">
        {tabs.map((item) => (
          <button
            key={item.id}
            type="button"
            onClick={() => setTab(item.id)}
            className={
              tab === item.id
                ? 'shrink-0 rounded-control bg-facebook/12 px-3 py-2 text-base font-semibold text-facebook'
                : 'shrink-0 rounded-control px-3 py-2 text-base font-semibold text-secondary hover:bg-hover/[0.05]'
            }
          >
            {item.label}
            {item.count > 0 && <span className="ml-1.5 text-sm font-normal">({item.count})</span>}
          </button>
        ))}
      </div>

      {tab === 'requests' &&
        (requestsLoading ? (
          <Skeleton className="h-64 w-full rounded-card" />
        ) : (requests?.length ?? 0) === 0 ? (
          <EmptyState
            icon="UserPlus"
            title="No friend requests"
            body="When someone adds you as a friend, their request will show up here."
          />
        ) : (
          <div className="grid gap-3 sm:grid-cols-2">
            {requests?.map((request) => (
              <div key={request.id} className="card overflow-hidden">
                <div className="h-28 bg-elevated">
                  <img
                    src={`https://picsum.photos/seed/cover-${request.sender.id}/400/160`}
                    alt=""
                    className="h-full w-full object-cover"
                  />
                </div>

                <div className="-mt-8 flex flex-col items-center px-4 pb-4 text-center">
                  <Avatar src={request.sender.avatar_url} name={request.sender.name} size="lg" ring />

                  <Link
                    to={`/profile/${request.sender.username}`}
                    className="mt-2 text-base font-semibold hover:underline"
                  >
                    {request.sender.name}
                  </Link>

                  <p className="text-sm text-secondary">{request.mutual_friends_count} mutual friends</p>

                  {request.message && (
                    <p className="mt-2 rounded-bubble bg-subtle px-3 py-2 text-md italic">
                      “{request.message}”
                    </p>
                  )}

                  <div className="mt-3 flex w-full gap-2">
                    <Button
                      variant="primary"
                      fullWidth
                      onClick={() => accept.mutate(request.id)}
                      loading={accept.isPending}
                    >
                      Confirm
                    </Button>

                    <Button variant="secondary" fullWidth>
                      Delete
                    </Button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        ))}

      {tab === 'suggestions' &&
        (suggestionsLoading ? (
          <Skeleton className="h-64 w-full rounded-card" />
        ) : (
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {suggestions?.map((person) => (
              <div key={person.id} className="card overflow-hidden">
                <div className="h-28 bg-elevated">
                  <img
                    src={`https://picsum.photos/seed/sug-${person.id}/400/160`}
                    alt=""
                    className="h-full w-full object-cover"
                  />
                </div>

                <div className="-mt-8 flex flex-col items-center px-4 pb-4 text-center">
                  <Avatar src={person.avatar_url} name={person.name} size="lg" ring />

                  <Link
                    to={`/profile/${person.username}`}
                    className="mt-2 text-base font-semibold hover:underline"
                  >
                    {person.name}
                  </Link>

                  <p className="text-sm text-secondary">{person.is_online ? 'Active now' : 'Suggested for you'}</p>

                  <div className="mt-3 flex w-full gap-2">
                    <Button variant="primary" fullWidth>
                      Add friend
                    </Button>

                    <Button variant="secondary" fullWidth onClick={() => openChat(`contact-${person.id}`)}>
                      Message
                    </Button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        ))}

      {tab === 'all' &&
        (friendsLoading ? (
          <Skeleton className="h-64 w-full rounded-card" />
        ) : (
          <div className="grid gap-2 sm:grid-cols-2">
            {friends?.map((friend) => (
              <Link
                key={friend.id}
                to={`/profile/${friend.username}`}
                className="flex items-center gap-3 rounded-card bg-surface p-3 shadow-card transition-colors hover:bg-hover/[0.03]"
              >
                <Avatar src={friend.avatar_url} name={friend.name} size="md" online={friend.is_online} />

                <span className="min-w-0 flex-1">
                  <span className="block truncate font-semibold">{friend.name}</span>
                  <span className="block text-sm text-secondary">
                    {friend.is_online ? 'Active now' : 'Friend'}
                  </span>
                </span>

                <Icon name="ChevronRight" size={18} className="text-secondary" />
              </Link>
            ))}
          </div>
        ))}
    </div>
  );
}

function EmptyState({
  icon,
  title,
  body,
}: {
  icon: 'UserPlus' | 'Users' | 'MessageCircle';
  title: string;
  body: string;
}) {
  return (
    <div className="card grid place-items-center p-12 text-center">
      <span className="grid h-14 w-14 place-items-center rounded-full bg-elevated text-secondary">
        <Icon name={icon} size={26} />
      </span>

      <h2 className="mt-3 text-lg font-bold">{title}</h2>
      <p className="mt-1 max-w-sm text-md text-secondary">{body}</p>
    </div>
  );
}
