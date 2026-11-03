import * as RTooltip from '@radix-ui/react-tooltip';
import type { ReactNode } from 'react';

/**
 * Facebook's tooltips are dark pills with a 400ms open delay. Radix gives us
 * collision-aware positioning for free; the visuals are ours.
 */
export function TooltipProvider({ children }: { children: ReactNode }) {
  return (
    <RTooltip.Provider delayDuration={400} skipDelayDuration={200}>
      {children}
    </RTooltip.Provider>
  );
}

export function Tooltip({
  content,
  children,
  side = 'bottom',
}: {
  content: ReactNode;
  children: ReactNode;
  side?: 'top' | 'right' | 'bottom' | 'left';
}) {
  return (
    <RTooltip.Root>
      <RTooltip.Trigger asChild>{children}</RTooltip.Trigger>
      <RTooltip.Portal>
        <RTooltip.Content
          side={side}
          sideOffset={6}
          className="z-tooltip select-none rounded-control bg-[#1C1E21] px-2.5 py-1.5 text-sm font-medium text-white shadow-dropdown animate-scale-in"
        >
          {content}
          <RTooltip.Arrow className="fill-[#1C1E21]" width={11} height={5} />
        </RTooltip.Content>
      </RTooltip.Portal>
    </RTooltip.Root>
  );
}
