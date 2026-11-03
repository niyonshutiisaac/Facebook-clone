import { Icon, type IconName } from '@/components/atoms/Icon';
import { Avatar } from '@/components/atoms/Avatar';
import { compactNumber } from '@/lib/format';
import type { Profile } from '@/types/api';

/**
 * The About tab.
 *
 * Facebook splits it into Overview / Work and education / Places lived / Contact
 * and basic info. Every field is filtered through the profile the API returned,
 * so a field the viewer is not allowed to see is simply absent here — privacy is
 * enforced once, server-side, and the UI renders whatever it was given.
 */
type Section = 'overview' | 'work' | 'places' | 'contact';

export function ProfileAbout({
  profile,
  active,
  onChange,
}: {
  profile: Profile;
  active: Section;
  onChange: (section: Section) => void;
}) {
  const tabs: Array<{ id: Section; label: string }> = [
    { id: 'overview', label: 'Overview' },
    { id: 'work', label: 'Work and education' },
    { id: 'places', label: 'Places lived' },
    { id: 'contact', label: 'Contact and basic info' },
  ];

  return (
    <div className="grid gap-4 lg:grid-cols-[minmax(0,17rem)_minmax(0,1fr)]">
      <nav className="card h-fit p-2" aria-label="About sections">
        <h2 className="px-2 py-2 text-xl font-bold">About</h2>

        <ul className="space-y-0.5">
          {tabs.map((tab) => (
            <li key={tab.id}>
              <button
                type="button"
                onClick={() => onChange(tab.id)}
                className={
                  active === tab.id
                    ? 'w-full rounded-control bg-facebook/12 px-3 py-2 text-left text-base font-semibold text-facebook'
                    : 'w-full rounded-control px-3 py-2 text-left text-base font-medium hover:bg-hover/[0.05]'
                }
              >
                {tab.label}
              </button>
            </li>
          ))}
        </ul>
      </nav>

      <div className="min-w-0 space-y-4">
        {active === 'overview' && <Overview profile={profile} />}
        {active === 'work' && <WorkAndEducation profile={profile} />}
        {active === 'places' && <Places profile={profile} />}
        {active === 'contact' && <ContactAndBasic profile={profile} />}
      </div>
    </div>
  );
}

function Overview({ profile }: { profile: Profile }) {
  const rows: Array<{ icon: IconName; label: string; value?: string | null }> = [
    { icon: 'Briefcase', label: 'Work', value: profile.work?.[0]?.company },
    { icon: 'GraduationCap', label: 'Education', value: profile.education?.[0]?.school },
    { icon: 'MapPin', label: 'Current city', value: profile.current_city },
    { icon: 'House', label: 'Hometown', value: profile.hometown },
    {
      icon: 'Heart',
      label: 'Relationship',
      value: profile.relationship_status?.replace(/_/g, ' '),
    },
  ];

  return (
    <>
      <section className="card p-4">
        <h2 className="text-xl font-bold">About</h2>

        {profile.about && <p className="mt-2 text-md">{profile.about}</p>}

        {profile.favorite_quotes && (
          <blockquote className="mt-3 border-l-2 border-divider pl-3 text-md italic text-secondary">
            {profile.favorite_quotes}
          </blockquote>
        )}
      </section>

      <section className="card p-4">
        <h2 className="text-xl font-bold">Overview</h2>

        <ul className="mt-3 space-y-3">
          {rows
            .filter((row) => Boolean(row.value))
            .map((row) => (
              <li key={row.label} className="flex items-start gap-3">
                <Icon name={row.icon} size={18} className="mt-0.5 shrink-0 text-secondary" />

                <span>
                  <span className="block text-sm text-secondary">{row.label}</span>
                  <span className="block text-base font-medium capitalize">{row.value}</span>
                </span>
              </li>
            ))}
        </ul>
      </section>

      <section className="card p-4">
        <div className="flex items-center justify-between">
          <h2 className="text-xl font-bold">Friends</h2>

          <span className="text-base text-secondary">
            {compactNumber(profile.friends_count)} friends
          </span>
        </div>
      </section>
    </>
  );
}

