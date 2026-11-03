import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { User } from '@/types/api';
import { mockApi, USE_MOCKS } from '@/api/client';
import { api } from '@/api/client';

export type Theme = 'light' | 'dark' | 'system';

interface AuthState {
  user: User | null;
  token: string | null;
  status: 'idle' | 'loading' | 'authenticated' | 'unauthenticated';
  error: string | null;

  unreadNotifications: number;
  unreadMessages: number;
  pendingRequests: number;

  bootstrap: () => Promise<void>;
  login: (identity: string, password: string) => Promise<void>;
  register: (input: {
    first_name: string;
    last_name: string;
    email?: string;
    phone?: string;
    password: string;
  }) => Promise<void>;
  logout: () => Promise<void>;
  clear: () => void;
  setUser: (user: User) => void;
  setUnread: (patch: Partial<Pick<AuthState, 'unreadNotifications' | 'unreadMessages' | 'pendingRequests'>>) => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      user: null,
      token: null,
      status: 'idle',
      error: null,

      unreadNotifications: 0,
      unreadMessages: 0,
      pendingRequests: 0,

      async bootstrap() {
        // In mock mode there is no session to restore: the demo account is
        // always signed in, matching how the seeders leave the demo database.
        if (USE_MOCKS) {
          const me = await mockApi.me();

          set({
            user: me.user,
            token: 'mock-token',
            status: 'authenticated',
            unreadNotifications: me.unread_notifications,
            unreadMessages: me.unread_messages,
            pendingRequests: me.pending_friend_requests,
          });

          return;
        }

        if (!get().token) {
          set({ status: 'unauthenticated' });

          return;
        }

        try {
          const { data } = await api.get<{
            user: User;
            unread_notifications: number;
            unread_messages: number;
            pending_friend_requests: number;
          }>('/auth/me');

          set({
            user: data.user,
            status: 'authenticated',
            unreadNotifications: data.unread_notifications,
            unreadMessages: data.unread_messages,
            pendingRequests: data.pending_friend_requests,
          });
        } catch {
          set({ user: null, token: null, status: 'unauthenticated' });
        }
      },

      async login(identity, password) {
        set({ status: 'loading', error: null });

        try {
          if (USE_MOCKS) {
            const payload = await mockApi.login(identity, password);

            set({ user: payload.user, token: payload.token, status: 'authenticated' });

            return;
          }

          const { data } = await api.post<{ user: User; token: string }>('/auth/login', {
            identity,
            password,
          });

          set({ user: data.user, token: data.token, status: 'authenticated' });
        } catch (error) {
          set({
            status: 'unauthenticated',
            error: error instanceof Error ? error.message : 'Unable to sign in.',
          });

          throw error;
        }
      },

      async register(input) {
        set({ status: 'loading', error: null });

        try {
          if (USE_MOCKS) {
            const payload = await mockApi.register(input);

            set({ user: payload.user, token: payload.token, status: 'authenticated' });

            return;
          }

          const { data } = await api.post<{ user: User; token: string }>('/auth/register', input);

          set({ user: data.user, token: data.token, status: 'authenticated' });
        } catch (error) {
          set({
            status: 'unauthenticated',
            error: error instanceof Error ? error.message : 'Unable to create your account.',
          });

          throw error;
        }
      },

      async logout() {
        if (!USE_MOCKS) {
          await api.post('/auth/logout').catch(() => undefined);
        }

        set({
          user: null,
          token: null,
          status: 'unauthenticated',
          unreadNotifications: 0,
          unreadMessages: 0,
          pendingRequests: 0,
        });
      },

      clear() {
        set({ user: null, token: null, status: 'unauthenticated' });
      },

      setUser(user) {
        set({ user });
      },

      setUnread(patch) {
        set(patch);
      },
    }),
    {
      name: 'avimoney.auth',
      partialize: (state) => ({ user: state.user, token: state.token }),
    },
  ),
);
