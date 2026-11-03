import { useState } from 'react';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import { Skeleton } from '@/components/atoms/Skeleton';
import { SearchInput } from '@/components/molecules/SearchInput';
import { relativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Conversation } from '@/types/api';

/**
 * The messenger sidebar: one row per thread with the last message preview, an
 * unread dot, and (for your own messages) a small avatar marking read state.
 */
export function ConversationList({
  conversations,
  activeId,
  onSelect,
  loading = false,
  className,
}: {
  conversations: Conversation[];
  activeId?: string;
  onSelect: (conversation: Conversation) => void;
  loading?: boolean;
  className?: string;
}) {
  const [query, setQuery] = useState('');

  const filtered = query
    ? conversations.filter((c) => c.display_name.toLowerCase().includes(query.toLowerCase()))
    : conversations;

  const ordered = [...filtered].sort((a, b) => {
    if (a.is_pinned !== b.is_pinned) return a.is_pinned ? -1 : 1;

    return +new Date(b.updated_at) - +new Date(a.updated_at);
  });

  return (
    <div className={cn('flex h-full flex-col overflow-hidden', className)}>
      <div className="border-b border-divider p-3">
        <div className="mb-2 flex items-center justify-between">
          <h1 className="text-2xl font-bold">Chats</h1>

          <div className="flex items-center gap-1">
            <button
              type="button"
              aria-label="New message"
              className="grid h-8 w-8 place-items-center rounded-full text-primary hover:bg-hover/[0.08]"
            >
              <Icon name="SquarePen" size={18} />
            </button>

            <button
              type="button"
              aria-label="Options"
              className="grid h-8 w-8 place-items-center rounded-full text-primary hover:bg-hover/[0.08]"
            >
              <Icon name="Ellipsis" size={18} />
            </button>
          </div>
        </div>

        <SearchInput value={query} onChange={setQuery} placeholder="Search Messenger" />
      </div>

      <ul className="flex-1 overflow-y-auto p-2 scrollbar-thin">
        {loading &&
          Array.from({ length: 6 }).map((_, index) => (
            <li key={index} className="flex items-center gap-3 p-2">
              <Skeleton className="h-14 w-14 rounded-full" />
              <div className="flex-1 space-y-2">
                <Skeleton className="h-3 w-32" />
                <Skeleton className="h-3 w-48" />
              </div>
            </li>
          ))}

        {ordered.map((conversation) => {
          const last = conversation.last_message;
          const unread = conversation.unread_count > 0;

          return (
            <li key={conversation.id}>
              <button
                type="button"
                onClick={() => onSelect(conversation)}
                className={cn(
                  'flex w-full items-center gap-3 rounded-control p-2 text-left transition-colors',
                  activeId === conversation.id ? 'bg-facebook/[0.12]' : 'hover:bg-hover/[0.05]',
                )}
              >
                <Avatar
                  src={conversation.display_avatar}
                  name={conversation.display_name}
                  size={56}
                  online={conversation.participants[1]?.is_online}
                />

                <div className="min-w-0 flex-1">
                  <div className="flex items-baseline justify-between gap-2">
                    <span
                      className={cn(
                        'truncate text-base',
                        unread ? 'font-bold' : 'font-semibold',
                      )}
                    >
                      {conversation.display_name}
                    </span>

                    <span className={cn('shrink-0 text-xs', unread ? 'font-semibold text-facebook' : 'text-secondary')}>
                      {relativeTime(conversation.updated_at)}
                    </span>
                  </div>

                  <div className="flex items-center gap-1">
                    <span
                      className={cn(
                        'truncate text-sm',
                        unread ? 'font-semibold text-primary' : 'text-secondary',
                      )}
                    >
                      {last
                        ? `${last.is_mine ? 'You: ' : conversation.type === 'group' && last.sender ? `${last.sender.name.split(' ')[0]}: ` : ''}${last.body ?? 'Sent an attachment'}`
                        : 'No messages yet'}
                    </span>

                    {unread && (
                      <span className="ml-auto grid h-5 min-w-[20px] shrink-0 place-items-center rounded-full bg-facebook-red px-1.5 text-2xs font-bold text-white">
                        {conversation.unread_count}
                      </span>
                    )}
                  </div>
                </div>
              </button>
            </li>
          );
        })}

        {!loading && ordered.length === 0 && (
          <li className="p-8 text-center text-md text-secondary">No conversations yet.</li>
        )}
      </ul>
    </div>
  );
}
