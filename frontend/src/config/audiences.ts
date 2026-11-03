import type { IconName } from '@/components/atoms/Icon';
import type { Audience } from '@/types/api';

/** The audience picker, in the order Facebook lists them. */
export const AUDIENCES: Array<{
  value: Audience;
  label: string;
  description: string;
  icon: IconName;
}> = [
  { value: 'public', label: 'Public', description: 'Anyone on or off Avimoney', icon: 'Globe' },
  { value: 'friends', label: 'Friends', description: 'Your friends on Avimoney', icon: 'Users' },
  {
    value: 'friends_except',
    label: 'Friends except…',
    description: 'Hide from some friends',
    icon: 'UserMinus',
  },
  { value: 'specific', label: 'Specific friends', description: 'Only show to some friends', icon: 'UserCheck' },
  { value: 'only_me', label: 'Only me', description: 'Only visible to you', icon: 'Lock' },
];
