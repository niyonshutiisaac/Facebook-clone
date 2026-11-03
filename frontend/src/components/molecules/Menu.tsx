import * as DropdownMenu from '@radix-ui/react-dropdown-menu';
import type { ReactNode } from 'react';
import { Icon, type IconName } from '@/components/atoms/Icon';
import { cn } from '@/lib/utils';

export interface MenuItem {
  label: string;
  icon?: IconName;
  onSelect?: () => void;
  danger?: boolean;
  divider?: boolean;
  /** Rendered instead of `label` — for rows with avatars or toggles. */
  content?: ReactNode;
}

/**
 * Facebook uses two menu systems: the account/settings menus (top-right) and
 * the "…" menus on posts. One component covers both.
 */
export function Menu({
  items,
  trigger,
  align = 'end',
  sideOffset = 8,
  className,
}: {
  items: MenuItem[];
  trigger: ReactNode;
  align?: 'start' | 'center' | 'end';
  sideOffset?: number;
  className?: string;
}) {
  return (
    <DropdownMenu.Root>
      <DropdownMenu.Trigger asChild>{trigger}</DropdownMenu.Trigger>

      <DropdownMenu.Portal>
        <DropdownMenu.Content
          align={align}
          sideOffset={sideOffset}
          className={cn(
            'z-dropdown min-w-[16rem] overflow-hidden rounded-card bg-surface p-2 shadow-dropdown animate-scale-in',
            className,
          )}
        >
          {items.map((item, index) =>
            item.divider ? (
              <DropdownMenu.Separator key={`sep-${index}`} className="my-2 h-px bg-divider" />
            ) : (
              <DropdownMenu.Item
                key={item.label ?? index}
                onSelect={item.onSelect}
                className={cn(
                  'flex cursor-pointer select-none items-center gap-3 rounded-control px-2 py-2 text-md outline-none',
                  'data-[highlighted]:bg-hover/[0.06]',
                  item.danger && 'text-facebook-red',
                )}
              >
                {item.content ?? (
                  <>
                    {item.icon && <Icon name={item.icon} size={20} className="text-secondary" />}
                    <span className="flex-1 font-medium">{item.label}</span>
                  </>
                )}
              </DropdownMenu.Item>
            ),
          )}
        </DropdownMenu.Content>
      </DropdownMenu.Portal>
    </DropdownMenu.Root>
  );
}
