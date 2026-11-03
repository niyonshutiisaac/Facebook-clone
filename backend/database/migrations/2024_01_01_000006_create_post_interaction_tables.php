<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shares, saved items / collections and polls.
     */
    public function up(): void
    {
        Schema::create('shared_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('group_id')->nullable();
            $table->foreignUuid('page_id')->nullable();
            $table->string('audience', 20)->default('public');
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->index(['post_id', 'created_at'], 'shared_posts_post_idx');
            $table->index(['user_id', 'created_at'], 'shared_posts_user_idx');
        });

        Schema::create('saved_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('collection_id')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'post_id'], 'saved_posts_unique');
            $table->index(['user_id', 'created_at'], 'saved_posts_user_idx');
            $table->index('collection_id', 'saved_posts_collection_idx');
        });

        Schema::create('saved_collections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('cover_path')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'name'], 'saved_collections_name_unique');
        });

        Schema::create('polls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->unique()->constrained('posts')->cascadeOnDelete();
            $table->string('question', 500);
            $table->boolean('allows_multiple')->default(false);
            $table->timestamp('closes_at')->nullable();

            $table->timestamps();
        });

        Schema::create('poll_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('poll_id')->constrained('polls')->cascadeOnDelete();
            $table->string('label', 300);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('votes_count')->default(0);

            $table->timestamps();

            $table->index(['poll_id', 'position'], 'poll_options_order_idx');
        });

        Schema::create('poll_votes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('poll_option_id')->constrained('poll_options')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            // One vote per option per user (multiple votes allowed across
            // options only when the poll permits multi-select).
            $table->unique(['poll_option_id', 'user_id'], 'poll_votes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('polls');
        Schema::dropIfExists('saved_collections');
        Schema::dropIfExists('saved_posts');
        Schema::dropIfExists('shared_posts');
    }
};
