import { format, isToday, isYesterday, differenceInCalendarDays } from 'date-fns';

/**
 * Facebook's own relative-time rules:
 *   < 1 min   "now"
 *   < 60 min  "12m"
 *   < 24 h    "3h"
 *   1 day     "Yesterday at 4:12 PM"
 *   ≤ 7 days  "Tuesday at 4:12 PM"
 *   same year "12 March at 4:12 PM"
 *   older     "12 March 2021"
 */
export function relativeTime(iso: string | null | undefined): string {
  if (!iso) return '';

  const date = new Date(iso);
  const seconds = (Date.now() - date.getTime()) / 1000;

  if (Number.isNaN(date.getTime())) return '';

  if (seconds < 60) return 'now';
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m`;
  if (seconds < 86400 && isToday(date)) return `${Math.floor(seconds / 3600)}h`;

  const time = format(date, 'h:mm a');

  if (isYesterday(date)) return `Yesterday at ${time}`;
  if (differenceInCalendarDays(Date.now(), date) < 7) return `${format(date, 'EEEE')} at ${time}`;
  if (date.getFullYear() === new Date().getFullYear()) return `${format(date, 'd MMMM')} at ${time}`;

  return format(date, 'd MMMM yyyy');
}

/** Compact counter: 1.2K, 3.4M — Facebook's abbreviated numbers. */
export function compactNumber(value: number): string {
  if (!Number.isFinite(value)) return '0';
  if (value < 1000) return String(value);
  if (value < 1_000_000) {
    const k = value / 1000;
    return `${k < 10 ? k.toFixed(1).replace(/\.0$/, '') : Math.round(k)}K`;
  }
  const m = value / 1_000_000;
  return `${m < 10 ? m.toFixed(1).replace(/\.0$/, '') : Math.round(m)}M`;
}

export function fullName(first?: string | null, last?: string | null): string {
  return [first, last].filter(Boolean).join(' ');
}

/** "Ava and 3 others" — used by reaction summaries and notification text. */
export function joinNames(names: string[], limit = 2): string {
  if (names.length === 0) return '';
  if (names.length === 1) return names[0]!;

  if (names.length <= limit) {
    return `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}`;
  }

  const remaining = names.length - limit;

  return `${names.slice(0, limit).join(', ')} and ${remaining} ${remaining === 1 ? 'other' : 'others'}`;
}

export function fileToDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result));
    reader.onerror = () => reject(reader.error);
    reader.readAsDataURL(file);
  });
}

export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function formatDuration(seconds: number): string {
  const mins = Math.floor(seconds / 60);
  const secs = Math.floor(seconds % 60);

  return `${mins}:${String(secs).padStart(2, '0')}`;
}

/**
 * Splits plain text into segments so mentions and hashtags can be rendered as
 * links. The backend already escapes the HTML; this only tokenises.
 */
export type Token = { type: 'text' | 'mention' | 'hashtag' | 'url'; value: string };

export function tokenize(text: string): Token[] {
  const pattern = /(https?:\/\/[^\s]+)|(@[\w.]+)|(#[\p{L}\p{N}_]+)/gu;
  const tokens: Token[] = [];
  let lastIndex = 0;

  for (const match of text.matchAll(pattern)) {
    const index = match.index ?? 0;

    if (index > lastIndex) {
      tokens.push({ type: 'text', value: text.slice(lastIndex, index) });
    }

    tokens.push({
      type: match[1] ? 'url' : match[2] ? 'mention' : 'hashtag',
      value: match[0],
    });

    lastIndex = index + match[0].length;
  }

  if (lastIndex < text.length) {
    tokens.push({ type: 'text', value: text.slice(lastIndex) });
  }

  return tokens;
}

/** Initials used by the deterministic avatar placeholder. */
export function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]!.toUpperCase())
    .join('');
}
