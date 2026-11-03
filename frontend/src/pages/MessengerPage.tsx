import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ConversationList } from '@/components/organisms/ConversationList';
import { ChatWindow, ChatEmptyState } from '@/components/organisms/ChatWindow';
import { useConversations } from '@/api/queries';
import { useUiStore } from '@/store/ui';

/**
 * Full-page messenger: conversation list on the left, active thread on the
 * right. Selecting a thread also opens it in the dock, so navigating away keeps
 * the chat alive.
 */
export function MessengerPage() {
  const { data: conversations = [], isLoading } = useConversations();
  const { openChat } = useUiStore();
  const navigate = useNavigate();

  const [activeId, setActiveId] = useState<string | null>(null);
  const active = conversations.find((c) => c.id === activeId) ?? null;

  // Default to the most recent conversation on first load.
  useEffect(() => {
    if (!activeId && conversations.length > 0) {
      const sorted = [...conversations].sort(
        (a, b) => +new Date(b.updated_at) - +new Date(a.updated_at),
      );

      setActiveId(sorted[0]!.id);
    }
  }, [conversations, activeId]);

  return (
    <div className="mx-auto flex h-[calc(100vh-3.5rem)] max-w-[1600px] overflow-hidden">
      <div className="w-full shrink-0 md:w-[22.5rem]">
        <ConversationList
          conversations={conversations}
          activeId={activeId ?? undefined}
          loading={isLoading}
          onSelect={(conversation) => {
            setActiveId(conversation.id);
            openChat(conversation.id);
          }}
          className="h-full"
        />
      </div>

      <div className="hidden flex-1 md:block">
        {active ? (
          <ChatWindow
            key={active.id}
            conversation={active}
            onClose={() => setActiveId(null)}
          />
        ) : (
          <ChatEmptyState />
        )}
      </div>

      {/* Selecting a thread on mobile drops you into the dock instead. */}
      <div className="grid flex-1 place-items-center md:hidden">
        {active ? (
          <div className="p-8 text-center text-md text-secondary">
            <p>Conversation opened in the chat dock.</p>

            <button
              type="button"
              onClick={() => navigate('/')}
              className="mt-3 rounded-control bg-facebook-action px-4 py-2 font-semibold text-white"
            >
              Back to feed
            </button>
          </div>
        ) : (
          <ChatEmptyState />
        )}
      </div>
    </div>
  );
}
