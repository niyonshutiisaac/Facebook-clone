import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Avatar } from '@/components/atoms/Avatar';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Skeleton } from '@/components/atoms/Skeleton';
import { Menu } from '@/components/molecules/Menu';
import { PRIVACY } from '@/components/molecules/GroupCard';
import { PostCard } from '@/components/organisms/PostCard';
import { PostSkeleton } from '@/components/atoms/Skeleton';
import {
  useCreateGroupPost,
  useGroup,
  useGroupMembers,
  useGroupMemberAction,
  useGroupPosts,
  useJoinGroup,
  useLeaveGroup,
  useModerateGroupPost,
  usePendingGroupPosts,
} from '@/api/queries';
import { useAuthStore } from '@/store/auth';
import { compactNumber, relativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { GroupMember, ID } from '@/types/api';

type Tab = 'discussion' | 'about' | 'members' | 'pending';

/**
 * A group: cover, membership state, and the four surfaces Facebook gives every
 * group — discussion, about, members and (for admins) the moderation queue.
 *
 * The queue only exists when the viewer can moderate; rendering it conditionally
 * keeps the tab bar honest rather than showing a tab that would 403.
 */
export function GroupPage() {
  const { id = '' } = useParams();

  const [tab, setTab] = useState<Tab>('discussion');

  const { data, isLoading } = useGroup(id);
  const group = data?.group;

  const join = useJoinGroup();
  const leave = useLeaveGroup();
  const memberAction = useGroupMemberAction();

  const isAdmin = group?.membership?.is_admin ?? false;
  const isMember = group?.membership?.is_member ?? false;

  if (isLoading) {
    return (
      <div className="mx-auto w-full max-w-[43.75rem] space-y-4 px-2 sm:px-0">
        <Skeleton className="h-56 w-full rounded-card" />
        <Skeleton className="h-24 w-full rounded-card" />
        <PostSkeleton />
      </div>
    );
  }

  if (!group) {
    return (
      <div className="card mx-auto mt-6 w-full max-w-[30rem] p-10 text-center">
        <h1 className="text-xl font-bold">This group isn&apos;t available</h1>
        <p className="mt-1 text-base text-secondary">
          It may have been deleted, or you may not have permission to see it.
        </p>
        <Link to="/groups" className="mt-4 inline-block font-semibold text-accent hover:underline">
          Back to groups
        </Link>
      </div>
    );
  }

  const privacy = PRIVACY[group.privacy];

  const tabs: Array<[Tab, string]> = [
    ['discussion', 'Discussion'],
    ['about', 'About'],
    ['members', `Members${group.members_count ? ` · ${compactNumber(group.members_count)}` : ''}`],
  ];

  if (isAdmin) tabs.push(['pending', 'Pending posts']);

  return (
    <div className="mx-auto w-full max-w-[43.75rem] px-2 sm:px-0">
      {/* ------------------------------------------------------------- cover */}
      <section className="card overflow-hidden">
        <div className="relative h-40 bg-subtle sm:h-56">
          {group.cover_url ? (
            <img src={group.cover_url} alt="" className="h-full w-full object-cover" />
          ) : (
            <div className="h-full w-full bg-gradient-to-br from-facebook/40 to-facebook/10" />
          )}

          <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-4 pt-16">
            <div className="flex items-end gap-3">
              <Avatar
                src={group.avatar_url}
                name={group.name}
                size={88}
                square
                className="hidden rounded-card ring-2 ring-white sm:block"
              />

              <div className="min-w-0 flex-1 text-white">
                <h1 className="text-2xl font-bold drop-shadow">{group.name}</h1>
                <p className="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-base text-white/90">
                  <Icon name={privacy.icon} size={14} />
                  {privacy.label}
                  <span aria-hidden="true">·</span>
                  {compactNumber(group.members_count)} members
                </p>
              </div>
            </div>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2 border-t border-divider p-3">
          <Button
            variant={isMember ? 'secondary' : 'primary'}
            onClick={() => (isMember ? leave.mutate(group.id) : join.mutate(group.id))}
            loading={join.isPending || leave.isPending}
          >
            {isMember ? (
              <>
                <Icon name="Check" size={16} />
                Joined
              </>
            ) : (
              <>
                <Icon name="UserPlus" size={16} />
                Join group
              </>
            )}
          </Button>

          <Button variant="secondary">
            <Icon name="Share2" size={16} />
            Share
          </Button>

          {isAdmin && (
            <Button variant="secondary" onClick={() => setTab('pending')}>
              <Icon name="ShieldCheck" size={16} />
              Admin tools
            </Button>
          )}

          <Menu
            items={[
              { label: 'Invite friends', icon: 'UserPlus' },
              { label: 'Group settings', icon: 'Settings' },
              { label: 'Leave group', icon: 'LogOut', danger: true, onSelect: () => leave.mutate(group.id) },
            ]}
            trigger={
              <Button variant="secondary" aria-label="More options">
                <Icon name="Ellipsis" size={16} />
              </Button>
            }
          />
        </div>
      </section>

      {/* -------------------------------------------------------------- tabs */}
      <nav className="mt-4 flex gap-1 border-b border-divider" aria-label="Group sections">
        {tabs.map(([value, label]) => (
          <button
            key={value}
            type="button"
            onClick={() => setTab(value)}
            aria-current={tab === value}
            className={cn(
              '-mb-px border-b-2 px-3 py-2.5 text-base font-semibold transition-colors',
              tab === value ? 'border-facebook text-facebook' : 'border-transparent text-secondary hover:bg-hover/[0.05]',
            )}
          >
            {label}
          </button>
        ))}
      </nav>

      <div className="mt-4">
        {tab === 'discussion' && (
          <Discussion groupId={group.id} isMember={isMember} requiresApproval={!isAdmin} />
        )}

        {tab === 'about' && <About groupId={group.id} />}

        {tab === 'members' && (
          <Members
            groupId={group.id}
            isAdmin={isAdmin}
            onAction={(userId, action) => memberAction.mutate({ groupId: group.id, userId, action })}
          />
        )}

        {tab === 'pending' && isAdmin && <Pending groupId={group.id} />}
      </div>
    </div>
  );
}

/**
 * The group feed. Posting is only offered to members — Facebook hides the
 * composer from everyone else rather than letting the request fail.
 */
function Discussion({
  groupId,
  isMember,
  requiresApproval,
}: {
  groupId: ID;
  isMember: boolean;
  requiresApproval: boolean;
}) {
  const me = useAuthStore((state) => state.user);
  const { data, isLoading, hasNextPage, fetchNextPage, isFetchingNextPage } = useGroupPosts(groupId);
  const create = useCreateGroupPost(groupId);

  const [draft, setDraft] = useState('');
  const posts = data?.pages.flatMap((page) => page.data) ?? [];

  const submit = () => {
    if (!draft.trim() || create.isPending) return;

    create.mutate(draft.trim());
    setDraft('');
  };

  return (
    <div className="space-y-4">
      {isMember && (
        <section className="card p-3">
          <div className="flex gap-2">
            <Avatar src={me?.avatar_url ?? null} name={me?.name ?? 'You'} size={40} />

            <textarea
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
              rows={draft ? 3 : 1}
              placeholder="Write something to the group…"
              aria-label="Write a post to the group"
              className="min-h-[2.5rem] flex-1 resize-none rounded-bubble bg-subtle px-3 py-2 text-md outline-none ring-inset focus:ring-1 focus:ring-facebook/40"
            />
          </div>

          <div className="mt-2 flex items-center justify-between gap-2 pl-12">
            <p className="text-sm text-secondary">
              {requiresApproval ? 'An admin will review this before it appears.' : 'Visible to group members.'}
            </p>

            <Button variant="primary" size="sm" onClick={submit} disabled={!draft.trim()} loading={create.isPending}>
              Post
            </Button>
          </div>
        </section>
      )}

      {isLoading ? (
        <PostSkeleton />
      ) : posts.length === 0 ? (
        <p className="card p-10 text-center text-base text-secondary">
          Nothing here yet. Be the first to post.
        </p>
      ) : (
        posts.map((post) => <PostCard key={post.id} post={post} />)
      )}

      {hasNextPage && (
        <Button variant="secondary" fullWidth onClick={() => void fetchNextPage()} loading={isFetchingNextPage}>
          Load more posts
        </Button>
      )}
    </div>
  );
}

/** Description, topics, rules and the people who run the group. */
function About({ groupId }: { groupId: ID }) {
  const { data } = useGroup(groupId);

  if (!data) return null;

  const { group, rules, admins } = data;

  return (
    <div className="space-y-4">
      <section className="card p-4">
        <h2 className="text-lg font-bold">About this group</h2>

        {group.description && <p className="mt-2 whitespace-pre-wrap text-md">{group.description}</p>}

        <dl className="mt-4 space-y-2 text-base">
          <div className="flex gap-2">
            <dt className="flex items-center gap-1.5 font-semibold">
              <Icon name={PRIVACY[group.privacy].icon} size={15} />
              Privacy
            </dt>
            <dd className="text-secondary">{PRIVACY[group.privacy].label}</dd>
          </div>

          <div className="flex gap-2">
            <dt className="flex items-center gap-1.5 font-semibold">
              <Icon name="Eye" size={15} />
              Visible
            </dt>
            <dd className="text-secondary">{PRIVACY[group.privacy].blurb}</dd>
          </div>

          <div className="flex gap-2">
            <dt className="flex items-center gap-1.5 font-semibold">
              <Icon name="Clock" size={15} />
              Created
            </dt>
            <dd className="text-secondary">{relativeTime(group.created_at)}</dd>
          </div>
        </dl>

        {group.topics && group.topics.length > 0 && (
          <div className="mt-4 flex flex-wrap gap-2">
            {group.topics.map((topic) => (
              <span key={topic} className="rounded-pill bg-subtle px-3 py-1 text-sm font-semibold text-secondary">
                {topic}
              </span>
            ))}
          </div>
        )}
      </section>

      <section className="card p-4">
        <h2 className="text-lg font-bold">Group rules</h2>

        <ol className="mt-2 space-y-3">
          {rules.map((rule, index) => (
            <li key={rule.id} className="flex gap-3">
              <span className="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-subtle text-sm font-semibold">
                {index + 1}
              </span>

              <span>
                <span className="font-semibold">{rule.title}</span>
                {rule.description && <span className="block text-base text-secondary">{rule.description}</span>}
              </span>
            </li>
          ))}
        </ol>
      </section>

      <section className="card p-4">
        <h2 className="text-lg font-bold">Admins and moderators</h2>

        <ul className="mt-2 space-y-2">
          {admins.map((admin) => (
            <li key={admin.id}>
              <Link to={`/profile/${admin.username}`} className="flex items-center gap-2 rounded-control p-1 hover:bg-hover/[0.05]">
                <Avatar src={admin.avatar_url} name={admin.name} size={40} />
                <span className="min-w-0 flex-1 truncate font-semibold">{admin.name}</span>
                <span className="text-sm text-secondary">Admin</span>
              </Link>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}

/**
 * Members, with the moderation controls attached to each row when the viewer is
 * an admin. Pending requests are their own filter — an admin should not have to
 * read a member list to find the queue.
 */
function Members({
  groupId,
  isAdmin,
  onAction,
}: {
  groupId: ID;
  isAdmin: boolean;
  onAction: (userId: ID, action: 'promote' | 'demote' | 'remove') => void;
}) {
  const [filter, setFilter] = useState<'all' | 'pending'>('all');
  const { data, isLoading } = useGroupMembers(groupId, filter === 'pending' ? 'pending' : undefined);

  const members = data?.data ?? [];

  return (
    <div className="space-y-3">
      <div className="flex gap-1">
        {(
          [
            ['all', 'All members'],
            ['pending', 'Pending requests'],
          ] as Array<['all' | 'pending', string]>
        ).map(([value, label]) => (
          <button
            key={value}
            type="button"
            onClick={() => setFilter(value)}
            className={cn(
              'rounded-pill px-3 py-1.5 text-base font-semibold transition-colors',
              filter === value ? 'bg-facebook/10 text-facebook' : 'text-secondary hover:bg-hover/[0.06]',
            )}
          >
            {label}
          </button>
        ))}
      </div>

      <section className="card divide-y divide-divider">
        {isLoading &&
          Array.from({ length: 5 }, (_, i) => (
            <div key={i} className="flex items-center gap-3 p-3">
              <Skeleton className="h-10 w-10 rounded-full" />
              <Skeleton className="h-4 w-32" />
            </div>
          ))}

        {!isLoading && members.length === 0 && (
          <p className="p-8 text-center text-base text-secondary">
            {filter === 'pending' ? 'No requests waiting.' : 'No members to show.'}
          </p>
        )}

        {members.map((member) => (
          <MemberRow key={member.id} member={member} isAdmin={isAdmin} onAction={onAction} />
        ))}
      </section>
    </div>
  );
}

function MemberRow({
  member,
  isAdmin,
  onAction,
}: {
  member: GroupMember;
  isAdmin: boolean;
  onAction: (userId: ID, action: 'promote' | 'demote' | 'remove') => void;
}) {
  const ROLE_BADGE: Record<string, string> = {
    admin: 'bg-facebook/10 text-facebook',
    moderator: 'bg-facebook-green/15 text-facebook-green',
    member: 'hidden',
  };

  return (
    <article className="flex items-center gap-3 p-3">
      <Avatar src={member.user.avatar_url} name={member.user.name} size={40} online={member.user.is_online} />

      <div className="min-w-0 flex-1">
        <Link to={`/profile/${member.user.username}`} className="truncate font-semibold hover:underline">
          {member.user.name}
        </Link>

        <p className="text-sm text-secondary">
          {member.status === 'pending' ? 'Requested to join' : `Joined ${relativeTime(member.joined_at)}`}
          {member.badge ? ` · ${member.badge.replace('_', ' ')}` : ''}
        </p>
      </div>

      <span className={cn('rounded-pill px-2 py-0.5 text-sm font-semibold', ROLE_BADGE[member.role] ?? 'hidden')}>
        {member.role === 'admin' ? 'Admin' : 'Moderator'}
      </span>

      {isAdmin && (
        <Menu
          items={
            member.status === 'pending'
              ? [
                  { label: 'Approve request', icon: 'Check' },
                  { label: 'Decline request', icon: 'X', danger: true },
                ]
              : [
                  {
                    label: member.role === 'moderator' ? 'Remove as moderator' : 'Make moderator',
                    icon: 'ShieldCheck',
                    onSelect: () => onAction(member.user.id, member.role === 'moderator' ? 'demote' : 'promote'),
                  },
                  { label: 'Remove from group', icon: 'UserMinus', danger: true, onSelect: () => onAction(member.user.id, 'remove') },
                ]
          }
          trigger={
            <button
              type="button"
              aria-label={`Moderate ${member.user.name}`}
              className="grid h-9 w-9 place-items-center rounded-full text-secondary hover:bg-hover/[0.08]"
            >
              <Icon name="Ellipsis" size={18} />
            </button>
          }
        />
      )}
    </article>
  );
}

/** The admin moderation queue: approve or decline posts before they go live. */
function Pending({ groupId }: { groupId: ID }) {
  const { data, isLoading } = usePendingGroupPosts(groupId);
  const moderate = useModerateGroupPost(groupId);

  const posts = data?.data ?? [];

  return (
    <div className="space-y-3">
      <p className="text-base text-secondary">
        {posts.length === 0
          ? 'Nothing is waiting for review.'
          : `${posts.length} ${posts.length === 1 ? 'post is' : 'posts are'} waiting for review.`}
      </p>

      {isLoading && <PostSkeleton />}

      {posts.map((post) => (
        <article key={post.id} className="card p-4">
          <header className="flex items-center gap-2">
            <Avatar src={post.author.avatar_url} name={post.author.name} size={40} />

            <div className="min-w-0">
              <p className="truncate font-semibold">{post.author.name}</p>
              <p className="text-sm text-secondary">Requested {relativeTime(post.created_at)}</p>
            </div>
          </header>

          <p className="mt-3 whitespace-pre-wrap text-md">{post.body}</p>

          <footer className="mt-4 flex gap-2">
            <Button
              variant="primary"
              size="sm"
              onClick={() => moderate.mutate({ postId: post.id, decision: 'approve' })}
              loading={moderate.isPending}
            >
              <Icon name="Check" size={15} />
              Approve
            </Button>

            <Button
              variant="secondary"
              size="sm"
              onClick={() => moderate.mutate({ postId: post.id, decision: 'decline' })}
              disabled={moderate.isPending}
            >
              <Icon name="X" size={15} />
              Decline
            </Button>
          </footer>
        </article>
      ))}
    </div>
  );
}
