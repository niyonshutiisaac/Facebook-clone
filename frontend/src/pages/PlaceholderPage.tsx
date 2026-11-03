import { useLocation } from 'react-router-dom';
import { Icon, type IconName } from '@/components/atoms/Icon';

/**
 * Honest empty state for surfaces outside the current vertical slice (Watch,
 * Marketplace, Groups…). It says so plainly rather than faking a feature.
 */
export function PlaceholderPage({
  title,
  icon = 'Construction',
  description = 'This surface is not part of the current build. Feed, profiles, comments and real-time messenger are.',
}: {
  title?: string;
  icon?: IconName;
  description?: string;
}) {
  const { pathname } = useLocation();

  return (
    <div className="mx-auto grid w-full max-w-feed place-items-center px-4 py-20 text-center">
      <span className="grid h-16 w-16 place-items-center rounded-full bg-elevated text-secondary">
        <Icon name={icon} size={30} />
      </span>

      <h1 className="mt-4 text-2xl font-bold">
        {title ?? pathname.split('/').filter(Boolean).at(0)?.replace(/^\w/, (c) => c.toUpperCase()) ?? 'Coming soon'}
      </h1>

      <p className="mt-2 max-w-md text-md text-secondary">{description}</p>
    </div>
  );
}
