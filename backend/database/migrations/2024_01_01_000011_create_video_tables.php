<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Long form video: source asset, generated thumbnails, the transcoding
     * ladder produced by FFmpeg and per-view analytics.
     */
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('post_id')->nullable();

            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('path');
            $table->string('disk', 20)->default('public');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 80)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('duration')->nullable()->comment('seconds');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('status', 20)->default('processing')
                ->comment('processing|ready|failed');
            $table->string('processing_job_id')->nullable();
            $table->text('processing_error')->nullable();
            $table->string('audience', 20)->default('public');
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedInteger('reactions_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->text('subtitles_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at'], 'videos_user_time_idx');
            $table->index(['status', 'created_at'], 'videos_status_idx');

            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
        });

        Schema::create('video_thumbnails', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('video_id')->constrained('videos')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('time_offset')->default(0);
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index(['video_id', 'is_default'], 'video_thumbnails_video_idx');
        });

        Schema::create('video_qualities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('video_id')->constrained('videos')->cascadeOnDelete();

            $table->string('label', 10)->comment('360p|480p|720p|1080p');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('bitrate_kbps');
            $table->string('path');
            $table->string('playlist_path')->nullable()->comment('HLS manifest');
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamps();

            $table->unique(['video_id', 'label'], 'video_qualities_unique');
        });

        Schema::create('video_views', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('video_id')->constrained('videos')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();

            $table->unsignedInteger('watched_seconds')->default(0);
            $table->decimal('completion_rate', 5, 4)->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['video_id', 'created_at'], 'video_views_video_idx');
            $table->index(['user_id', 'video_id'], 'video_views_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_views');
        Schema::dropIfExists('video_qualities');
        Schema::dropIfExists('video_thumbnails');
        Schema::dropIfExists('videos');
    }
};
