import { cn } from '@/lib/utils';

/**
 * The three bouncing dots Facebook shows while the other person is typing.
 * Staggered delays make them read as a wave rather than a blink.
 */
export function TypingIndicator({ className }: { className?: string }) {
  return (
    <span
      className={cn('inline-flex items-center gap-1', className)}
      role="status"
      aria-label="Typing"
    >
      {[0, 1, 2].map((index) => (
        <span
          key={index}
          className="h-2 w-2 rounded-full bg-secondary animate-typing-bounce"
          style={{ animationDelay: `${index * 160}ms` }}
        />
      ))}
    </span>
  );
}
