import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { ProfileHeader } from '@/components/organisms/ProfileHeader';
import { ProfileAbout } from '@/components/organisms/ProfileAbout';
import { PostComposer } from '@/components/organisms/PostComposer';
import { PostCard } from '@/components/organisms/PostCard';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import { Skeleton } from '@/components/atoms/Skeleton';
import { compactNumber } from '@/lib/format';
import { useAuthStore } from '@/store/auth';
import { useProfile, useTimeline } from '@/api/queries';

export function ProfilePage() {
  const { username = '' } = useParams();
  const { user } = useAuthStore();
  const [tab, setTab] = useState('Posts');
  const [aboutSection, setAboutSection] = useState<'overview' | 'work' | 'places' | 'contact'>(
    'overview',
  );

  const { data, isLoading } = useProfile(username);
  const { data: timeline, isLoading: timelineLoading } = useTimeline(data?.profile.id ?? '');

  const profile = data?.profile ?? (username === user?.username ? undefined : undefined);
  const posts = timeline?.data ?? [];

  if (isLoading) {
    return (
      <div className="mx-auto max-w-profile">
        <Skeleton className="h-[350px] w-full rounded-none" />
        <div className="px-4">
          <Skeleton className="-mt-8 h-[10.5rem] w-[10.5rem] rounded-full ring-4 ring-surface" />
          <Skeleton className="mt-4 h-8 w-64" />
        </div>
      </div>
    );
  }

  if (!profile) {
    return (
      <div className="mx-auto max-w-profile px-4 py-16 text-center">
        <h1 className="text-2xl font-bold">This page isn&apos;t available</h1>
        <p className="mt-2 text-md text-secondary">
          The link may be broken, or the page may have been removed.
        </p>
      </div>
    );
  }

  const tabs: Record<string, JSX.Element> = {
    Posts: (
      <div className="grid gap-4 lg:grid-cols-[minmax(0,21rem)_minmax(0,1fr)]">
        {/* About card */}
        <aside className="card h-fit p-4">
          <h2 className="text-xl font-bold">Intro</h2>

          {profile.about && <p className="mt-2 text-center text-md">{profile.about}</p>}

          <ul className="mt-3 space-y-2.5 text-md">
            {profile.headline && (
              <li className="flex items-start gap-2">
                <Icon name="Briefcase" size={18} className="mt-0.5 shrink-0 text-secondary" />
                <span>{profile.headline}</span>
              </li>
            )}

            {profile.work?.[0] && (
              <li className="flex items-start gap-2">
                <Icon name="Building2" size={18} className="mt-0.5 shrink-0 text-secondary" />
                <span>
                  Works at <span className="font-semibold">{profile.work[0].company}</span>
                </span>
              </li>
            )}

            {profile.education?.[0] && (
              <li className="flex items-start gap-2">
                <Icon name="GraduationCap" size={18} className="mt-0.5 shrink-0 text-secondary" />
                <span>
                  Studied at <span className="font-semibold">{profile.education[0].school}</span>
                </span>
              </li>
            )}

            {profile.current_city && (
              <li className="flex items-start gap-2">
                <Icon name="MapPin" size={18} className="mt-0.5 shrink-0 text-secondary" />
                <span>
                  Lives in <span className="font-semibold">{profile.current_city}</span>
                </span>
              </li>
            )}

            {profile.hometown && (
              <li className="flex items-start gap-2">
                <Icon name="House" size={18} className="mt-0.5 shrink-0 text-secondary" />
                <span>
                  From <span className="font-semibold">{profile.hometown}</span>
                </span>
              </li>
            )}
          </ul>

          <div className="mt-4 grid grid-cols-3 gap-2 border-t border-divider pt-3 text-center">
            <div>
              <p className="text-lg font-bold">{compactNumber(profile.friends_count)}</p>
              <p className="text-sm text-secondary">Friends</p>
            </div>
            <div>
              <p className="text-lg font-bold">{compactNumber(profile.followers_count ?? 0)}</p>
              <p className="text-sm text-secondary">Followers</p>
            </div>
            <div>
              <p className="text-lg font-bold">{compactNumber(profile.posts_count ?? 0)}</p>
              <p className="text-sm text-secondary">Posts</p>
            </div>
          </div>
        </aside>

        {/* Timeline */}
        <div className="min-w-0 space-y-4">
          {profile.can_edit && <PostComposer />}

          {timelineLoading ? (
            <Skeleton className="h-64 w-full rounded-card" />
          ) : posts.length === 0 ? (
            <div className="card p-8 text-center text-md text-secondary">
              Nothing here yet.
            </div>
          ) : (
            posts.map((post) => <PostCard key={post.id} post={post} />)
          )}
        </div>
      </div>
    ),

    Friends: (
      <div className="card p-4">
        <h2 className="mb-4 text-xl font-bold">{compactNumber(profile.friends_count)} friends</h2>

        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
          {(data?.friends_preview ?? []).map((friend) => (
            <div key={friend.id} className="flex items-center gap-3 rounded-control p-2 hover:bg-hover/[0.05]">
              <Avatar src={friend.avatar_url} name={friend.name} size="md" />
              <span className="truncate text-base font-semibold">{friend.name}</span>
            </div>
          ))}
        </div>
      </div>
    ),

    About: (
      <ProfileAbout profile={profile} active={aboutSection} onChange={setAboutSection} />
    ),

    Photos: (
      <div className="card p-4">
        <h2 className="mb-4 text-xl font-bold">Photos</h2>

        <div className="grid grid-cols-2 gap-1 sm:grid-cols-3 lg:grid-cols-4">
          {Array.from({ length: 12 }, (_, index) => (
            <div key={index} className="aspect-square overflow-hidden rounded-card bg-elevated">
              <img
                src={`https://picsum.photos/seed/photos-${profile.id}-${index}/400/400`}
                alt=""
                loading="lazy"
                className="h-full w-full object-cover"
              />
            </div>
          ))}
        </div>
      </div>
    ),
  };

  return (
    <div className="mx-auto max-w-profile">
      <ProfileHeader
        profile={profile}
        friendsPreview={(data?.friends_preview ?? []) as never}
        activeTab={tab}
        onTabChange={setTab}
      />

      <div className="mt-4 bg-canvas px-4 pb-16">
        {tabs[tab] ?? (
          <div className="card p-8 text-center text-md text-secondary">
            This section is coming soon.
          </div>
        )}
      </div>
    </div>
  );
}
