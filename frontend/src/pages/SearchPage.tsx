import { useEffect, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import { SearchInput } from '@/components/molecules/SearchInput';
import { PostCard } from '@/components/organisms/PostCard';
import { PostSkeleton, Skeleton } from '@/components/atoms/Skeleton';
import {
  useClearRecentSearches,
  useRecentSearches,
  useSearchResults,
  useTrending,
} from '@/api/queries';
import { compactNumber, relativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { SearchTab, User } from '@/types/api';

const TABS: Array<[SearchTab, string]> = [
  ['all', 'Top'],
  ['people', 'People'],
  ['posts', 'Posts'],
  ['groups', 'Groups'],
];

/**
 * The results page, reached from the header (`?q=`) or the trending rail.
 *
 * With no query it is Facebook's "before you type" state: recent searches you
 * can clear, and what is trending. With a query it is a tabbed result set, one
 * request per tab — the backend narrows by `type` so switching tabs is a small
 * round trip rather than a refetch of everything.
 */
export function SearchPage() {
  const [params, setParams] = useSearchParams();
  const navigate = useNavigate();

  const query = params.get('q') ?? '';
  const tab = (params.get('type') as SearchTab | null) ?? 'all';

  const [draft, setDraft] = useState(query);

  // Keep the field in step with the URL when the user arrives from a link
  // (the trending rail, a suggestion) rather than by typing here.
  useEffect(() => setDraft(query), [query]);

  const { data, isLoading } = useSearchResults(query, tab);

  const results = data;
  const total =
    (results?.people.length ?? 0) + (results?.posts.length ?? 0) + (results?.groups.length ?? 0);

  const setTab = (next: SearchTab) => {
    const nextParams = new URLSearchParams(params);

    if (next === 'all') nextParams.delete('type');
    else nextParams.set('type', next);

    setParams(nextParams, { replace: true });
  };

  const submit = (term: string) => {
    const trimmed = term.trim();

    if (!trimmed) return;

    navigate(`/search?q=${encodeURIComponent(trimmed)}`);
  };

  return (
    <div className="mx-auto w-full max-w-[43.75rem] px-2 sm:px-0">
      <div className="card mb-4 p-3">
        <SearchInput
          value={draft}
          onChange={setDraft}
          placeholder="Search Avimoney"
          autoFocus
          onKeyDown={(event) => {
            if (event.key === 'Enter') submit(draft);
          }}
        />
      </div>

      {query.trim().length === 0 ? (
        <EmptyState onPick={submit} />
      ) : (
        <>
          <nav className="mb-4 flex gap-1 border-b border-divider" aria-label="Result types">
            {TABS.map(([value, label]) => (
              <button
                key={value}
                type="button"
                onClick={() => setTab(value)}
                aria-current={tab === value}
                className={cn(
                  '-mb-px border-b-2 px-3 py-2.5 text-base font-semibold transition-colors',
                  tab === value
                    ? 'border-facebook text-facebook'
                    : 'border-transparent text-secondary hover:bg-hover/[0.05]',
                )}
              >
                {label}
              </button>
            ))}
          </nav>

          {isLoading ? (
            <div className="space-y-4">
              {Array.from({ length: 3 }, (_, i) => (
                <PostSkeleton key={i} />
              ))}
            </div>
          ) : total === 0 ? (
            <div className="card p-10 text-center">
              <p className="text-lg font-semibold">No results for “{query}”</p>
              <p className="mt-1 text-base text-secondary">
                Check the spelling, or try a different word — people, posts and groups are all searchable.
              </p>
            </div>
          ) : (
            <div className="space-y-4">
              {(results?.people.length ?? 0) > 0 && (
                <section className="card overflow-hidden">
                  <h2 className="px-4 pb-1 pt-3 text-lg font-bold">People</h2>

                  {results?.people.map((person) => (
                    <PersonRow key={person.id} person={person} />
                  ))}

                  {tab === 'all' && (results?.people.length ?? 0) >= 5 && (
                    <button
                      type="button"
                      onClick={() => setTab('people')}
                      className="w-full border-t border-divider px-4 py-2.5 text-base font-semibold text-accent hover:bg-hover/[0.05]"
                    >
                      See all people
                    </button>
                  )}
                </section>
              )}

              {(results?.groups.length ?? 0) > 0 && (
                <section className="card overflow-hidden">
                  <h2 className="px-4 pb-1 pt-3 text-lg font-bold">Groups</h2>

                  {results?.groups.map((group) => (
                    <Link
                      key={group.id}
                      to={`/groups/${group.slug}`}
                      className="flex items-center gap-3 px-4 py-2.5 hover:bg-hover/[0.05]"
                    >
                      <Avatar src={group.avatar_url} name={group.name} size={44} square className="rounded-[10px]" />

                      <span className="min-w-0 flex-1">
                        <span className="block truncate font-semibold">{group.name}</span>
                        <span className="block text-sm text-secondary">
                          {compactNumber(group.members_count)} members
                          {group.description ? ` · ${group.description.slice(0, 60)}` : ''}
                        </span>
                      </span>

                      <Icon name="ChevronRight" size={18} className="text-secondary" />
                    </Link>
                  ))}
                </section>
              )}

              {(results?.posts.length ?? 0) > 0 && (
                <section>
                  <h2 className="mb-2 px-1 text-lg font-bold">Posts</h2>

                  <div className="space-y-4">
                    {results?.posts.map((post) => (
                      <PostCard key={post.id} post={post} />
                    ))}
                  </div>
                </section>
              )}
            </div>
          )}
        </>
      )}
    </div>
  );
}

function PersonRow({ person }: { person: User }) {
  return (
    <Link to={`/profile/${person.username ?? person.id}`} className="flex items-center gap-3 px-4 py-2.5 hover:bg-hover/[0.05]">
      <Avatar src={person.avatar_url} name={person.name} size={48} online={person.is_online} />

      <span className="min-w-0 flex-1">
        <span className="block truncate font-semibold">{person.name}</span>
        {person.username && <span className="block text-sm text-secondary">@{person.username}</span>}
      </span>

      <span className="rounded-control bg-subtle px-3 py-1.5 text-base font-semibold">
        {person.relationship?.is_friend ? 'Friends' : 'View profile'}
      </span>
    </Link>
  );
}

/**
 * Recent searches and trends.
 *
 * Recent searches are the reason the endpoint exists: the row is removable, and
 * "Clear all" removes the whole history rather than hiding it for a session.
 */
function EmptyState({ onPick }: { onPick: (term: string) => void }) {
  const { data: recent, isLoading: recentLoading } = useRecentSearches();
  const { data: trending } = useTrending();
  const clear = useClearRecentSearches();

  return (
    <div className="space-y-4">
      <section className="card overflow-hidden">
        <div className="flex items-center justify-between px-4 pb-1 pt-3">
          <h2 className="text-lg font-bold">Recent searches</h2>

          {recent && recent.length > 0 && (
            <button
              type="button"
              onClick={() => clear.mutate(undefined)}
              className="text-base font-semibold text-accent hover:underline"
            >
              Clear all
            </button>
          )}
        </div>

        {recentLoading && (
          <div className="space-y-2 p-4">
            <Skeleton className="h-4 w-40" />
            <Skeleton className="h-4 w-56" />
          </div>
        )}

        {!recentLoading && (recent ?? []).length === 0 && (
          <p className="px-4 py-6 text-base text-secondary">
            You have no recent searches. What you look for will show up here.
          </p>
        )}

        {(recent ?? []).map((item) => (
          <div key={item.id} className="flex items-center gap-3 px-4 py-2 hover:bg-hover/[0.05]">
            <button type="button" onClick={() => onPick(item.query)} className="flex min-w-0 flex-1 items-center gap-3 text-left">
              <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-elevated text-secondary">
                <Icon name="Clock" size={17} />
              </span>

              <span className="min-w-0">
                <span className="block truncate text-md font-medium">{item.query}</span>
                <span className="block text-sm text-secondary">Searched {relativeTime(item.last_searched_at)}</span>
              </span>
            </button>

            <button
              type="button"
              onClick={() => clear.mutate(item.id)}
              aria-label={`Remove ${item.query} from recent searches`}
              className="grid h-8 w-8 shrink-0 place-items-center rounded-full text-secondary hover:bg-hover/[0.08]"
            >
              <Icon name="X" size={16} />
            </button>
          </div>
        ))}
      </section>

      {trending && trending.length > 0 && (
        <section className="card overflow-hidden">
          <h2 className="px-4 pb-1 pt-3 text-lg font-bold">Trending</h2>

          {trending.map((topic) => (
            <button
              key={topic.id}
              type="button"
              onClick={() => onPick(topic.name)}
              className="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-hover/[0.05]"
            >
              <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-elevated text-secondary">
                <Icon name="TrendingUp" size={17} />
              </span>

              <span className="min-w-0 flex-1">
                <span className="block truncate text-md font-semibold">{topic.name}</span>
                <span className="block text-sm text-secondary">{compactNumber(topic.posts)} posts</span>
              </span>

              <Icon name="ChevronRight" size={18} className="text-secondary" />
            </button>
          ))}
        </section>
      )}
    </div>
  );
}
