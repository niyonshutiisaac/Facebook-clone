import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { Theme } from './auth';

interface UiState {
  theme: Theme;
  leftSidebarOpen: boolean;
  rightSidebarOpen: boolean;
  /** The floating messenger dock: which threads are open and which is visible. */
  openChats: string[];
  chatMinimised: Record<string, boolean>;
  composerOpen: boolean;
  createPostOpen: boolean;
  mobileNavOpen: boolean;

  setTheme: (theme: Theme) => void;
  toggleTheme: () => void;
  toggleLeftSidebar: () => void;
  toggleRightSidebar: () => void;
  toggleMobileNav: (open?: boolean) => void;

  openChat: (conversationId: string) => void;
  closeChat: (conversationId: string) => void;
  toggleChatMinimised: (conversationId: string) => void;

  setCreatePostOpen: (open: boolean) => void;
}

/** Applies the theme to <html>, resolving `system` to the OS preference. */
function applyTheme(theme: Theme): void {
  const root = document.documentElement;

  if (theme === 'system') {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    root.dataset.theme = prefersDark ? 'dark' : 'light';
  } else {
    root.dataset.theme = theme;
  }
}

export const useUiStore = create<UiState>()(
  persist(
    (set, get) => ({
      theme: 'system',
      leftSidebarOpen: true,
      rightSidebarOpen: true,
      openChats: [],
      chatMinimised: {},
      composerOpen: false,
      createPostOpen: false,
      mobileNavOpen: false,

      setTheme(theme) {
        applyTheme(theme);
        set({ theme });
      },

      toggleTheme() {
        const next: Theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        applyTheme(next);
        set({ theme: next });
      },

      toggleLeftSidebar() {
        set({ leftSidebarOpen: !get().leftSidebarOpen });
      },

      toggleRightSidebar() {
        set({ rightSidebarOpen: !get().rightSidebarOpen });
      },

      toggleMobileNav(open) {
        set({ mobileNavOpen: open ?? !get().mobileNavOpen });
      },

      openChat(conversationId) {
        const open = get().openChats;

        if (!open.includes(conversationId)) {
          // Facebook caps the dock at three visible threads.
          set({ openChats: [...open, conversationId].slice(-3) });
        }

        set({
          chatMinimised: { ...get().chatMinimised, [conversationId]: false },
        });
      },

      closeChat(conversationId) {
        set({ openChats: get().openChats.filter((id) => id !== conversationId) });
      },

      toggleChatMinimised(conversationId) {
        set({
          chatMinimised: {
            ...get().chatMinimised,
            [conversationId]: !get().chatMinimised[conversationId],
          },
        });
      },

      setCreatePostOpen(open) {
        set({ createPostOpen: open });
      },
    }),
    {
      name: 'avimoney.ui',
      partialize: (state) => ({
        theme: state.theme,
        leftSidebarOpen: state.leftSidebarOpen,
        rightSidebarOpen: state.rightSidebarOpen,
      }),
      onRehydrateStorage: () => (state) => {
        if (state) applyTheme(state.theme);
      },
    },
  ),
);

/** Called once at start-up so the persisted theme is applied before paint. */
export function initTheme(): void {
  applyTheme(useUiStore.getState().theme);

  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (useUiStore.getState().theme === 'system') applyTheme('system');
  });
}
