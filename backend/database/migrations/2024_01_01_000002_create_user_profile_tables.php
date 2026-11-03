<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Everything that augments the bare `users` row: profile detail, settings,
     * per-field privacy, session/device inventory and the block list.
     */
    public function up(): void
    {
        // ------------------------------------------------------------- profile
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->string('headline', 255)->nullable();
            $table->text('about')->nullable();
            $table->string('current_city')->nullable();
            $table->string('hometown')->nullable();
            $table->string('relationship_status', 30)->nullable();
            $table->foreignUuid('partner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('website')->nullable();
            $table->string('pronunciation')->nullable();
            $table->text('favorite_quotes')->nullable();
            $table->string('blood_type', 5)->nullable();
            $table->string('name_pronunciation_audio')->nullable();

            // Denormalised counters maintained by observers — keeps the profile
            // page to a single query instead of five COUNT(*).
            $table->unsignedInteger('friends_count')->default(0);
            $table->unsignedInteger('followers_count')->default(0);
            $table->unsignedInteger('following_count')->default(0);
            $table->unsignedInteger('posts_count')->default(0);
            $table->unsignedInteger('photos_count')->default(0);

            $table->json('featured_photos')->nullable();
            $table->json('work')->nullable();
            $table->json('education')->nullable();
            $table->json('places_lived')->nullable();
            $table->json('contact_info')->nullable();
            $table->json('basic_info')->nullable();
            $table->json('family_members')->nullable();
            $table->json('life_events')->nullable();

            $table->timestamps();
        });

        // ------------------------------------------------------------ settings
        Schema::create('user_settings', function (Blueprint $table): void
        {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->string('theme', 10)->default('system')->comment('light|dark|system');
            $table->string('language', 10)->default('en');
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('email_digest_enabled')->default(true);
            $table->boolean('push_enabled')->default(true);
            $table->boolean('show_active_status')->default(true);
            $table->boolean('tag_review_enabled')->default(true);
            $table->boolean('profile_visitors_enabled')->default(false);
            $table->json('notification_preferences')->nullable();
            $table->json('feed_preferences')->nullable();

            $table->timestamps();
        });

        // ---------------------------------------------- per-field privacy
        Schema::create('user_privacy_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('field', 64)->comment('e.g. email, phone, birth_date, friends_list');
            $table->string('audience', 20)->default('public')
                ->comment('public|friends|friends_except|specific|only_me');

            // Only populated for the friends_except / specific audiences.
            $table->json('excluded_user_ids')->nullable();
            $table->json('allowed_user_ids')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'field'], 'privacy_user_field_unique');
        });

        // ------------------------------------------------------------ sessions
        Schema::create('user_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('session_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_name')->nullable();
            $table->string('device_type', 20)->nullable()->comment('desktop|mobile|tablet');
            $table->string('platform')->nullable();
            $table->string('browser')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'last_activity_at'], 'user_sessions_activity_idx');
        });

        // ------------------------------------------------------------- devices
        Schema::create('user_devices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('fingerprint', 128);
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();
            $table->boolean('is_trusted')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->string('push_token')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'fingerprint'], 'user_devices_unique');
        });

        // -------------------------------------------------------------- blocks
        Schema::create('user_blocks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('blocked_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason')->nullable();

            $table->timestamps();

            // Blocking is mutual, so both directions are queried constantly.
            $table->unique(['user_id', 'blocked_user_id'], 'user_blocks_unique');
            $table->index('blocked_user_id', 'user_blocks_blocked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('user_devices');
        Schema::dropIfExists('user_sessions');
        Schema::dropIfExists('user_privacy_settings');
        Schema::dropIfExists('user_settings');
        Schema::dropIfExists('user_profiles');
    }
};
