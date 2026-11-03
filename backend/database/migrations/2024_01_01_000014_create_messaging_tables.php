<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Messenger: conversations, participants, messages (with attachments,
     * read receipts, reactions and soft deletes) plus typing/presence.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 10)->default('private')->comment('private|group');

            // Sorted "uuid:uuid" for 1:1 threads. Unique, so two simultaneous
            // "message this person" requests can never fork the thread.
            $table->string('pair_key', 80)->nullable()->unique();

            $table->string('title', 160)->nullable()->comment('group chats only');
            $table->foreignUuid('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('avatar_path')->nullable();
            $table->string('theme_color', 9)->default('#0084FF');
            $table->text('emoji_icon')->nullable();
            $table->boolean('is_archived')->default(false);

            // Denormalised pointers to the latest message, so rendering the
            // conversation list is a single indexed read instead of N subqueries.
            $table->foreignUuid('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->index(['type', 'last_message_at'], 'conversations_recent_idx');
        });

        Schema::create('conversation_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('role', 10)->default('member')->comment('member|admin');
            $table->string('nickname', 100)->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('last_read_at')->nullable();
            $table->foreignUuid('last_read_message_id')->nullable();
            $table->boolean('is_muted')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_left')->default(false);
            $table->boolean('is_typing')->default(false);
            $table->timestamp('typing_at')->nullable();

            $table->timestamps();

            $table->unique(['conversation_id', 'user_id'], 'conversation_participants_unique');
            $table->index(['user_id', 'last_read_at'], 'conversation_participants_inbox_idx');
            $table->index(['user_id', 'is_archived', 'is_pinned'], 'conversation_participants_state_idx');
            $table->index(['conversation_id', 'is_typing'], 'conversation_participants_typing_idx');
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('reply_to_id')->nullable();
            $table->foreignUuid('forwarded_from_id')->nullable();

            $table->string('type', 15)->default('text')
                ->comment('text|image|video|audio|file|gif|sticker|system|location');
            $table->longText('body')->nullable();

            $table->unsignedTinyInteger('delivery_status')->default(0)
                ->comment('0=pending,1=sent,2=delivered,3=read');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('deleted_for_everyone_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Cursor pagination always reads "messages before this id".
            $table->index(['conversation_id', 'created_at'], 'messages_thread_idx');
            $table->index(['conversation_id', 'id'], 'messages_cursor_idx');
            $table->index(['sender_id', 'created_at'], 'messages_sender_idx');

            $table->foreign('reply_to_id')->references('id')->on('messages')->nullOnDelete();
            $table->foreign('forwarded_from_id')->references('id')->on('messages')->nullOnDelete();
        });

        Schema::create('message_media', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();

            $table->string('type', 15)->comment('image|video|audio|file|gif|sticker');
            $table->string('path');
            $table->string('disk', 20)->default('public');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable();

            $table->timestamps();

            $table->index('message_id', 'message_media_message_idx');
        });

        Schema::create('message_reads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->unique(['message_id', 'user_id'], 'message_reads_unique');
            $table->index(['user_id', 'read_at'], 'message_reads_user_idx');
        });

        Schema::create('message_reactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reaction', 20)->default('like');

            $table->timestamps();

            $table->unique(['message_id', 'user_id'], 'message_reactions_unique');
        });

        Schema::create('message_deletions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope', 10)->default('me')->comment('me|everyone');

            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'scope'], 'message_deletions_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_deletions');
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('message_reads');
        Schema::dropIfExists('message_media');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
