import { useRef, useState } from 'react';
import { Avatar } from '@/components/atoms/Avatar';
import { Icon } from '@/components/atoms/Icon';
import { Spinner } from '@/components/atoms/Spinner';
import { cn } from '@/lib/utils';
import type { User } from '@/types/api';

/**
 * Facebook's inline comment box: a bare input that expands into a row with the
 * action icons once focused (or once it has content).
 */
export function CommentComposer({
  author,
  placeholder = 'Write a comment…',
  autoFocus = false,
  compact = false,
  onSubmit,
  submitting = false,
  onCancel,
}: {
  author: User;
  placeholder?: string;
  autoFocus?: boolean;
  compact?: boolean;
  onSubmit: (body: string) => void;
  submitting?: boolean;
  onCancel?: () => void;
}) {
  const [value, setValue] = useState('');
  const [focused, setFocused] = useState(autoFocus);
  const inputRef = useRef<HTMLTextAreaElement>(null);

  const submit = () => {
    if (!value.trim() || submitting) return;

    onSubmit(value.trim());
    setValue('');
  };

  return (
    <div className="flex gap-2">
      {!compact && <Avatar src={author.avatar_url} name={author.name} size="sm" />}

      <div
        className={cn(
          'flex-1 rounded-bubble bg-subtle px-3 py-1.5 transition-shadow',
          focused && 'ring-1 ring-facebook/40',
        )}
      >
        <textarea
          ref={inputRef}
          rows={1}
          value={value}
          autoFocus={autoFocus}
          placeholder={placeholder}
          onChange={(event) => {
            setValue(event.target.value);
            event.target.style.height = 'auto';
            event.target.style.height = `${Math.min(event.target.scrollHeight, 160)}px`;
          }}
          onFocus={() => setFocused(true)}
          onBlur={() => setFocused(value.length > 0)}
          onKeyDown={(event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
              event.preventDefault();
              submit();
            }

            if (event.key === 'Escape') {
              onCancel?.();
            }
          }}
          className="w-full resize-none bg-transparent text-md outline-none placeholder:text-tertiary"
        />

        <div className="flex items-center justify-between gap-2">
          <div className="flex items-center gap-0.5 text-secondary">
            <IconButton name="Smile" label="Insert an emoji" />
            <IconButton name="Camera" label="Add a photo" />
            <IconButton name="Image" label="Add a GIF" />
            <IconButton name="Sticker" label="Add a sticker" />
          </div>

          <div className="flex items-center gap-1">
            {focused && onCancel && (
              <button
                type="button"
                onClick={onCancel}
                className="rounded-control px-2 py-1 text-sm text-secondary hover:bg-hover/[0.06]"
              >
                Cancel
              </button>
            )}

            <button
              type="button"
              onClick={submit}
              disabled={!value.trim() || submitting}
              aria-label="Send comment"
              className={cn(
                'grid h-8 w-8 place-items-center rounded-full transition-all',
                value.trim()
                  ? 'bg-facebook-action text-white hover:bg-facebook-action-hover'
                  : 'text-tertiary',
              )}
            >
              {submitting ? <Spinner size={16} /> : <Icon name="Send" size={16} />}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

function IconButton({ name, label }: { name: 'Smile' | 'Camera' | 'Image' | 'Sticker'; label: string }) {
  return (
    <button
      type="button"
      title={label}
      aria-label={label}
      className="grid h-7 w-7 place-items-center rounded-full transition-colors hover:bg-hover/[0.08]"
    >
      <Icon name={name} size={18} />
    </button>
  );
}
