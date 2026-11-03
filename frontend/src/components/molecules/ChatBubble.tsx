import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import { cn } from '@/lib/utils';
import { relativeTime } from '@/lib/format';
import { TypingIndicator } from './TypingIndicator';
import type { Message, User } from '@/types/api';

/**
 * A single message row.
 *
 * Facebook's own rules, reproduced:
 *  - your messages are blue and right-aligned; theirs are grey and left
 *  - consecutive messages from the same author share one avatar (only the last
 *    bubble in a group gets the tail)
 *  - emoji-only messages render without a bubble, at ~2× size
 *  - reactions sit under the bubble, overlapping
 */
export function ChatBubble({
  message,
  author,
  showAvatar,
  isLastOfGroup,
  isRead,
}: {
  message: Message;
  author: User;
  showAvatar: boolean;
  isLastOfGroup: boolean;
  isRead?: boolean;
}) {
  const mine = message.is_mine ?? false;
  const emojiOnly = message.is_emoji_only ?? false;

  return (
    <div className={cn('flex items-end gap-2', mine && 'flex-row-reverse')}>
      {/* Avatar slot — kept as a spacer so every row lines up. */}
      <div className="w-7 shrink-0">
        {showAvatar && !mine && <Avatar src={author.avatar_url} name={author.name} size={28} />}
      </div>

      <div className={cn('flex max-w-[70%] flex-col', mine && 'items-end')}>
        {emojiOnly ? (
          <span className="px-2 text-4xl leading-tight">{message.body}</span>
        ) : (
          <div
            className={cn(
              'relative whitespace-pre-wrap break-words px-3 py-2 text-md',
              mine
                ? 'bg-facebook-action text-white'
                : 'bg-subtle text-primary',
              // Bubble tail: the corner nearest the avatar is squared off on the
              // last message of a group.
              mine
                ? isLastOfGroup
                  ? 'rounded-bubble rounded-br-md'
                  : 'rounded-bubble'
                : isLastOfGroup
                  ? 'rounded-bubble rounded-bl-md'
                  : 'rounded-bubble',
              !isLastOfGroup && (mine ? 'rounded-br-bubble' : 'rounded-bl-bubble'),
            )}
            style={{
              borderBottomRightRadius: mine ? (isLastOfGroup ? 6 : 18) : 18,
              borderBottomLeftRadius: mine ? 18 : isLastOfGroup ? 6 : 18,
            }}
          >
            {message.body}
          </div>
        )}

        {isLastOfGroup && (
          <span className="mt-1 px-1 text-2xs text-tertiary">
            {message.pending ? (
              <Icon name="Clock" size={12} className="inline" />
            ) : mine ? (
              isRead ? (
                'Read'
              ) : (
                'Sent'
              )
            ) : (
              relativeTime(message.created_at)
            )}
          </span>
        )}
      </div>
    </div>
  );
}

/** "Marcus is typing…" row that sits at the bottom of the thread. */
export function TypingRow({ user }: { user: User }) {
  return (
    <div className="flex items-end gap-2">
      <Avatar src={user.avatar_url} name={user.name} size={28} />

      <div className="rounded-bubble bg-subtle px-3 py-2.5" style={{ borderBottomLeftRadius: 6 }}>
        <TypingIndicator />
      </div>
    </div>
  );
}
