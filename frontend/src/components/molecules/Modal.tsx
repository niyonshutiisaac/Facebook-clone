import { Dialog, DialogPanel, DialogTitle, DialogBackdrop, Transition, TransitionChild } from '@headlessui/react';
import { Fragment, type ReactNode } from 'react';
import { Icon } from '@/components/atoms/Icon';
import { cn } from '@/lib/utils';

export interface ModalProps {
  open: boolean;
  onClose: () => void;
  title?: ReactNode;
  children: ReactNode;
  footer?: ReactNode;
  size?: 'sm' | 'md' | 'lg' | 'full';
  /** Facebook's post composer is a centred dialog; everything else is too. */
  padding?: boolean;
}

const SIZES = {
  sm: 'max-w-sm',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
  full: 'max-w-5xl',
} as const;

export function Modal({ open, onClose, title, children, footer, size = 'md', padding = true }: ModalProps) {
  return (
    <Transition show={open} as={Fragment}>
      <Dialog onClose={onClose} className="relative z-modal">
        <TransitionChild
          as={Fragment}
          enter="ease-out duration-200"
          enterFrom="opacity-0"
          enterTo="opacity-100"
          leave="ease-in duration-150"
          leaveFrom="opacity-100"
          leaveTo="opacity-0"
        >
          <DialogBackdrop className="fixed inset-0 bg-black/60 backdrop-blur-[1px]" />
        </TransitionChild>

        <div className="fixed inset-0 flex items-start justify-center overflow-y-auto p-4 sm:items-center">
          <TransitionChild
            as={Fragment}
            enter="ease-fb duration-200"
            enterFrom="opacity-0 scale-95 translate-y-2"
            enterTo="opacity-100 scale-100 translate-y-0"
            leave="ease-in duration-150"
            leaveFrom="opacity-100 scale-100"
            leaveTo="opacity-0 scale-95"
          >
            <DialogPanel
              className={cn(
                'w-full overflow-hidden rounded-card bg-surface shadow-modal',
                SIZES[size],
              )}
            >
              {title && (
                <div className="relative border-b border-divider px-4 py-3 text-center">
                  <DialogTitle className="text-xl font-bold">{title}</DialogTitle>

                  <button
                    type="button"
                    onClick={onClose}
                    aria-label="Close"
                    className="absolute right-3 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-elevated text-secondary transition-colors hover:bg-hover/[0.1]"
                  >
                    <Icon name="X" size={20} />
                  </button>
                </div>
              )}

              <div className={cn('max-h-[70vh] overflow-y-auto scrollbar-thin', padding && 'p-4')}>
                {children}
              </div>

              {footer && <div className="border-t border-divider p-4">{footer}</div>}
            </DialogPanel>
          </TransitionChild>
        </div>
      </Dialog>
    </Transition>
  );
}
