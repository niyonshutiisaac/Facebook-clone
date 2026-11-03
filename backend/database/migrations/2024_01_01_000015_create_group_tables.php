<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Groups: the group itself, membership (with join requests), the mapping
     * of posts into groups, rules and the admin roster.
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();

            $table->string('name', 160);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->string('type', 20)->default('general')
                ->comment('general|social_learning|buy_and_sell');
            $table->string('privacy', 20)->default('public')
                ->comment('public|private_visible|private_hidden');

            $table->string('cover_path')->nullable();
            $table->string('avatar_path')->nullable();
            $table->json('topics')->nullable();
            $table->json('questions')->nullable()->comment('membership questions asked on join');
            $table->boolean('requires_post_approval')->default(false);

            $table->unsignedInteger('members_count')->default(0);
            $table->unsignedInteger('posts_count')->default(0);
            $table->unsignedInteger('pending_members_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['privacy', 'members_count'], 'groups_discovery_idx');
            $table->index(['owner_id', 'created_at'], 'groups_owner_idx');
        });

        Schema::create('group_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('role', 10)->default('member')->comment('member|moderator|admin');
            $table->string('status', 10)->default('active')
                ->comment('active|pending|banned');
            $table->text('answers')->nullable()->comment('answers to the join questions');
            $table->timestamp('joined_at')->useCurrent();
            $table->boolean('receives_notifications')->default(true);
            $table->string('badge', 20)->nullable()->comment('admin|moderator|new_member|founding_member');

            $table->timestamps();

            $table->unique(['group_id', 'user_id'], 'group_members_unique');
            $table->index(['user_id', 'status'], 'group_members_user_idx');
            $table->index(['group_id', 'role'], 'group_members_role_idx');
            $table->index(['group_id', 'status'], 'group_members_status_idx');
        });

        Schema::create('group_member_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('message')->nullable();
            $table->json('answers')->nullable();
            $table->string('status', 10)->default('pending')
                ->comment('pending|approved|rejected');
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->unique(['group_id', 'user_id'], 'group_member_requests_unique');
            $table->index(['group_id', 'status'], 'group_member_requests_status_idx');
        });

        Schema::create('group_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_announcement')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->string('status', 10)->default('approved')
                ->comment('pending|approved|rejected');

            $table->timestamps();

            $table->unique(['group_id', 'post_id'], 'group_posts_unique');
            $table->index(['group_id', 'is_pinned', 'created_at'], 'group_posts_feed_idx');
        });

        Schema::create('group_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('groups')->cascadeOnDelete();

            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            $table->index(['group_id', 'position'], 'group_rules_order_idx');
        });

        Schema::create('group_admins', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('role', 10)->default('admin')->comment('admin|moderator');
            $table->json('permissions')->nullable();
            $table->timestamp('appointed_at')->useCurrent();

            $table->timestamps();

            $table->unique(['group_id', 'user_id'], 'group_admins_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_admins');
        Schema::dropIfExists('group_rules');
        Schema::dropIfExists('group_posts');
        Schema::dropIfExists('group_member_requests');
        Schema::dropIfExists('group_members');
        Schema::dropIfExists('groups');
    }
};
