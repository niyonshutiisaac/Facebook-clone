<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The audit log users can review ("Your activity"), the raw affinity
     * interaction stream that powers ranking, and On This Day memories.
     */
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('action', 60)
                ->comment('post.created|post.edited|post.deleted|comment.created|reaction.added|friend.request_sent|login|password.changed|2fa.enabled|privacy.updated');
            $table->string('subject_type', 40)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'activity_log_user_time_idx');
            $table->index(['user_id', 'action'], 'activity_log_user_action_idx');
        });

        Schema::create('user_interactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('target_user_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedInteger('profile_views')->default(0);
            $table->unsignedInteger('messages_sent')->default(0);
            $table->unsignedInteger('reactions_given')->default(0);
            $table->unsignedInteger('comments_written')->default(0);
            $table->unsignedInteger('posts_viewed')->default(0);
            $table->unsignedBigInteger('dwell_ms')->default(0);
            $table->unsignedInteger('photo_tags')->default(0);
            $table->decimal('affinity_score', 6, 4)->default(0);
            $table->timestamp('last_interaction_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'target_user_id'], 'user_interactions_unique');
            $table->index(['user_id', 'affinity_score'], 'user_interactions_affinity_idx');
        });

        Schema::create('memories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();

            $table->date('memory_date')->comment('the anniversary date, e.g. 2019-09-09');
            $table->unsignedSmallInteger('years_ago')->default(1);
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('shared_at')->nullable();
            $table->timestamp('last_shown_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'post_id', 'memory_date'], 'memories_unique');
            $table->index(['user_id', 'memory_date'], 'memories_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memories');
        Schema::dropIfExists('user_interactions');
        Schema::dropIfExists('activity_log');
    }
};
