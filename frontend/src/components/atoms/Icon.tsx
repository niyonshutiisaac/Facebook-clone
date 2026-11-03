import {
  Archive,
  ArrowUp,
  ArrowUpRight,
  BadgeCheck,
  Ban,
  Bell,
  BellOff,
  Bookmark,
  Briefcase,
  Building2,
  CalendarDays,
  Camera,
  ChartColumn,
  Check,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ChevronUp,
  CircleCheckBig,
  CircleHelp,
  Clock,
  Code,
  CornerDownRight,
  Construction,
  Ellipsis,
  Eye,
  EyeOff,
  Film,
  Flag,
  Gift,
  Globe,
  GraduationCap,
  History,
  Heart,
  House,
  Image,
  ImageOff,
  LayoutGrid,
  Link2,
  Lock,
  LogOut,
  Mail,
  MapPin,
  Menu,
  MessageCircle,
  MessageSquare,
  MessageSquareQuote,
  Mic,
  Minus,
  Moon,
  Music2,
  Palette,
  Pause,
  Pencil,
  Phone,
  Play,
  Plus,
  Repeat,
  Rss,
  Search,
  Send,
  Settings,
  Share2,
  ShieldCheck,
  Smile,
  Sparkles,
  SquarePen,
  Star,
  Sticker,
  Store,
  Sun,
  Tag,
  ThumbsUp,
  Trash2,
  TrendingUp,
  Tv,
  User,
  UserCheck,
  UserMinus,
  UserPlus,
  Users,
  UsersRound,
  Video,
  Volume2,
  VolumeX,
  X,
  type LucideIcon,
  type LucideProps,
} from 'lucide-react';
import type { ComponentType, SVGProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * A curated icon registry.
 *
 * `import { icons } from 'lucide-react'` pulls all ~1,500 glyphs into the
 * bundle (≈800 kB). Naming the ones we actually use keeps the icon set at the
 * size of a single sprite, and gives `IconName` real type safety — a typo is a
 * compile error rather than a blank square at runtime.
 */
const registry = {
  Archive,
  ArrowUp,
  ArrowUpRight,
  BadgeCheck,
  Ban,
  Bell,
  BellOff,
  Bookmark,
  Briefcase,
  Building2,
  CalendarDays,
  Camera,
  ChartColumn,
  Check,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ChevronUp,
  CircleCheckBig,
  CircleHelp,
  Clock,
  Code,
  CornerDownRight,
  Construction,
  Ellipsis,
  Eye,
  EyeOff,
  Film,
  Flag,
  Gift,
  Globe,
  GraduationCap,
  History,
  Heart,
  House,
  Image,
  ImageOff,
  LayoutGrid,
  Link2,
  Lock,
  LogOut,
  Mail,
  MapPin,
  Menu,
  MessageCircle,
  MessageSquare,
  MessageSquareQuote,
  Mic,
  Minus,
  Moon,
  Music2,
  Palette,
  Pause,
  Pencil,
  Phone,
  Play,
  Plus,
  Repeat,
  Rss,
  Search,
  Send,
  Settings,
  Share2,
  ShieldCheck,
  Smile,
  Sparkles,
  SquarePen,
  Star,
  Sticker,
  Store,
  Sun,
  Tag,
  ThumbsUp,
  Trash2,
  TrendingUp,
  Tv,
  User,
  UserCheck,
  UserMinus,
  UserPlus,
  Users,
  UsersRound,
  Video,
  Volume2,
  VolumeX,
  X,
} satisfies Record<string, LucideIcon>;

type RegistryName = keyof typeof registry;

/**
 * Glyphs Facebook uses that Lucide does not ship: the "f" mark, the reaction
 * faces and the dark-mode indicator.
 */
type CustomName =
  | 'facebook'
  | 'dark-mode'
  | 'like-fill'
  | 'love-fill'
  | 'care-fill'
  | 'haha-fill'
  | 'wow-fill'
  | 'sad-fill'
  | 'angry-fill'
  | 'newspaper'
  | 'friend-check';

export type IconName = RegistryName | CustomName;

const custom: Record<CustomName, ComponentType<SVGProps<SVGSVGElement>>> = {
  facebook: (props) => (
    <svg viewBox="0 0 36 36" fill="currentColor" aria-hidden="true" {...props}>
      <path d="M20.18 35.87C10.61 34.77 3 26.46 3 16.5 3 9.01 7.92 2.69 14.65 1 20.65-.5 26.41 1.87 29.65 7.05c.81 1.3-.17 2.99-1.65 2.99-1.5 0-2.73 1.2-2.75 2.71-.02 1.86.08 3.72.06 5.58 0 .39-.07.78-.11 1.17-.61 6.21-5.65 11.02-11.87 11.17-3.02.06-6.03-.77-8.57-2.33-.29-.18-.67-.4-.8-.72-.24-.62.17-1.32.83-1.46.81-.16 1.65-.17 2.48-.26 2.6-.28 5.15-.92 7.29-2.35 3.64-2.43 5.87-6.5 5.83-10.88-.03-3.72.06-7.44-.04-11.15-.02-.76-.28-1.52-.53-2.25-.2-.58-1.03-.71-1.44-.24-.51.58-.83 1.31-1.16 2.01-2.71 5.73-7.55 9.79-13.55 11.55-2.13.62-4.36.86-6.55.83H8.6c-1.44 1.98-2.44 4.32-2.79 6.83-.13.94-.17 1.9-.22 2.85.22 6.32 4.11 11.98 10.03 14.22 6.33 2.4 13.6 1.2 18.69-2.89.24-.19.47-.4.71-.6.53-.46 1.34-.16 1.42.55.1.87.12 1.75.06 2.63-.06.88-.7 1.63-1.56 1.79-.87.16-1.75.2-2.63.25Z" />
    </svg>
  ),
  'dark-mode': (props) => (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" {...props}>
      <circle cx="12" cy="12" r="4" fill="currentColor" stroke="none" />
      <path strokeLinecap="round" d="M20 12.05A8.01 8.01 0 1 1 11.95 4a6.5 6.5 0 0 0 8.05 8.05Z" />
    </svg>
  ),
  'like-fill': (props) => (
    <svg viewBox="0 0 24 24" fill="currentColor" {...props}>
      <path d="M1 21h4V9H1v12ZM23 10c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2Z" />
    </svg>
  ),
  'love-fill': (props) => (
    <svg viewBox="0 0 24 24" fill="currentColor" {...props}>
      <path d="M12 21.35 10.55 20C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35Z" />
    </svg>
  ),
  'care-fill': (props) => (
    <svg viewBox="0 0 24 24" fill="currentColor" {...props}>
      <path d="m12 17.27 6.18 3.73-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21 12 17.27Z" />
    </svg>
  ),
  'haha-fill': (props) => (
    <svg viewBox="0 0 24 24" fill="currentColor" {...props}>
      <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20ZM8.5 11a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm7 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm-3.5 7.5c-2.6 0-4.8-1.5-5.6-3.7-.2-.5.3-1 .8-.8 2 .8 4.4.8 6.4 0 .6-.2 1 .3.8.8-.8 2.2-3.8 3.7-6.4 3.7Z" />
    </svg>
  ),
  'wow-fill': (props) => (
    <svg viewBox="0 0 24 24" fill="currentColor" {...props}>
      <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20ZM9 10a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm6 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm-3 6.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5Z" />
    </svg>
  ),
  'sad-fill': (props) => (
    <svg viewBox="0 0 24 24" fill="currentColor" {...props}>
      <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20ZM8.5 10a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm7 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm3 7.2c-.6.5-1.5.4-2-.2-1.1-1.2-2.8-1.9-4.5-1.9s-3.4.7-4.5 1.9c-.5.6-1.4.7-2 .2-.6-.5-.7-1.4-.2-2C7.1 13.3 9.5 12.3 12 12.3s4.9 1 6.7 3c.5.6.4 1.5-.2 1.9Z" />
    </svg>
  ),
  'angry-fill': (props) => (
    <svg viewBox="0 0 24 24" fill="currentColor" {...props}>
      <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20ZM7.5 12.5c1.4 0 2.5 1.1 2.5 2.5 0 .8-1.7 2.5-2.5 2.5S5 15.8 5 15c0-1.4 1.1-2.5 2.5-2.5Zm9 0c1.4 0 2.5 1.1 2.5 2.5 0 .8-1.7 2.5-2.5 2.5S14 15.8 14 15c0-1.4 1.1-2.5 2.5-2.5Zm-8.2-1.3 3.7-.6 3.7.6c.5.1.8.6.7 1.1-.1.5-.6.8-1.1.7l-2.5-.4h-1.6l-2.5.4c-.5.1-1-.2-1.1-.7-.1-.5.2-1 .7-1.1Z" />
    </svg>
  ),
  newspaper: (props) => (
    <svg viewBox="0 0 24 24" fill="currentColor" {...props}>
      <path d="M4 3h13a2 2 0 0 1 2 2v14H6a2 2 0 0 1-2-2V3Zm2 4v2h9V7H6Zm0 4v2h5v-2H6Zm0 4v2h9v-2H6Zm11-8h3v12h-3V7Z" />
    </svg>
  ),
  'friend-check': (props) => (
    <svg viewBox="0 0 24 24" fill="currentColor" {...props}>
      <path d="M9 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-3.3 0-6 1.8-6 4v2h12v-2c0-2.2-2.7-4-6-4Zm8.6-6.2 1.3-1.3 2.1 2.1 4.2-4.2 1.3 1.3-5.5 5.5-3.4-3.4Z" />
    </svg>
  ),
};

export interface IconProps extends Omit<LucideProps, 'ref'> {
  name: IconName;
  className?: string;
}

export function Icon({ name, className, size = 20, ...props }: IconProps) {
  if (name in custom) {
    const Component = custom[name as CustomName];

    return <Component className={cn('shrink-0', className)} width={size} height={size} {...props} />;
  }

  const Component = registry[name as RegistryName] as LucideIcon | undefined;

  if (!Component) {
    if (import.meta.env.DEV) {
      console.warn(`[Icon] Unknown icon "${name}". Add it to the registry in Icon.tsx.`);
    }

    return null;
  }

  return <Component className={cn('shrink-0', className)} size={size} {...props} />;
}
