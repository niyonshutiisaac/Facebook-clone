<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign keys that could not be declared inline because the referenced
     * table is created by a later migration (or references a table that
     * depends on it). Deferring them keeps the migration order linear while
     * still giving the database full referential integrity.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->foreign('group_id')->references('id')->on('groups')->cascadeOnDelete();
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->cascadeOnDelete();
        });

        Schema::table('saved_posts', function (Blueprint $table): void {
            $table->foreign('collection_id')->references('id')->on('saved_collections')->nullOnDelete();
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->foreign('last_message_id')->references('id')->on('messages')->nullOnDelete();
        });

        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->foreign('last_read_message_id')->references('id')->on('messages')->nullOnDelete();
        });

        Schema::table('group_posts', function (Blueprint $table): void {
            $table->index(['group_id', 'status'], 'group_posts_moderation_idx');
        });
    }

    public function down(): void
    {
        Schema::table('group_posts', function (Blueprint $table): void {
            $table->dropIndex('group_posts_moderation_idx');
        });

        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->dropForeign(['last_read_message_id']);
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropForeign(['last_message_id']);
        });

        Schema::table('saved_posts', function (Blueprint $table): void {
            $table->dropForeign(['collection_id']);
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropForeign(['group_id']);
            $table->dropForeign(['page_id']);
            $table->dropForeign(['event_id']);
        });
    }
};
