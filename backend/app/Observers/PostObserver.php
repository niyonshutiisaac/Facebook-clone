<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\PostCreated;
use App\Models\Post;
use App\Services\Feed\FeedCacheService;
use Illuminate\Support\Facades\DB;

final class PostObserver
{
    public function __construct(private readonly FeedCacheService $feedCache)
    {
    }

    public function creating(Post $post): void
    {
        if ($post->published_at === null) {
            $post->published_at = now();
        }
    }

    public function created(Post $post): void
    {
        $post->author?->profile?->increment('posts_count');

        // Tell every follower's feed channel that new content exists.
        $followerIds = DB::table('friendships')
            ->where('friend_id', $post->user_id)
            ->where('is_following', true)
            ->pluck('user_id')
            ->all();

        PostCreated::dispatch($post, $followerIds);

        $this->feedCache->forgetForAudienceOf($post->author ?? new class extends \App\Models\User {});
    }

    public function deleted(Post $post): void
    {
        $post->author?->profile?->decrement('posts_count');
    }
}
