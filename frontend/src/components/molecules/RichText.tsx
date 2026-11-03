import { Fragment } from 'react';
import { Link } from 'react-router-dom';
import { tokenize } from '@/lib/format';

/**
 * Renders post bodies with mentions, hashtags and clickable URLs.
 *
 * The backend escapes HTML server-side, so this component only tokenises plain
 * text — it never injects markup.
 */
export function RichText({ text, className }: { text: string; className?: string }) {
  const tokens = tokenize(text);

  return (
    <p className={className}>
      {tokens.map((token, index) => {
        if (token.type === 'mention') {
          return (
            <Link
              key={index}
              to={`/profile/${token.value.slice(1)}`}
              className="font-medium text-accent hover:underline"
            >
              {token.value}
            </Link>
          );
        }

        if (token.type === 'hashtag') {
          return (
            <Link key={index} to={`/hashtag/${token.value.slice(1)}`} className="text-accent hover:underline">
              {token.value}
            </Link>
          );
        }

        if (token.type === 'url') {
          return (
            <a
              key={index}
              href={token.value}
              target="_blank"
              rel="noopener noreferrer nofollow"
              className="break-all text-accent hover:underline"
            >
              {token.value}
            </a>
          );
        }

        return <Fragment key={index}>{token.value}</Fragment>;
      })}
    </p>
  );
}
