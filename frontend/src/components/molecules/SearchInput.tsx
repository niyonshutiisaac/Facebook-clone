import { useEffect, useRef, useState, type KeyboardEvent } from 'react';
import { Icon } from '@/components/atoms/Icon';
import { Spinner } from '@/components/atoms/Spinner';
import { cn } from '@/lib/utils';

/**
 * Facebook's search field: a grey pill with a magnifying glass that expands
 * into a results dropdown as soon as there is a query.
 */
export function SearchInput({
  value,
  onChange,
  placeholder = 'Search',
  autoFocus = false,
  className,
  expanded = false,
  onFocusChange,
  onKeyDown,
}: {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  autoFocus?: boolean;
  className?: string;
  expanded?: boolean;
  onFocusChange?: (focused: boolean) => void;
  onKeyDown?: (event: KeyboardEvent<HTMLInputElement>) => void;
}) {
  const [focused, setFocused] = useState(false);
  const [loading, setLoading] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  // The spinner is a pure visual cue for the debounce window.
  useEffect(() => {
    if (!value) {
      setLoading(false);

      return;
    }

    setLoading(true);
    const timer = setTimeout(() => setLoading(false), 300);

    return () => clearTimeout(timer);
  }, [value]);

  return (
    <div
      className={cn(
        'flex items-center gap-2 rounded-pill bg-subtle px-3 transition-all',
        expanded || focused ? 'h-11 w-full' : 'h-10',
        className,
      )}
    >
      <Icon name="Search" size={16} className="text-secondary" />

      <input
        ref={inputRef}
        type="search"
        value={value}
        autoFocus={autoFocus}
        placeholder={placeholder}
        onChange={(event) => onChange(event.target.value)}
        onKeyDown={onKeyDown}
        onFocus={() => {
          setFocused(true);
          onFocusChange?.(true);
        }}
        onBlur={() => {
          setFocused(false);

          // Delay so a click on a result is not lost to the blur.
          setTimeout(() => onFocusChange?.(false), 150);
        }}
        className="h-full flex-1 bg-transparent text-md outline-none placeholder:text-tertiary [&::-webkit-search-cancel-button]:appearance-none"
        aria-label={placeholder}
      />

      {loading && <Spinner size={14} className="text-tertiary" />}
    </div>
  );
}
