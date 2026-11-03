<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The core identity table.
     *
     * Primary keys across the schema are UUIDs so that identifiers can be
     * generated client side (optimistic rendering), are never enumerable, and
     * can be merged across shards without collision.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // Identity
            $table->string('username', 50)->unique()->nullable();
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('email')->unique();
            $table->string('phone', 30)->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');

            // Demographics (Facebook requires a birthday and gender at signup)
            $table->date('birth_date');
            $table->string('gender', 20)->nullable()->comment('female|male|custom|prefer_not_to_say');
            $table->string('pronoun', 30)->nullable();

            // Presentation
            $table->string('avatar_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('bio', 101)->nullable()->comment('Facebook limits the intro line to 101 characters');

            // Account state
            $table->string('account_status', 20)->default('active')
                ->comment('active|suspended|deactivated|disabled');
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_profile_locked')->default(false);
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('locale', 10)->default('en');
            $table->string('timezone', 64)->default('UTC');

            // Two factor authentication
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Composite indexes for the query patterns that actually run.
            $table->index(['account_status', 'created_at'], 'users_status_created_idx');
            $table->index('last_seen_at', 'users_last_seen_idx');
            $table->index(['first_name', 'last_name'], 'users_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
