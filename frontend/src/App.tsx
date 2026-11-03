import { Suspense, lazy, useEffect } from 'react';
import { BrowserRouter, Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { TooltipProvider } from '@/components/molecules/Tooltip';
import { AppLayout } from '@/components/templates/AppLayout';

import { useAuthStore } from '@/store/auth';
import { initTheme } from '@/store/ui';
import { PostSkeleton } from '@/components/atoms/Skeleton';

/**
 * Route-level code splitting.
 *
 * The messenger, profile and friends surfaces each pull in their own set of
 * components and never need to be in the user's first paint — splitting them
 * keeps the initial bundle small enough that the feed appears immediately.
 */
const FeedPage = lazy(async () => ({ default: (await import('@/pages/FeedPage')).FeedPage }));
const ProfilePage = lazy(async () => ({ default: (await import('@/pages/ProfilePage')).ProfilePage }));
const PostPage = lazy(async () => ({ default: (await import('@/pages/PostPage')).PostPage }));
const MessengerPage = lazy(async () => ({ default: (await import('@/pages/MessengerPage')).MessengerPage }));
const FriendsPage = lazy(async () => ({ default: (await import('@/pages/FriendsPage')).FriendsPage }));
const NotificationsPage = lazy(async () => ({ default: (await import('@/pages/NotificationsPage')).NotificationsPage }));
const SavedPage = lazy(async () => ({ default: (await import('@/pages/SavedPage')).SavedPage }));
const PlaceholderPage = lazy(async () => ({ default: (await import('@/pages/PlaceholderPage')).PlaceholderPage }));
const ReelsPage = lazy(async () => ({ default: (await import('@/pages/ReelsPage')).ReelsPage }));
const GroupsPage = lazy(async () => ({ default: (await import('@/pages/GroupsPage')).GroupsPage }));
const GroupPage = lazy(async () => ({ default: (await import('@/pages/GroupPage')).GroupPage }));
const SearchPage = lazy(async () => ({ default: (await import('@/pages/SearchPage')).SearchPage }));
const LoginPage = lazy(async () => ({ default: (await import('@/pages/auth/LoginPage')).LoginPage }));
const RegisterPage = lazy(async () => ({ default: (await import('@/pages/auth/RegisterPage')).RegisterPage }));

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // Facebook-quality caching: data is fresh for 30s, then revalidated in the
      // background rather than blocking the user with a spinner.
      staleTime: 30_000,
      gcTime: 5 * 60_000,
      retry: 1,
      refetchOnWindowFocus: false,
      networkMode: 'always',
    },
  },
});

initTheme();

export default function App() {
  const bootstrap = useAuthStore((state) => state.bootstrap);
  const status = useAuthStore((state) => state.status);

  useEffect(() => {
    void bootstrap();
  }, [bootstrap]);

  if (status === 'idle') {
    return (
      <div className="mx-auto max-w-feed space-y-4 px-2 py-6">
        <PostSkeleton />
      </div>
    );
  }

  return (
    <QueryClientProvider client={queryClient}>
      <TooltipProvider>
        <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
          <Suspense fallback={<RouteFallback />}>
            <Routes>
            {/* ------------------------------------------------------- guest */}
            <Route path="/login" element={<LoginPage />} />
            <Route path="/register" element={<RegisterPage />} />

            {/* ----------------------------------------------- authenticated */}
            <Route element={<RequireAuth />}>
              <Route element={<AppLayout />}>
                <Route index element={<FeedPage />} />
                <Route path="profile/:username" element={<ProfilePage />} />
                <Route path="me" element={<Navigate to="/profile/avachen" replace />} />
                <Route path="friends" element={<FriendsPage />} />
                <Route path="notifications" element={<NotificationsPage />} />
                <Route path="saved" element={<SavedPage />} />
                <Route path="reels" element={<ReelsPage />} />
                <Route path="post/:id" element={<PostPage />} />
                <Route path="groups" element={<GroupsPage />} />
                <Route path="groups/:id" element={<GroupPage />} />
                <Route path="watch" element={<PlaceholderPage title="Watch" icon="Tv" />} />
                <Route
                  path="marketplace"
                  element={<PlaceholderPage title="Marketplace" icon="Store" />}
                />
                <Route path="memories" element={<PlaceholderPage title="Memories" icon="History" />} />
                <Route path="events" element={<PlaceholderPage title="Events" icon="CalendarDays" />} />
                <Route path="search" element={<SearchPage />} />
                <Route path="settings" element={<PlaceholderPage title="Settings" icon="Settings" />} />
                <Route path="menu" element={<FriendsPage />} />
              </Route>

              {/* Messenger is full-bleed: no three-column shell. */}
              <Route path="messages" element={<MessengerPage />} />
              <Route path="messages/:id" element={<MessengerPage />} />
            </Route>

            <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
          </Suspense>
        </BrowserRouter>
      </TooltipProvider>
    </QueryClientProvider>
  );
}

/** Shown while a route's chunk downloads. */
function RouteFallback() {
  return (
    <div className="mx-auto max-w-feed space-y-4 px-2 py-6">
      <PostSkeleton />
    </div>
  );
}

function RequireAuth() {
  const status = useAuthStore((state) => state.status);
  const location = useLocation();

  if (status !== 'authenticated') {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />;
  }

  return <Outlet />;
}
