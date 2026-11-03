<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Password resets, Sanctum tokens, brute-force tracking, 2FA challenges
     * and email verification codes.
     */
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Users use UUID primary keys, so the morph column is a char(36).
            $table->uuidMorphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('login_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->nullable()->index();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('successful')->default(false);
            $table->timestamp('attempted_at')->useCurrent();

            $table->index(['email', 'attempted_at'], 'login_attempts_email_time_idx');
            $table->index(['ip_address', 'attempted_at'], 'login_attempts_ip_time_idx');
        });

        Schema::create('two_factor_auth', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('method', 10)->comment('sms|totp');
            $table->string('destination', 100)->nullable()->comment('phone number for sms');
            $table->string('code', 10)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'method'], 'two_factor_user_method_idx');
        });

        Schema::create('email_verifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('code', 10);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'code'], 'email_verifications_user_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verifications');
        Schema::dropIfExists('two_factor_auth');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('password_reset_tokens');
    }
};
