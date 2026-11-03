<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The social graph: accepted friendships, pending requests, curated lists
     * and cached "people you may know" suggestions.
     */
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('friend_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedSmallInteger('closeness_score')->default(0)
                ->comment('0-100 affinity score, feeds the news feed ranking model');
            $table->boolean('is_close_friend')->default(false);
            $table->boolean('is_acquaintance')->default(false);
            $table->boolean('is_restricted')->default(false);
            $table->boolean('is_following')->default(true)
                ->comment('false = unfollowed, posts stay out of the feed');
            $table->boolean('is_see_first')->default(false);
            $table->timestamp('muted_until')->nullable();
            $table->timestamp('became_friends_at')->useCurrent();

            $table->timestamps();

            // A friendship is stored once and queried from both directions.
            $table->unique(['user_id', 'friend_id'], 'friendships_pair_unique');
            $table->index(['friend_id', 'user_id'], 'friendships_reverse_idx');
            $table->index(['user_id', 'is_following', 'is_see_first'], 'friendships_feed_source_idx');
        });

        Schema::create('friend_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('recipient_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 20)->default('pending')
                ->comment('pending|accepted|declined|cancelled');
            $table->text('message')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // One live request per unordered pair.
            $table->unique(['sender_id', 'recipient_id'], 'friend_requests_pair_unique');
            $table->index(['recipient_id', 'status'], 'friend_requests_inbox_idx');
            $table->index(['sender_id', 'status'], 'friend_requests_sent_idx');
        });

        Schema::create('friend_lists', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 20)->default('custom')
                ->comment('custom|close_friends|acquaintances|restricted|work|school|family');
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->unique(['user_id', 'name'], 'friend_lists_user_name_unique');
            $table->index(['user_id', 'type'], 'friend_lists_user_type_idx');
        });

        Schema::create('friend_list_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('friend_list_id')->constrained('friend_lists')->cascadeOnDelete();
            $table->foreignUuid('member_id')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['friend_list_id', 'member_id'], 'friend_list_members_unique');
            $table->index('member_id', 'friend_list_members_member_idx');
        });

        Schema::create('friend_suggestions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('suggested_user_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedInteger('mutual_friends_count')->default(0);
            $table->decimal('score', 8, 4)->default(0);
            $table->json('signals')->nullable();
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('dismissed_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'suggested_user_id'], 'friend_suggestions_unique');
            $table->index(['user_id', 'score'], 'friend_suggestions_ranking_idx');
        });

        Schema::create('close_friends', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('close_friend_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('notify_on_post')->default(true);

            $table->timestamps();

            $table->unique(['user_id', 'close_friend_id'], 'close_friends_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('close_friends');
        Schema::dropIfExists('friend_suggestions');
        Schema::dropIfExists('friend_list_members');
        Schema::dropIfExists('friend_lists');
        Schema::dropIfExists('friend_requests');
        Schema::dropIfExists('friendships');
    }
};
