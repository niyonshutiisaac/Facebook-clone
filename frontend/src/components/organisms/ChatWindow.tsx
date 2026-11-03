import { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { AnimatePresence, motion } from 'framer-motion';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import { IconButton } from '@/components/atoms/IconButton';
import { Spinner } from '@/components/atoms/Spinner';
import { Menu } from '@/components/molecules/Menu';
import { ChatBubble, TypingRow } from '@/components/molecules/ChatBubble';
import { useMarkRead, useMessages, useSendMessage, useTypingIndicator } from '@/api/queries';
import { useAuthStore } from '@/store/auth';
import { cn } from '@/lib/utils';
import type { Conversation } from '@/types/api';

/**
 * One chat window, used both in the dock and on the full-page messenger.
 *
 * The thread is grouped so consecutive messages from the same author share a
 * single avatar, matching Facebook. Typing is debounced on the client (one
 * event every three seconds) and auto-cleared after five seconds of silence.
 */
export function ChatWindow({
  conversation,
  docked = false,
  onClose,
  onMinimise,
  minimised = false,
}: {
  conversation: Conversation;
  docked?: boolean;
  onClose?: () => void;
  onMinimise?: () => void;
  minimised?: boolean;
}) {
  const { user } = useAuthStore();
  const [draft, setDraft] = useState('');

  const { data: messages = [], isLoading } = useMessages(conversation.id);
  const send = useSendMessage(conversation.id);
  const markRead = useMarkRead();
  const typing = useTypingIndicator(conversation.id);

  const scrollRef = useRef<HTMLDivElement>(null);
  const typingTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const lastTypedAt = useRef(0);

  const other = conversation.participants.find((p) => p.id !== user?.id);

  // Mark everything read as soon as the window is focused.
  useEffect(() => {
    if (!minimised) void markRead.mutateAsync(conversation.id);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [conversation.id, minimised]);

  // Keep the thread pinned to the newest message.
  useEffect(() => {
    const node = scrollRef.current;

    if (node) node.scrollTop = node.scrollHeight;
  }, [messages.length, minimised]);

  const grouped = useMemo(
    () =>
      messages.map((message, index) => {
        const previous = messages[index - 1];
        const next = messages[index + 1];

        return {
          message,
          showAvatar: next?.sender_id !== message.sender_id,
          isLastOfGroup: previous?.sender_id !== message.sender_id || index === messages.length - 1,
          isRead: message.delivery_status === 'read',
        };
      }),
    [messages],
  );

  const isTyping = (conversation.typing_user_ids ?? []).some((id) => id !== user?.id);

  const onDraftChange = (value: string) => {
    setDraft(value);

    const now = Date.now();

    // Throttle: at most one typing event every 3 seconds.
    if (value && now - lastTypedAt.current > 3000) {
      lastTypedAt.current = now;
      typing.mutate(true);

      if (typingTimer.current) clearTimeout(typingTimer.current);

      typingTimer.current = setTimeout(() => typing.mutate(false), 5000);
    }

    if (!value) {
      typing.mutate(false);

      if (typingTimer.current) clearTimeout(typingTimer.current);
    }
  };

  const submit = () => {
    if (!draft.trim()) return;

    send.mutate(draft.trim());
    setDraft('');

    if (typingTimer.current) clearTimeout(typingTimer.current);
    typing.mutate(false);
  };

  return (
    <div
      className={cn(
        'flex flex-col overflow-hidden bg-surface',
        docked ? 'h-full w-[22rem] rounded-t-card shadow-modal' : 'h-full rounded-card shadow-card',
      )}
    >
      {/* ----------------------------------------------------------- header */}
      <header className="flex items-center gap-2 border-b border-divider px-3 py-2">
        <Link to={`/profile/${other?.username}`} className="flex min-w-0 flex-1 items-center gap-2">
          <span className="relative">
            <Avatar src={conversation.display_avatar} name={conversation.display_name} size={32} online={other?.is_online} />
          </span>

          <span className="min-w-0">
            <span className="block truncate text-base font-semibold">{conversation.display_name}</span>
            <span className="block truncate text-xs text-secondary">
              {isTyping ? 'typing…' : other?.is_online ? 'Active now' : 'Offline'}
            </span>
          </span>
        </Link>

        <div className="flex items-center gap-0.5 text-facebook">
          <IconButton label="Start a call" size={32}>
            <Icon name="Phone" size={18} />
          </IconButton>

          <IconButton label="Start a video call" size={32}>
            <Icon name="Video" size={18} />
          </IconButton>

          <Menu
            items={[
              { label: 'View profile', icon: 'User', onSelect: () => undefined },
              { label: 'Mute conversation', icon: 'BellOff', onSelect: () => undefined },
              { label: 'Archive', icon: 'Archive', onSelect: () => undefined },
              { divider: true, label: '' },
              { label: 'Delete conversation', icon: 'Trash2', danger: true, onSelect: () => onClose?.() },
            ]}
            trigger={
              <button
                type="button"
                aria-label="Conversation options"
                className="grid h-8 w-8 place-items-center rounded-full text-secondary hover:bg-hover/[0.08]"
              >
                <Icon name="Ellipsis" size={18} />
              </button>
            }
          />

          {docked && (
            <>
              <button
                type="button"
                onClick={onMinimise}
                aria-label={minimised ? 'Expand' : 'Minimise'}
                className="grid h-8 w-8 place-items-center rounded-full text-secondary hover:bg-hover/[0.08]"
              >
                <Icon name="Minus" size={18} />
              </button>

              <button
                type="button"
                onClick={onClose}
                aria-label="Close chat"
                className="grid h-8 w-8 place-items-center rounded-full text-secondary hover:bg-hover/[0.08]"
              >
                <Icon name="X" size={18} />
              </button>
            </>
          )}
        </div>
      </header>

      {/* ----------------------------------------------------------- thread */}
      <AnimatePresence initial={false}>
        {!minimised && (
          <motion.div
            initial={{ height: 0 }}
            animate={{ height: 'auto' }}
            exit={{ height: 0 }}
            transition={{ duration: 0.18, ease: [0.08, 0.52, 0.37, 0.98] }}
            className="flex flex-1 flex-col overflow-hidden"
          >
            <div
              ref={scrollRef}
              className="flex-1 space-y-1.5 overflow-y-auto px-4 py-3 scrollbar-thin"
              role="log"
              aria-live="polite"
            >
              <div className="py-4 text-center">
                <Avatar src={conversation.display_avatar} name={conversation.display_name} size="lg" className="mx-auto" />

                <p className="mt-2 text-base font-semibold">{conversation.display_name}</p>
                <p className="text-sm text-secondary">
                  {conversation.type === 'group'
                    ? `${conversation.participants.length} members`
                    : 'Say hello 👋'}
                </p>
              </div>

              {isLoading ? (
                <div className="grid h-40 place-items-center">
                  <Spinner size={24} className="text-secondary" />
                </div>
              ) : (
                grouped.map(({ message, showAvatar, isLastOfGroup, isRead }) => (
                  <ChatBubble
                    key={message.id}
                    message={message}
                    author={message.sender ?? other ?? { id: 'unknown', name: 'Unknown', avatar_url: null }}
                    showAvatar={showAvatar}
                    isLastOfGroup={isLastOfGroup}
                    isRead={isRead}
                  />
                ))
              )}

              {isTyping && other && <TypingRow user={other} />}
            </div>

            {/* --------------------------------------------------- composer */}
            <div className="flex items-end gap-2 border-t border-divider p-2">
              <button
                type="button"
                aria-label="Add an attachment"
                className="grid h-9 w-9 shrink-0 place-items-center rounded-full text-facebook transition-colors hover:bg-hover/[0.08]"
              >
                <Icon name="Plus" size={22} />
              </button>

              <div className="flex flex-1 items-end gap-1 rounded-bubble bg-subtle px-3 py-1.5">
                <textarea
                  rows={1}
                  value={draft}
                  onChange={(event) => {
                    onDraftChange(event.target.value);
                    event.target.style.height = 'auto';
                    event.target.style.height = `${Math.min(event.target.scrollHeight, 120)}px`;
                  }}
                  onKeyDown={(event) => {
                    if (event.key === 'Enter' && !event.shiftKey) {
                      event.preventDefault();
                      submit();
                    }
                  }}
                  placeholder="Aa"
                  aria-label="Message"
                  className="max-h-28 flex-1 resize-none bg-transparent py-1 text-md outline-none placeholder:text-tertiary"
                />

                <button type="button" aria-label="Insert an emoji" className="p-1 text-secondary">
                  <Icon name="Smile" size={20} />
                </button>
              </div>

              <button
                type="button"
                onClick={submit}
                disabled={!draft.trim()}
                aria-label="Send"
                className={cn(
                  'grid h-9 w-9 shrink-0 place-items-center rounded-full transition-all',
                  draft.trim()
                    ? 'text-facebook hover:bg-facebook/10 active:scale-95'
                    : 'text-tertiary',
                )}
              >
                {send.isPending ? <Spinner size={18} /> : <Icon name="Send" size={20} />}
              </button>
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}

/** The empty state shown before a thread is selected. */
export function ChatEmptyState() {
  return (
    <div className="grid h-full place-items-center text-center">
      <div>
        <span className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-elevated text-secondary">
          <Icon name="MessageCircle" size={30} />
        </span>

        <h2 className="mt-3 text-xl font-bold">Your messages</h2>
        <p className="mt-1 max-w-xs text-md text-secondary">
          Send private photos and messages to a friend or group.
        </p>
      </div>
    </div>
  );
}
