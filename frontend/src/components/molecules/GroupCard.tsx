import { Link } from 'react-router-dom';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon, type IconName } from '@/components/atoms/Icon';
import { Skeleton } from '@/components/atoms/Skeleton';
import { compactNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Group, GroupPrivacy } from '@/types/api';

/**
 * How Facebook words each privacy level — the distinction members care about is
 * not "public vs private" but whether a private group can be found at all.
 */
export const PRIVACY: Record<GroupPrivacy, { icon: IconName; label: string; blurb: string }> = {
  public: {
    icon: 'Globe',
    label: 'Public',
    blurb: 'Anyone can see who is in the group and what they post.',
  },
  private_visible: {
    icon: 'Lock',
    label: 'Private · Visible',
    blurb: 'Anyone can find this group. Only members see who is in it and what they post.',
  },
  private_hidden: {
    icon: 'EyeOff',
    label: 'Private · Hidden',
    blurb: 'Only members can find this group.',
  },
};

export function GroupCard({
  group,
  onJoin,
  onLeave,
  joining,
}: {
  group: Group;
  onJoin: (group: Group) => void;
  onLeave: (group: Group) => void;
  joining: boolean;
}) {
  const privacy = PRIVACY[group.privacy];
  const member = group.membership?.is_member ?? false;

  return (
    <article className="card overflow-hidden">
      <Link to={`/groups/${group.slug}`} className="block">
        <div className="relative h-24 bg-subtle">
          {group.cover_url ? (
            <img src={group.cover_url} alt="" className="h-full w-full object-cover" />
          ) : (
            <div className="h-full w-full bg-gradient-to-br from-facebook/40 to-facebook/10" />
          )}

          <div className="absolute -bottom-6 left-3 rounded-card bg-surface p-1 shadow-card">
            <Avatar src={group.avatar_url} name={group.name} size={56} square className="rounded-[10px]" />
          </div>
        </div>
      </Link>

      <div className="px-3 pb-3 pt-8">
        <Link to={`/groups/${group.slug}`} className="block">
          <h3 className="truncate text-base font-semibold hover:underline">{group.name}</h3>
        </Link>

        <p className="mt-0.5 flex items-center gap-1 text-sm text-secondary">
          <Icon name={privacy.icon} size={13} />
          {privacy.label} · {compactNumber(group.members_count)} members
        </p>

        {group.description && <p className="mt-2 line-clamp-2 text-sm text-secondary">{group.description}</p>}

        <div className="mt-3 flex items-center gap-2">
          <button
            type="button"
            onClick={() => (member ? onLeave(group) : onJoin(group))}
            disabled={joining}
            aria-label={member ? `Leave ${group.name}` : `Join ${group.name}`}
            className={cn(
              'flex-1 rounded-control px-3 py-1.5 text-base font-semibold transition-colors',
              member
                ? 'bg-subtle text-primary hover:bg-hover/[0.10]'
                : 'bg-facebook text-white hover:bg-facebook-hover',
              joining && 'opacity-60',
            )}
          >
            {member ? 'Joined' : 'Join group'}
          </button>

          {group.membership?.is_admin && (
            <span className="rounded-control bg-subtle px-2 py-1.5 text-sm font-semibold text-secondary">Admin</span>
          )}
        </div>
      </div>
    </article>
  );
}

export function GroupCardSkeleton() {
  return (
    <div className="card overflow-hidden">
      <Skeleton className="h-24 w-full rounded-none" />
      <div className="space-y-2 p-3 pt-8">
        <Skeleton className="h-4 w-3/4" />
        <Skeleton className="h-3 w-1/2" />
        <Skeleton className="h-8 w-full" />
      </div>
    </div>
  );
}
