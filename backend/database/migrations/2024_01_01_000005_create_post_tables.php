<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The post aggregate and every table that decorates it: media, link
     * previews, mentions, hashtags, feelings, backgrounds, audience overrides
     * and the immutable edit history.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // Where the post lives: a user timeline, a group or a page.
            $table->foreignUuid('group_id')->nullable();
            $table->foreignUuid('page_id')->nullable();
            $table->foreignUuid('event_id')->nullable();

            $table->string('type', 20)->default('text')
                ->comment('text|photo|video|album|link|shared|poll|gif|feeling|check_in|life_event|avatar_update|cover_update|reel');

            $table->longText('body')->nullable();
            $table->string('background', 40)->nullable()->comment('gradient/colour token for text posts');
            $table->string('audience', 20)->default('public')
                ->comment('public|friends|friends_except|specific|only_me');

            $table->string('feeling', 60)->nullable();
            $table->string('activity', 120)->nullable();
            $table->string('location_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('life_event_type', 40)->nullable();

            // Shares point at the original post instead of duplicating media.
            $table->foreignUuid('shared_post_id')->nullable();
            $table->string('share_comment')->nullable();

            // Denormalised counters — reactions/comments/shares are hot paths
            // and are maintained by observers + queued recalculation.
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('shares_count')->default(0);
            $table->unsignedInteger('reactions_count')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);

            // Ranking inputs refreshed by UpdateFeedRankingScores.
            $table->decimal('engagement_velocity', 10, 4)->default(0);
            $table->decimal('quality_score', 6, 4)->default(0);
            $table->decimal('ranking_score', 10, 6)->default(0);

            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_edited')->default(false);
            $table->boolean('comments_enabled')->default(true);
            $table->boolean('is_pending_approval')->default(false);

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Feed query: "recent posts from these authors, newest first".
            $table->index(['user_id', 'created_at'], 'posts_author_time_idx');
            $table->index(['created_at', 'audience'], 'posts_time_audience_idx');
            $table->index(['ranking_score', 'created_at'], 'posts_ranking_idx');
            $table->index(['group_id', 'created_at'], 'posts_group_time_idx');
            $table->index(['page_id', 'created_at'], 'posts_page_time_idx');
            $table->index(['type', 'created_at'], 'posts_type_time_idx');
            $table->index('published_at', 'posts_published_idx');

            $table->foreign('shared_post_id')->references('id')->on('posts')->cascadeOnDelete();
        });

        Schema::create('post_media', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();

            $table->string('type', 10)->comment('image|video|gif');
            $table->string('path');
            $table->string('disk', 20)->default('public');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 80)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable()->comment('seconds, video only');
            $table->text('alt_text')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->json('variants')->nullable()->comment('resized renditions: {sm,md,lg}');

            $table->timestamps();

            $table->index(['post_id', 'position'], 'post_media_order_idx');
        });

        Schema::create('post_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->unique()->constrained('posts')->cascadeOnDelete();
            $table->text('url');
            $table->string('domain', 190)->index();
            $table->boolean('is_preview_enabled')->default(true);

            $table->timestamps();
        });

        Schema::create('post_link_previews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_link_id')->constrained('post_links')->cascadeOnDelete();

            $table->string('title', 512)->nullable();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('site_name', 190)->nullable();
            $table->string('favicon_url')->nullable();
            $table->string('canonical_url')->nullable();
            $table->timestamp('fetched_at')->nullable();

            $table->timestamps();
        });

        Schema::create('post_mentions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('mentioned_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('offset')->default(0);
            $table->unsignedSmallInteger('length')->default(0);

            $table->timestamps();

            $table->unique(['post_id', 'mentioned_user_id'], 'post_mentions_unique');
            $table->index('mentioned_user_id', 'post_mentions_user_idx');
        });

        Schema::create('post_hashtags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('tag', 100);

            $table->timestamps();

            $table->unique(['post_id', 'tag'], 'post_hashtags_unique');
            $table->index(['tag', 'created_at'], 'post_hashtags_trending_idx');
        });

        Schema::create('post_feelings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('kind', 10)->comment('feeling|activity');
            $table->string('value', 120);
            $table->string('emoji', 16)->nullable();

            $table->timestamps();

            $table->index(['post_id', 'kind'], 'post_feelings_post_idx');
        });

        Schema::create('post_backgrounds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('token', 40)->unique();
            $table->string('label', 60);
            $table->string('css_value');
            $table->string('text_color', 20)->default('#FFFFFF');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();
        });

        Schema::create('post_audiences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_allowed')->default(true)
                ->comment('true = allow list (specific), false = deny list (friends_except)');

            $table->timestamps();

            $table->unique(['post_id', 'user_id'], 'post_audiences_unique');
        });

        Schema::create('post_edit_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('editor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->longText('previous_body')->nullable();
            $table->string('previous_audience', 20)->nullable();
            $table->text('reason')->nullable();

            $table->timestamps();

            $table->index(['post_id', 'created_at'], 'post_edit_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_edit_history');
        Schema::dropIfExists('post_audiences');
        Schema::dropIfExists('post_backgrounds');
        Schema::dropIfExists('post_feelings');
        Schema::dropIfExists('post_hashtags');
        Schema::dropIfExists('post_mentions');
        Schema::dropIfExists('post_link_previews');
        Schema::dropIfExists('post_links');
        Schema::dropIfExists('post_media');
        Schema::dropIfExists('posts');
    }
};
