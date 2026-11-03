<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photo library: albums, individual photos and face/people tags.
     */
    public function up(): void
    {
        Schema::create('photo_albums', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('cover_photo_id')->nullable();
            $table->string('audience', 20)->default('public');
            $table->unsignedInteger('photos_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at'], 'photo_albums_user_idx');
        });

        Schema::create('photos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('album_id')->nullable();
            $table->foreignUuid('post_id')->nullable();

            $table->string('path');
            $table->string('disk', 20)->default('public');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 80)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->text('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->string('audience', 20)->default('public');
            $table->unsignedInteger('reactions_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->boolean('is_profile_picture')->default(false);
            $table->boolean('is_cover_photo')->default(false);
            $table->json('variants')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at'], 'photos_user_time_idx');
            $table->index(['album_id', 'created_at'], 'photos_album_time_idx');

            $table->foreign('album_id')->references('id')->on('photo_albums')->cascadeOnDelete();
            $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
        });

        Schema::create('photo_tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('photo_id')->constrained('photos')->cascadeOnDelete();
            $table->foreignUuid('tagged_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('tagger_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('x_position', 5, 2)->nullable();
            $table->decimal('y_position', 5, 2)->nullable();
            $table->string('status', 20)->default('pending')
                ->comment('pending|approved|rejected');

            $table->timestamps();

            $table->unique(['photo_id', 'tagged_user_id'], 'photo_tags_unique');
            $table->index(['tagged_user_id', 'status'], 'photo_tags_review_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_tags');
        Schema::dropIfExists('photos');
        Schema::dropIfExists('photo_albums');
    }
};
