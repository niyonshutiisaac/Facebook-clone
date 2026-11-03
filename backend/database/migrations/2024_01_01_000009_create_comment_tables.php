<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comments are self-referential: a reply is a comment whose parent_id is
     * set, which keeps threading to a single table with a single index.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('parent_id')->nullable();

            $table->longText('body');
            $table->unsignedSmallInteger('depth')->default(0);
            $table->string('path', 255)->nullable()->comment('materialised path, e.g. /a/b/c');

            $table->unsignedInteger('replies_count')->default(0);
            $table->unsignedInteger('reactions_count')->default(0);
            $table->boolean('is_edited')->default(false);
            $table->boolean('is_hidden')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // The feed reads "top level comments for this post, newest first".
            $table->index(['post_id', 'created_at'], 'comments_post_time_idx');
            $table->index(['post_id', 'parent_id'], 'comments_thread_idx');
            $table->index(['user_id', 'created_at'], 'comments_user_time_idx');

            $table->foreign('parent_id')->references('id')->on('comments')->cascadeOnDelete();
        });

        Schema::create('comment_media', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('comment_id')->constrained('comments')->cascadeOnDelete();
            $table->string('type', 10)->comment('image|gif|sticker|video');
            $table->string('path');
            $table->string('disk', 20)->default('public');
            $table->string('thumbnail_path')->nullable();
            $table->string('mime_type', 80)->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamps();

            $table->index('comment_id', 'comment_media_comment_idx');
        });

        Schema::create('comment_mentions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('comment_id')->constrained('comments')->cascadeOnDelete();
            $table->foreignUuid('mentioned_user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['comment_id', 'mentioned_user_id'], 'comment_mentions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_mentions');
        Schema::dropIfExists('comment_media');
        Schema::dropIfExists('comments');
    }
};
