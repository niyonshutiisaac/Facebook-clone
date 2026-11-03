import type { ReactNode } from 'react';

/** Screen-reader-only text. Prefer this over aria-label on interactive nodes. */
export function VisuallyHidden({ children }: { children: ReactNode }) {
  return <span className="sr-only">{children}</span>;
}
