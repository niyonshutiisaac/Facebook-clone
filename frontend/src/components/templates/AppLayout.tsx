import { useEffect } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { Header } from '@/components/organisms/Header';
import { LeftSidebar } from '@/components/organisms/LeftSidebar';
import { RightSidebar } from '@/components/organisms/RightSidebar';
import { MessengerDock } from '@/components/organisms/MessengerDock';
import { MobileTabBar } from './MobileTabBar';
import { useUiStore } from '@/store/ui';
import { cn } from '@/lib/utils';

/**
 * The three-column shell every authenticated page lives inside.
 *
 * Each rail scrolls independently and the page never reflows when the feed
 * grows — the same behaviour Facebook gets from three separately scrollable
 * regions rather than one document scroll.
 */
export function AppLayout() {
  const { leftSidebarOpen, rightSidebarOpen, mobileNavOpen, toggleMobileNav, openChat } = useUiStore();
  const location = useLocation();

  // Collapse the mobile drawer on navigation.
  useEffect(() => {
    if (mobileNavOpen) toggleMobileNav(false);
  }, [location.pathname]); // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    const onResize = () => {
      if (window.innerWidth < 1024 && useUiStore.getState().rightSidebarOpen) {
        useUiStore.setState({ rightSidebarOpen: false });
      }
    };

    window.addEventListener('resize', onResize);
    onResize();

    return () => window.removeEventListener('resize', onResize);
  }, []);

  return (
    <div className="min-h-screen bg-canvas">
      <Header />

      <div className="mx-auto flex max-w-[1600px] gap-0 px-0 pt-header sm:px-2 lg:px-4">
        {/* ----------------------------------------------------------- left */}
        {leftSidebarOpen && (
          <div className="sticky top-header hidden h-[calc(100vh-3.5rem)] w-[22.5rem] shrink-0 md:block">
            <LeftSidebar className="w-[22.5rem]" />
          </div>
        )}

        {/* ---------------------------------------------------------- centre */}
        <main className="min-w-0 flex-1 px-0 py-4 sm:px-2 lg:px-4">
          <Outlet />
        </main>

        {/* ---------------------------------------------------------- right */}
        {rightSidebarOpen && (
          <div className="sticky top-header hidden h-[calc(100vh-3.5rem)] w-[21.875rem] shrink-0 xl:block">
            <RightSidebar onOpenChat={openChat} />
          </div>
        )}
      </div>

      <MessengerDock />
      <MobileTabBar />

      {/* Mobile nav drawer */}
      <div
        className={cn(
          'fixed inset-0 z-sidebar-overlay md:hidden',
          mobileNavOpen ? 'pointer-events-auto' : 'pointer-events-none',
        )}
        aria-hidden={!mobileNavOpen}
      >
        <div
          onClick={() => toggleMobileNav(false)}
          className={cn(
            'absolute inset-0 bg-black/50 transition-opacity',
            mobileNavOpen ? 'opacity-100' : 'opacity-0',
          )}
        />

        <div
          className={cn(
            'absolute inset-y-0 left-0 w-[80%] max-w-xs bg-canvas transition-transform duration-200 ease-fb',
            mobileNavOpen ? 'translate-x-0' : '-translate-x-full',
          )}
        >
          <LeftSidebar />
        </div>
      </div>
    </div>
  );
}
