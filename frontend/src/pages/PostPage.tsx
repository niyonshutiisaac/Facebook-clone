import { Link, useParams } from 'react-router-dom';
import { PostCard } from '@/components/organisms/PostCard';
import { PostSkeleton } from '@/components/atoms/Skeleton';
import { Icon } from '@/components/atoms/Icon';
import { usePost } from '@/api/queries';

/**
 * Post permalink.
 *
 * Facebook opens a post in a centred column with the thread already expanded —
 * the point of the URL is the conversation, not the feed around it.
 */
export function PostPage() {
  const { id = '' } = useParams();
  const { data: post, isLoading, isError } = usePost(id);

  return (
    <div className="mx-auto w-full max-w-feed px-2 py-4 sm:px-0">
      <Link
        to="/"
        className="mb-3 inline-flex items-center gap-2 rounded-control px-2 py-1.5 text-base font-semibold text-secondary transition-colors hover:bg-hover/[0.05]"
      >
        <Icon name="ChevronLeft" size={18} />
        Back to feed
      </Link>

      {isLoading && <PostSkeleton />}

      {isError && (
        <div className="card p-10 text-center">
          <h1 className="text-xl font-bold">This post isn&apos;t available</h1>
          <p className="mt-2 text-md text-secondary">
            It may have been deleted, or the person who shared it changed who can see it.
          </p>
        </div>
      )}

      {post && <PostCard post={post} defaultExpanded />}
    </div>
  );
}
