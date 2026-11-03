<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_signs_in_with_an_email(): void
    {
        $user = User::factory()->create([
            'email' => 'ava@example.com',
            'password' => Hash::make('Password1!'),
        ]);

        $this->postJson('/api/auth/login', [
            'identity' => 'ava@example.com',
            'password' => 'Password1!',
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['user', 'token']);

        $this->assertDatabaseHas('user_sessions', ['user_id' => $user->id]);
    }

    #[Test]
    public function it_signs_in_with_a_username(): void
    {
        $user = User::factory()->create([
            'username' => 'avachen',
            'password' => Hash::make('Password1!'),
        ]);

        $this->postJson('/api/auth/login', [
            'identity' => 'avachen',
            'password' => 'Password1!',
        ])->assertOk()
            ->assertJsonPath('user.username', 'avachen');
    }

    #[Test]
    public function it_locks_the_account_after_too_many_failed_attempts(): void
    {
        config()->set('auth.max_login_attempts', 3);
        config()->set('auth.lockout_duration', 15);

        User::factory()->create([
            'email' => 'ava@example.com',
            'password' => Hash::make('Password1!'),
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/login', [
                'identity' => 'ava@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        // The fourth attempt (even with the correct password) is refused.
        $this->postJson('/api/auth/login', [
            'identity' => 'ava@example.com',
            'password' => 'Password1!',
        ])->assertStatus(429)
            ->assertJsonPath('error', 'account_locked');
    }

    #[Test]
    public function a_suspended_account_cannot_sign_in(): void
    {
        User::factory()->suspended()->create([
            'email' => 'banned@example.com',
            'password' => Hash::make('Password1!'),
        ]);

        $this->postJson('/api/auth/login', [
            'identity' => 'banned@example.com',
            'password' => 'Password1!',
        ])->assertStatus(422);
    }

    #[Test]
    public function it_is_rate_limited_per_identity(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password1!')]);

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', [
                'identity' => $user->email,
                'password' => 'nope',
            ]);
        }

        $this->assertDatabaseHas('login_attempts', ['successful' => false]);
    }
}
