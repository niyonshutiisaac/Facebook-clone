<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notifications are materialised into our own table (not Laravel's default
     * `notifications`) so they can be aggregated ("Ava and 3 others liked…"),
     * grouped by type, and filtered by unread state with a covering index.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete()
                ->comment('the recipient');
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->cascadeOnDelete()
                ->comment('who performed the action');

            $table->string('type', 60)
                ->comment('friend_request_received|post_reacted|post_commented|comment_replied|tagged|mentioned|post_shared|birthday|group_activity|page_activity|event_reminder|story_reacted|memory|live_started|new_message');

            $table->string('notifiable_type', 60)->nullable();
            $table->uuid('notifiable_id')->nullable();
            $table->json('data')->nullable();
            $table->text('body')->nullable()->comment('pre-rendered fallback text');

            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedSmallInteger('aggregated_count')->default(1);

            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'notifications_inbox_idx');
            $table->index(['user_id', 'read_at'], 'notifications_unread_idx');
            $table->index(['user_id', 'type'], 'notifications_type_idx');
            $table->index(['notifiable_type', 'notifiable_id'], 'notifications_subject_idx');
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 60)->comment('notification type key, or * for all');

            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->string('email_frequency', 12)->default('instant')
                ->comment('instant|daily|weekly|off');

            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'type'], 'notification_preferences_unique');
        });

        Schema::create('push_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('token');
            $table->string('platform', 10)->comment('ios|android|web');
            $table->string('device_name')->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'token'], 'push_tokens_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tokens');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
