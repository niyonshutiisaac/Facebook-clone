import { Outlet } from 'react-router-dom';

/** Centres content at Facebook's 680px single-column width. */
export function FeedLayout() {
  return (
    <div className="mx-auto w-full max-w-feed">
      <Outlet />
    </div>
  );
}
