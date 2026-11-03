<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('group_id')->nullable();

            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('privacy', 10)->default('public')->comment('public|private');

            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('timezone', 64)->default('UTC');

            $table->string('location_name')->nullable();
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('online_url')->nullable();

            $table->string('cover_path')->nullable();
            $table->unsignedInteger('going_count')->default(0);
            $table->unsignedInteger('interested_count')->default(0);
            $table->boolean('requires_approval')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['starts_at', 'privacy'], 'events_discovery_idx');
            $table->index(['user_id', 'starts_at'], 'events_host_idx');
        });

        Schema::create('event_attendees', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 12)->default('going')
                ->comment('going|interested|not_going');
            $table->timestamp('responded_at')->useCurrent();

            $table->timestamps();

            $table->unique(['event_id', 'user_id'], 'event_attendees_unique');
            $table->index(['event_id', 'status'], 'event_attendees_status_idx');
        });

        Schema::create('event_invites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUuid('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('invitee_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 12)->default('invited')
                ->comment('invited|going|interested|declined');
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->unique(['event_id', 'invitee_id'], 'event_invites_unique');
            $table->index(['invitee_id', 'status'], 'event_invites_inbox_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_invites');
        Schema::dropIfExists('event_attendees');
        Schema::dropIfExists('events');
    }
};