function WorkAndEducation({ profile }: { profile: Profile }) {
  const work = profile.work ?? [];
  const education = profile.education ?? [];

  return (
    <>
      <section className="card p-4">
        <h2 className="text-xl font-bold">Work</h2>

        {work.length === 0 ? (
          <p className="mt-2 text-md text-secondary">No workplaces to show.</p>
        ) : (
          <ul className="mt-3 space-y-3">
            {work.map((entry, index) => (
              <li key={index} className="flex items-start gap-3">
                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-elevated text-secondary">
                  <Icon name="Building2" size={20} />
                </span>

                <span>
                  <span className="block text-base font-semibold">{entry.company}</span>
                  {entry.position && <span className="block text-md">{entry.position}</span>}
                  {entry.from && (
                    <span className="block text-sm text-secondary">
                      Since {entry.from}
                      {entry.to ? ` · until ${entry.to}` : ''}
                    </span>
                  )}
                </span>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="card p-4">
        <h2 className="text-xl font-bold">Education</h2>

        {education.length === 0 ? (
          <p className="mt-2 text-md text-secondary">No schools to show.</p>
        ) : (
          <ul className="mt-3 space-y-3">
            {education.map((entry, index) => (
              <li key={index} className="flex items-start gap-3">
                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-elevated text-secondary">
                  <Icon name="GraduationCap" size={20} />
                </span>

                <span>
                  <span className="block text-base font-semibold">{entry.school}</span>
                  {entry.degree && <span className="block text-md">{entry.degree}</span>}
                  {entry.to && <span className="block text-sm text-secondary">Class of {entry.to}</span>}
                </span>
              </li>
            ))}
          </ul>
        )}
      </section>
    </>
  );
}

function Places({ profile }: { profile: Profile }) {
  const places = profile.places_lived ?? [];

  return (
    <section className="card p-4">
      <h2 className="text-xl font-bold">Places lived</h2>

      {places.length === 0 ? (
        <p className="mt-2 text-md text-secondary">No places to show.</p>
      ) : (
        <ul className="mt-3 space-y-3">
          {places.map((place, index) => (
            <li key={index} className="flex items-start gap-3">
              <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-elevated text-secondary">
                <Icon name={place.type === 'current' ? 'MapPin' : 'House'} size={20} />
              </span>

              <span>
                <span className="block text-base font-semibold">{place.city}</span>
                <span className="block text-sm capitalize text-secondary">{place.type}</span>
              </span>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

function ContactAndBasic({ profile }: { profile: Profile }) {
  const contact = profile.contact_info ?? {};
  const basic = profile.basic_info ?? {};

  const contactRows: Array<{ icon: IconName; label: string; value?: string | null }> = [
    { icon: 'Link2', label: 'Website', value: contact.website },
    { icon: 'Mail', label: 'Email', value: contact.email },
    { icon: 'Phone', label: 'Phone', value: contact.phone },
    { icon: 'MapPin', label: 'Address', value: contact.address },
  ];

  const basicRows: Array<{ icon: IconName; label: string; value?: string | null }> = [
    { icon: 'Gift', label: 'Birth date', value: basic.birth_date },
    { icon: 'User', label: 'Gender', value: basic.gender },
    { icon: 'Users', label: 'Pronouns', value: basic.pronouns },
    { icon: 'Globe', label: 'Languages', value: basic.languages?.join(', ') },
  ];

  return (
    <>
      <section className="card p-4">
        <div className="flex items-center gap-3">
          <Avatar src={profile.avatar_url} name={profile.name} size="md" />
          <h2 className="text-xl font-bold">Contact info</h2>
        </div>

        <ul className="mt-3 space-y-3">
          {contactRows
            .filter((row) => Boolean(row.value))
            .map((row) => (
              <li key={row.label} className="flex items-start gap-3">
                <Icon name={row.icon} size={18} className="mt-0.5 shrink-0 text-secondary" />
                <span>
                  <span className="block text-sm text-secondary">{row.label}</span>
                  <span className="block text-base">{row.value}</span>
                </span>
              </li>
            ))}
        </ul>

        {contactRows.every((row) => !row.value) && (
          <p className="mt-2 text-md text-secondary">Nothing is visible to you here.</p>
        )}
      </section>

      <section className="card p-4">
        <h2 className="text-xl font-bold">Basic info</h2>

        <ul className="mt-3 space-y-3">
          {basicRows
            .filter((row) => Boolean(row.value))
            .map((row) => (
              <li key={row.label} className="flex items-start gap-3">
                <Icon name={row.icon} size={18} className="mt-0.5 shrink-0 text-secondary" />
                <span>
                  <span className="block text-sm text-secondary">{row.label}</span>
                  <span className="block text-base capitalize">{row.value}</span>
                </span>
              </li>
            ))}
        </ul>

        {basicRows.every((row) => !row.value) && (
          <p className="mt-2 text-md text-secondary">Nothing is visible to you here.</p>
        )}
      </section>
    </>
  );
}
