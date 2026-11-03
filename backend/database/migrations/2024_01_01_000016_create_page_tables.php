<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pages (basic): the page, who administers it, followers and the mapping
     * of posts published as the page.
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();

            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->string('category', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('website')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();

            $table->string('avatar_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->json('about')->nullable();

            $table->unsignedInteger('followers_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('posts_count')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_published')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'followers_count'], 'pages_discovery_idx');
        });

        Schema::create('page_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('pages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('role', 20)->default('editor')
                ->comment('admin|editor|moderator|advertiser|analyst');
            $table->json('permissions')->nullable();
            $table->timestamp('granted_at')->useCurrent();

            $table->timestamps();

            $table->unique(['page_id', 'user_id'], 'page_roles_unique');
            $table->index('user_id', 'page_roles_user_idx');
        });

        Schema::create('page_followers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('pages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->boolean('receives_notifications')->default(true);
            $table->timestamp('followed_at')->useCurrent();

            $table->timestamps();

            $table->unique(['page_id', 'user_id'], 'page_followers_unique');
            $table->index(['user_id', 'followed_at'], 'page_followers_user_idx');
        });

        Schema::create('page_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('pages')->cascadeOnDelete();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->boolean('is_pinned')->default(false);

            $table->timestamps();

            $table->unique(['page_id', 'post_id'], 'page_posts_unique');
            $table->index(['page_id', 'created_at'], 'page_posts_feed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_posts');
        Schema::dropIfExists('page_followers');
        Schema::dropIfExists('page_roles');
        Schema::dropIfExists('pages');
    }
};
