<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feed delivery state: the durable copy of a pre-computed feed (Redis holds
     * the hot copy), what the user has already seen or hidden, feed
     * preferences, and the raw interaction log the ranking model learns from.
     */
    public function up(): void
    {
        Schema::create('newsfeed_cache', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();

            $table->decimal('score', 12, 6)->default(0);
            $table->string('cursor', 64)->nullable();
            $table->json('signals')->nullable();
            $table->timestamp('computed_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'post_id'], 'newsfeed_cache_unique');
            $table->index(['user_id', 'score'], 'newsfeed_cache_ranking_idx');
            $table->index(['user_id', 'cursor'], 'newsfeed_cache_cursor_idx');
        });

        Schema::create('newsfeed_seen', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->unsignedInteger('impressions')->default(1);
            $table->unsignedInteger('dwell_ms')->default(0);
            $table->timestamp('last_seen_at')->useCurrent();

            $table->timestamps();

            $table->unique(['user_id', 'post_id'], 'newsfeed_seen_unique');
            $table->index(['user_id', 'last_seen_at'], 'newsfeed_seen_recent_idx');
        });

        Schema::create('newsfeed_hidden', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('reason', 30)->default('hidden')
                ->comment('hidden|reported|snoozed_author|irrelevant');

            $table->timestamps();

            $table->unique(['user_id', 'post_id'], 'newsfeed_hidden_unique');
        });

        Schema::create('newsfeed_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type', 20)->comment('user|page|group');
            $table->uuid('subject_id');
            $table->string('preference', 20)->default('see_first')
                ->comment('see_first|unfollow|snooze|default');
            $table->timestamp('snoozed_until')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'subject_type', 'subject_id'], 'newsfeed_preferences_unique');
            $table->index(['subject_type', 'subject_id'], 'newsfeed_preferences_subject_idx');
        });

        Schema::create('feed_interaction_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('post_id')->nullable();
            $table->uuid('subject_id')->nullable()->comment('author/page/group the interaction was about');
            $table->string('subject_type', 20)->nullable();

            $table->string('action', 30)
                ->comment('view|dwell|like|comment|share|hide|click|profile_view|unfollow');
            $table->unsignedSmallInteger('weight')->default(1);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->json('meta')->nullable();

            $table->timestamp('occurred_at')->useCurrent();

            $table->index(['user_id', 'occurred_at'], 'feed_logs_user_time_idx');
            $table->index(['user_id', 'action'], 'feed_logs_user_action_idx');
            $table->index(['subject_id', 'action'], 'feed_logs_subject_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_interaction_logs');
        Schema::dropIfExists('newsfeed_preferences');
        Schema::dropIfExists('newsfeed_hidden');
        Schema::dropIfExists('newsfeed_seen');
        Schema::dropIfExists('newsfeed_cache');
    }
};
