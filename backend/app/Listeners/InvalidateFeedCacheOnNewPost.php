<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PostCreated;
use App\Services\Feed\FeedCacheService;

final class InvalidateFeedCacheOnNewPost
{
    public function __construct(private readonly FeedCacheService $feedCache)
    {
    }

    public function handle(PostCreated $event): void
    {
        $author = $event->post->author;

        if ($author === null) {
            return;
        }

        $this->feedCache->forgetForAudienceOf($author);
    }
}
