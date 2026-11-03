<?php

declare(strict_types=1);

namespace Tests\Feature\Feed;

use App\Models\Post;
use App\Models\User;
use App\Services\Feed\ContentQualityService;
use App\Services\Feed\FeedCacheService;
use App\Services\Feed\FeedRankingService;
use App\Services\Feed\NewsFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NewsFeedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_feed_only_contains_posts_from_friends_you_follow(): void
    {
        $viewer = User::factory()->withProfile()->create();
        $friend = User::factory()->withProfile()->create();
        $stranger = User::factory()->withProfile()->create();

        $friendships = app(\App\Services\Social\FriendshipService::class);
        $friendships->acceptDirectly($viewer, $friend);

        $visible = Post::factory()->create(['user_id' => $friend->id, 'body' => 'visible to you']);
        Post::factory()->create(['user_id' => $stranger->id, 'body' => 'hidden from you']);

        $this->actingAs($viewer)->withoutEvents();

        $ids = app(NewsFeedService::class)->candidates($viewer)->pluck('id');

        $this->assertTrue($ids->contains($visible->id));
        $this->assertFalse($ids->contains(Post::query()->firstWhere('body', 'hidden from you')->id));
    }

    #[Test]
    public function the_feed_excludes_your_own_posts_and_hidden_posts(): void
    {
        $viewer = User::factory()->withProfile()->create();
        $friend = User::factory()->withProfile()->create();

        app(\App\Services\Social\FriendshipService::class)->acceptDirectly($viewer, $friend);

        $own = Post::factory()->create(['user_id' => $viewer->id]);
        $hidden = Post::factory()->create(['user_id' => $friend->id]);
        $shown = Post::factory()->create(['user_id' => $friend->id]);

        $feed = app(NewsFeedService::class);
        $feed->hidePost($viewer, $hidden);

        $ids = $feed->candidates($viewer)->pluck('id');

        $this->assertFalse($ids->contains($own->id));
        $this->assertFalse($ids->contains($hidden->id));
        $this->assertTrue($ids->contains($shown->id));
    }

    #[Test]
    public function ranking_prefers_a_close_friends_fresh_post_with_media(): void
    {
        $viewer = User::factory()->withProfile()->create();
        $close = User::factory()->withProfile()->create();
        $distant = User::factory()->withProfile()->create();

        $friendships = app(\App\Services\Social\FriendshipService::class);
        $friendships->acceptDirectly($viewer, $close);
        $friendships->acceptDirectly($viewer, $distant);

        \App\Models\Friendship::query()
            ->where('user_id', $viewer->id)
            ->where('friend_id', $close->id)
            ->update(['closeness_score' => 0.95]);
        \App\Models\Friendship::query()
            ->where('user_id', $viewer->id)
            ->where('friend_id', $distant->id)
            ->update(['closeness_score' => 0.02]);

        $strong = Post::factory()->withMedia(2)->create([
            'user_id' => $close->id,
            'created_at' => now()->subMinutes(5),
            'quality_score' => 0.95,
            'reactions_count' => 40,
            'comments_count' => 12,
        ]);

        $weak = Post::factory()->create([
            'user_id' => $distant->id,
            'created_at' => now()->subHours(70),
            'quality_score' => 0.1,
            'reactions_count' => 0,
        ]);

        /** @var FeedRankingService $ranking */
        $ranking = app(FeedRankingService::class);

        [$strongScore, $weakScore] = [$ranking->score($strong, $viewer)['total'], $ranking->score($weak, $viewer)['total']];

        $this->assertGreaterThan($weakScore, $strongScore);
    }

    #[Test]
    public function the_feed_is_cached_and_invalidated_when_the_author_posts(): void
    {
        $viewer = User::factory()->withProfile()->create();
        $friend = User::factory()->withProfile()->create();

        app(\App\Services\Social\FriendshipService::class)->acceptDirectly($viewer, $friend);

        Post::factory()->create(['user_id' => $friend->id]);

        /** @var FeedCacheService $cache */
        $cache = app(FeedCacheService::class);

        $cache->put($viewer, [['id' => 'cached', 'score' => 0.1, 'ranking_score' => 0.1, 'created_at' => now()->toIso8601String()]]);

        $this->assertNotNull($cache->get($viewer));

        Post::factory()->create(['user_id' => $friend->id]);

        $this->assertNull($cache->get($viewer), 'A new post by a friend must invalidate the cache.');
    }

    #[Test]
    public function the_feed_paginates_with_a_stable_cursor(): void
    {
        $viewer = User::factory()->withProfile()->create();
        $friend = User::factory()->withProfile()->create();

        app(\App\Services\Social\FriendshipService::class)->acceptDirectly($viewer, $friend);

        Post::factory()->count(25)->create(['user_id' => $friend->id]);

        /** @var NewsFeedService $feed */
        $feed = app(NewsFeedService::class);

        $first = $feed->forUser($viewer, null, ['limit' => 10]);
        $second = $feed->forUser($viewer, $first['cursor'], ['limit' => 10]);

        $this->assertCount(10, $first['items']);
        $this->assertCount(10, $second['items']);
        $this->assertNotEquals(
            $first['items']->first()->id,
            $second['items']->first()->id
        );
    }

    #[Test]
    public function a_post_that_is_all_caps_scores_worse_than_the_same_text_in_sentence_case(): void
    {
        $quality = app(ContentQualityService::class);

        $calm = Post::factory()->create(['body' => 'Went for a long walk along the canal and saw three herons.']);
        $shouting = Post::factory()->create(['body' => 'WENT FOR A LONG WALK ALONG THE CANAL AND SAW THREE HERONS!!!!']);

        $this->assertGreaterThan(
            $quality->score($shouting),
            $quality->score($calm)
        );
    }
}
