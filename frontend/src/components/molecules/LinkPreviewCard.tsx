import { Icon } from '@/components/atoms/Icon';
import { Skeleton } from '@/components/atoms/Skeleton';
import type { LinkPreview } from '@/types/api';

/**
 * The clickable Open Graph card below link posts.
 *
 * Layout matches Facebook: a 16:9 image on top, then a grey metadata strip with
 * domain, title and description.
 */
export function LinkPreviewCard({ preview, url }: { preview: LinkPreview; url: string }) {
  return (
    <a
      href={url}
      target="_blank"
      rel="noopener noreferrer nofollow"
      className="mt-3 block overflow-hidden rounded-card border border-divider transition-colors hover:bg-hover/[0.03]"
    >
      {preview.image_url && (
        <div className="relative aspect-[1.91/1] w-full overflow-hidden bg-elevated">
          <img src={preview.image_url} alt="" loading="lazy" className="h-full w-full object-cover" />
        </div>
      )}

      <div className="bg-subtle p-3">
        <span className="text-xs uppercase tracking-wide text-secondary">
          {preview.domain || new URL(url).hostname}
        </span>

        <h3 className="mt-1 line-clamp-2 text-base font-bold leading-snug">
          {preview.title ?? preview.url}
        </h3>

        {preview.description && (
          <p className="mt-1 line-clamp-2 text-sm text-secondary">{preview.description}</p>
        )}

        <span className="mt-2 inline-flex items-center gap-1 text-xs text-secondary">
          {preview.site_name && <span className="font-medium">{preview.site_name}</span>}
          <Icon name="ArrowUpRight" size={12} />
        </span>
      </div>
    </a>
  );
}

export function LinkPreviewSkeleton() {
  return (
    <div className="mt-3 overflow-hidden rounded-card border border-divider">
      <Skeleton className="aspect-[1.91/1] w-full rounded-none" />
      <div className="space-y-2 bg-subtle p-3">
        <Skeleton className="h-3 w-24" />
        <Skeleton className="h-4 w-3/4" />
        <Skeleton className="h-3 w-full" />
      </div>
    </div>
  );
}
