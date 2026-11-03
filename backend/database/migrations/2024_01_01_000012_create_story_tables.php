<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 24 hour stories, including viewers, replies, highlights and stickers.
     */
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('type', 10)->default('photo')->comment('photo|video|text');
            $table->longText('body')->nullable()->comment('text stories');
            $table->string('background', 40)->nullable();
            $table->string('audience', 20)->default('friends');
            $table->unsignedSmallInteger('duration')->default(5)->comment('seconds shown');

            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('replies_count')->default(0);
            $table->unsignedInteger('reactions_count')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_highlighted')->default(false);

            // Stories expire 24 hours after they are published.
            $table->timestamp('expires_at');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at'], 'stories_user_time_idx');
            $table->index(['expires_at', 'is_archived'], 'stories_expiry_idx');
        });

        Schema::create('story_media', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('story_id')->constrained('stories')->cascadeOnDelete();

            $table->string('type', 10)->comment('image|video');
            $table->string('path');
            $table->string('disk', 20)->default('public');
            $table->string('thumbnail_path')->nullable();
            $table->string('mime_type', 80)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();

            $table->timestamps();

            $table->index('story_id', 'story_media_story_idx');
        });

        Schema::create('story_viewers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('story_id')->constrained('stories')->cascadeOnDelete();
            $table->foreignUuid('viewer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('viewed_at')->useCurrent();

            $table->timestamps();

            $table->unique(['story_id', 'viewer_id'], 'story_viewers_unique');
            $table->index(['viewer_id', 'viewed_at'], 'story_viewers_viewer_idx');
        });

        Schema::create('story_replies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('story_id')->constrained('stories')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->text('body')->nullable();
            $table->string('reaction', 20)->nullable();
            $table->boolean('is_read')->default(false);

            $table->timestamps();

            $table->index(['story_id', 'created_at'], 'story_replies_story_idx');
        });

        Schema::create('story_highlights', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('cover_path')->nullable();
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'position'], 'story_highlights_user_idx');
        });

        Schema::create('story_highlight_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('highlight_id')->constrained('story_highlights')->cascadeOnDelete();
            $table->foreignUuid('story_id')->constrained('stories')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->unique(['highlight_id', 'story_id'], 'story_highlight_items_unique');
        });

        Schema::create('story_stickers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('story_id')->constrained('stories')->cascadeOnDelete();

            $table->string('type', 20)
                ->comment('text|emoji|location|mention|poll|quiz|music|gif|time|temperature');
            $table->json('payload')->nullable();
            $table->decimal('x_position', 5, 2)->default(50);
            $table->decimal('y_position', 5, 2)->default(50);
            $table->decimal('scale', 5, 2)->default(1);
            $table->decimal('rotation', 6, 2)->default(0);

            $table->timestamps();

            $table->index(['story_id', 'type'], 'story_stickers_story_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_stickers');
        Schema::dropIfExists('story_highlight_items');
        Schema::dropIfExists('story_highlights');
        Schema::dropIfExists('story_replies');
        Schema::dropIfExists('story_viewers');
        Schema::dropIfExists('story_media');
        Schema::dropIfExists('stories');
    }
};
