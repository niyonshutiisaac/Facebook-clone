<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Safety tooling: blocks, mutes, content reports and the report taxonomy.
     */
    public function up(): void
    {
        Schema::create('blocked_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'blocked_id'], 'blocked_users_unique');
            $table->index('blocked_id', 'blocked_users_blocked_idx');
        });

        Schema::create('muted_users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('muted_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('muted_until')->nullable()->comment('null = mute forever');
            $table->text('reason')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'muted_id'], 'muted_users_unique');
            $table->index(['user_id', 'muted_until'], 'muted_users_expiry_idx');
        });

        Schema::create('content_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('reportable_type', 40)
                ->comment('post|comment|message|user|group|page|photo|story|reel');
            $table->uuid('reportable_id');
            $table->foreignUuid('reason_id')->nullable();

            $table->text('description')->nullable();
            $table->string('status', 12)->default('pending')
                ->comment('pending|reviewing|resolved|dismissed');
            $table->text('resolution')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['reportable_type', 'reportable_id'], 'content_reports_subject_idx');
            $table->index(['status', 'created_at'], 'content_reports_queue_idx');
            $table->index('reporter_id', 'content_reports_reporter_idx');
        });

        Schema::create('report_reasons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 60)->unique();
            $table->string('label', 160);
            $table->text('description')->nullable();
            $table->string('applies_to', 120)->nullable()->comment('comma separated reportable types');
            $table->boolean('requires_description')->default(false);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_reasons');
        Schema::dropIfExists('content_reports');
        Schema::dropIfExists('muted_users');
        Schema::dropIfExists('blocked_users');
    }
};
