import { PostCard } from '@/components/organisms/PostCard';
import { Skeleton } from '@/components/atoms/Skeleton';
import { useSavedPosts } from '@/api/queries';

export function SavedPage() {
  const { data, isLoading } = useSavedPosts();

  return (
    <div className="mx-auto w-full max-w-feed px-2 sm:px-0">
      <header className="mb-4">
        <h1 className="text-2xl font-bold">Saved</h1>
        <p className="text-md text-secondary">Only you can see what you&apos;ve saved.</p>
      </header>

      {isLoading ? (
        <Skeleton className="h-64 w-full rounded-card" />
      ) : (data?.data.length ?? 0) === 0 ? (
        <div className="card p-12 text-center text-md text-secondary">
          You haven&apos;t saved anything yet.
        </div>
      ) : (
        <div className="space-y-4">
          {data?.data.map((post) => <PostCard key={post.id} post={post} />)}
        </div>
      )}
    </div>
  );
}
