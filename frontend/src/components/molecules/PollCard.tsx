import { motion } from 'framer-motion';
import { Icon } from '@/components/atoms/Icon';
import { compactNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Poll } from '@/types/api';

/**
 * Polls.
 *
 * Two states, exactly like Facebook: before voting the options are selectable
 * pills; after voting each option becomes a labelled bar with its percentage and
 * a check on the one you chose.
 */
export function PollCard({ poll, onVote }: { poll: Poll; onVote?: (optionId: string) => void }) {
  const voted = poll.options.some((option) => option.voted_by_viewer);

  return (
    <div className="mt-3 rounded-card border border-divider p-4">
      <h4 className="mb-3 text-base font-bold">{poll.question}</h4>

      {voted ? (
        <div className="space-y-2">
          {poll.options.map((option) => (
            <div key={option.id} className="relative">
              <motion.div
                initial={{ width: 0 }}
                animate={{ width: `${option.percentage}%` }}
                transition={{ duration: 0.6, ease: [0.08, 0.52, 0.37, 0.98] }}
                className={cn(
                  'absolute inset-y-0 left-0 rounded-control',
                  option.voted_by_viewer ? 'bg-facebook/20' : 'bg-elevated',
                )}
              />

              <div className="relative flex items-center justify-between px-3 py-2.5">
                <span className="flex items-center gap-2 text-base font-medium">
                  {option.voted_by_viewer && (
                    <Icon name="CircleCheckBig" size={16} className="text-facebook" />
                  )}
                  {option.label}
                </span>

                <span className={cn('text-base tabular-nums', option.voted_by_viewer && 'font-semibold')}>
                  {option.percentage}%
                </span>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="space-y-2">
          {poll.options.map((option) => (
            <button
              key={option.id}
              type="button"
              onClick={() => onVote?.(option.id)}
              className="w-full rounded-control border border-divider px-3 py-2.5 text-left text-base font-medium transition-colors hover:bg-hover/[0.05] active:scale-[0.99]"
            >
              {option.label}
            </button>
          ))}
        </div>
      )}

      <div className="mt-3 flex items-center gap-2 text-sm text-secondary">
        <Icon name="ChartColumn" size={14} />
        <span>{compactNumber(poll.total_votes)} votes</span>

        {poll.closes_at && (
          <>
            <span aria-hidden="true">·</span>
            <span>{new Date(poll.closes_at).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} remaining</span>
          </>
        )}
      </div>
    </div>
  );
}
