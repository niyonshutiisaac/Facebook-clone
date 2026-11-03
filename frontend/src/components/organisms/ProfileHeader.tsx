import { Avatar } from '@/components/atoms/Avatar';
import { Button } from '@/components/atoms/Button';
import { Icon } from '@/components/atoms/Icon';
import { Skeleton } from '@/components/atoms/Skeleton';
import { Menu } from '@/components/molecules/Menu';
import { Tooltip } from '@/components/molecules/Tooltip';
import { compactNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useUiStore } from '@/store/ui';
import type { Profile } from '@/types/api';

/**
 * The profile hero: cover photo, oversized avatar with a 4px white ring, the
 * name with a verified badge, the friend count, and the tab bar.
 */
export function ProfileHeader({
  profile,
  loading = false,
  friendsPreview = [],
  activeTab,
  onTabChange,
}: {
  profile?: Profile;
  loading?: boolean;
  friendsPreview?: Profile[];
  activeTab: string;
  onTabChange: (tab: string) => void;
}) {
  const { openChat } = useUiStore();

  if (loading || !profile) {
    return (
      <div>
        <Skeleton className="h-[200px] w-full rounded-none sm:h-[350px]" />
        <div className="mx-auto max-w-profile px-4">
          <div className="-mt-8 flex flex-col gap-4 sm:flex-row sm:items-end">
            <Skeleton className="h-[10.5rem] w-[10.5rem] rounded-full ring-4 ring-surface" />
            <div className="flex-1 space-y-3 pb-4">
              <Skeleton className="h-7 w-64" />
              <Skeleton className="h-4 w-32" />
            </div>
          </div>
        </div>
      </div>
    );
  }

  const tabs = ['Posts', 'About', 'Friends', 'Photos', 'Videos', 'Reels', 'More'];

  return (
    <div>
      {/* ------------------------------------------------------------ cover */}
      <div className="relative h-[200px] w-full overflow-hidden bg-elevated sm:h-[350px]">
        {profile.cover_url ? (
          <img src={profile.cover_url} alt="" className="h-full w-full object-cover" />
        ) : (
          <div className="h-full w-full bg-gradient-to-br from-slate-300 to-slate-500" />
        )}

        {profile.can_edit && (
          <button
            type="button"
            className="absolute bottom-4 right-4 flex items-center gap-2 rounded-control bg-surface/95 px-3 py-2 text-base font-semibold shadow-card hover:bg-surface"
          >
            <Icon name="Camera" size={18} />
            {profile.cover_url ? 'Edit cover photo' : 'Add cover photo'}
          </button>
        )}
      </div>

      <div className="mx-auto max-w-profile px-4">
        {/* ------------------------------------------------------- identity */}
        <div className="-mt-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div className="relative">
              <div className="rounded-full ring-4 ring-surface">
                <Avatar
                  src={profile.avatar_url}
                  name={profile.name}
                  size="xl"
                  className="h-[10.5rem] w-[10.5rem]"
                />
              </div>

              {profile.can_edit && (
                <button
                  type="button"
                  aria-label="Edit profile picture"
                  className="absolute bottom-2 right-2 grid h-10 w-10 place-items-center rounded-full bg-elevated shadow-card hover:bg-hover/[0.08]"
                >
                  <Icon name="Camera" size={20} />
                </button>
              )}
            </div>

            <div className="pb-3">
              <h1 className="flex items-center gap-2 text-3xl font-bold">
                {profile.name}
                {profile.is_verified && <Icon name="BadgeCheck" size={22} className="text-facebook" />}
              </h1>

              <p className="mt-1 text-base text-secondary">{profile.headline}</p>

              <p className="mt-0.5 text-base font-semibold text-secondary">
                {compactNumber(profile.friends_count)} friends
              </p>

              {friendsPreview.length > 0 && (
                <div className="mt-2 flex items-center">
                  {friendsPreview.slice(0, 7).map((friend, index) => (
                    <span key={friend.id} style={{ marginLeft: index === 0 ? 0 : -8 }}>
                      <Avatar src={friend.avatar_url} name={friend.name} size={32} ring />
                    </span>
                  ))}

                  {profile.friends_count > friendsPreview.length && (
                    <span className="ml-2 text-sm text-secondary">
                      +{compactNumber(profile.friends_count - friendsPreview.length)}
                    </span>
                  )}
                </div>
              )}
            </div>
          </div>

          {/* ------------------------------------------------------- actions */}
          <div className="flex shrink-0 items-center gap-2 pb-3">
            {profile.can_edit ? (
              <>
                <Button
                  variant="primary"
                  onClick={() => undefined}
                  className="gap-2"
                >
                  <Icon name="Plus" size={18} />
                  Add to story
                </Button>

                <Button variant="secondary" onClick={() => undefined} className="gap-2">
                  <Icon name="Pencil" size={16} />
                  Edit profile
                </Button>
              </>
            ) : (
              <>
                <Button variant="primary" onClick={() => undefined} className="gap-2">
                  <Icon name="UserPlus" size={18} />
                  Add friend
                </Button>

                <Button
                  variant="secondary"
                  onClick={() => openChat(`contact-${profile.id}`)}
                  className="gap-2"
                >
                  <Icon name="MessageCircle" size={18} />
                  Message
                </Button>

                <Menu
                  items={[
                    { label: 'Follow', icon: 'Rss', onSelect: () => undefined },
                    { label: 'Add to close friends', icon: 'Star', onSelect: () => undefined },
                    { divider: true, label: '' },
                    { label: 'Block', icon: 'Ban', danger: true, onSelect: () => undefined },
                    { label: 'Report profile', icon: 'Flag', danger: true, onSelect: () => undefined },
                  ]}
                  trigger={
                    <Button variant="secondary" aria-label="More actions">
                      <Icon name="Ellipsis" size={18} />
                    </Button>
                  }
                />
              </>
            )}
          </div>
        </div>

        {/* ------------------------------------------------------------ tabs */}
        <div className="mt-4 flex items-center gap-1 overflow-x-auto border-t border-divider pt-2 scrollbar-none">
          {tabs.map((tab) => (
            <button
              key={tab}
              type="button"
              onClick={() => onTabChange(tab)}
              className={cn(
                'relative shrink-0 rounded-control px-4 py-3 text-base font-semibold transition-colors',
                activeTab === tab ? 'text-facebook' : 'text-secondary hover:bg-hover/[0.05]',
              )}
            >
              {tab}

              {activeTab === tab && (
                <span className="absolute inset-x-2 -bottom-px h-[3px] rounded-t-full bg-facebook" />
              )}
            </button>
          ))}

          {profile.can_edit && (
            <Tooltip content="More">
              <button
                type="button"
                className="ml-auto grid h-9 w-9 place-items-center rounded-control text-secondary hover:bg-hover/[0.05]"
                aria-label="More tabs"
              >
                <Icon name="Ellipsis" size={20} />
              </button>
            </Tooltip>
          )}
        </div>
      </div>
    </div>
  );
}
