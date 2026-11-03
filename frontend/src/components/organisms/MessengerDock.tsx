import { AnimatePresence, motion } from 'framer-motion';
import { ChatWindow } from './ChatWindow';
import { useConversations } from '@/api/queries';
import { useUiStore } from '@/store/ui';

/**
 * The floating dock of chat windows pinned to the bottom-right.
 *
 * Facebook caps it at three visible windows; opening a fourth closes the oldest.
 * Windows animate in from the bottom and reorder without reflowing the page.
 */
export function MessengerDock() {
  const { openChats, chatMinimised, closeChat, toggleChatMinimised } = useUiStore();
  const { data: conversations = [] } = useConversations();

  const open = openChats
    .filter((id) => id !== '__list__')
    .map((id) => conversations.find((c) => c.id === id))
    .filter((c): c is NonNullable<typeof c> => Boolean(c));

  return (
    <div className="pointer-events-none fixed bottom-0 right-2 z-messenger flex items-end gap-2 sm:right-4">
      <AnimatePresence>
        {open.map((conversation) => (
          <motion.div
            key={conversation.id}
            layout
            initial={{ y: 40, opacity: 0, scale: 0.98 }}
            animate={{ y: 0, opacity: 1, scale: 1 }}
            exit={{ y: 30, opacity: 0, scale: 0.98 }}
            transition={{ type: 'spring', stiffness: 460, damping: 38 }}
            className="pointer-events-auto h-[27rem] w-[22rem] max-w-[calc(100vw-1rem)]"
          >
            <ChatWindow
              conversation={conversation}
              docked
              minimised={chatMinimised[conversation.id] ?? false}
              onClose={() => closeChat(conversation.id)}
              onMinimise={() => toggleChatMinimised(conversation.id)}
            />
          </motion.div>
        ))}
      </AnimatePresence>
    </div>
  );
}
