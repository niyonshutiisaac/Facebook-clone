<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Short form vertical video: the reel itself, its audio track, views,
     * comments and shares.
     */
    public function up(): void
    {
        Schema::create('reels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('audio_id')->nullable();

            $table->string('path');
            $table->string('disk', 20)->default('public');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 80)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('duration')->nullable()->comment('max 90 seconds');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->string('caption', 2200)->nullable();
            $table->string('audience', 20)->default('public');
            $table->boolean('allows_remix')->default(true);
            $table->boolean('allows_comments')->default(true);
            $table->string('status', 20)->default('processing');
            $table->text('processing_error')->nullable();

            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('shares_count')->default(0);
            $table->decimal('completion_rate', 5, 4)->default(0);
            $table->decimal('ranking_score', 10, 6)->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['ranking_score', 'created_at'], 'reels_ranking_idx');
            $table->index(['user_id', 'created_at'], 'reels_user_time_idx');
            $table->index(['status', 'created_at'], 'reels_status_idx');

            $table->foreign('audio_id')->references('id')->on('reel_audio')->nullOnDelete();
        });

        Schema::create('reel_audio', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title', 255);
            $table->string('artist', 255)->nullable();
            $table->string('path')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->string('cover_path')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->boolean('is_trending')->default(false);

            $table->timestamps();

            $table->index(['is_trending', 'uses_count'], 'reel_audio_trending_idx');
        });

        Schema::create('reel_views', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('reel_id')->constrained('reels')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();

            $table->unsignedInteger('watched_seconds')->default(0);
            $table->decimal('completion_rate', 5, 4)->default(0);
            $table->unsignedSmallInteger('replays')->default(0);

            $table->timestamps();

            $table->index(['reel_id', 'created_at'], 'reel_views_reel_idx');
            $table->index(['user_id', 'reel_id'], 'reel_views_user_idx');
        });

        Schema::create('reel_comments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('reel_id')->constrained('reels')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('parent_id')->nullable();

            $table->longText('body');
            $table->unsignedInteger('likes_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['reel_id', 'created_at'], 'reel_comments_reel_idx');

            $table->foreign('parent_id')->references('id')->on('reel_comments')->cascadeOnDelete();
        });

        Schema::create('reel_shares', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('reel_id')->constrained('reels')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('destination', 20)->default('feed')->comment('feed|message|story|external');

            $table->timestamps();

            $table->index(['reel_id', 'created_at'], 'reel_shares_reel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reel_shares');
        Schema::dropIfExists('reel_comments');
        Schema::dropIfExists('reel_views');
        Schema::dropIfExists('reel_audio');
        Schema::dropIfExists('reels');
    }
};
