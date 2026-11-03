<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_account_with_profile_settings_and_privacy_rows(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'ava@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'first_name' => 'Ava',
            'last_name' => 'Chen',
            'birth_date' => '1994-06-14',
            'gender' => 'female',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'ava@example.com')
            ->assertJsonStructure(['user', 'token', 'token_type', 'expires_in']);

        $this->assertDatabaseHas('users', ['email' => 'ava@example.com']);

        $user = \App\Models\User::query()->firstWhere('email', 'ava@example.com');

        $this->assertNotNull($user->profile, 'Profile row must be created with the account.');
        $this->assertNotNull($user->settings, 'Settings row must be created with the account.');
        $this->assertDatabaseCount('user_privacy_settings', 6);
        $this->assertTrue(Hash::check('Password1!', $user->password));
    }

    public function test_it_derives_a_unique_username_from_the_name(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['email' => 'one@example.com']))->assertCreated();
        $this->postJson('/api/auth/register', $this->payload(['email' => 'two@example.com']))->assertCreated();

        $usernames = \App\Models\User::query()->pluck('username');

        $this->assertCount(2, $usernames);
        $this->assertCount(2, $usernames->unique());
    }

    public function test_it_rejects_people_under_the_minimum_age(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'email' => 'kid@example.com',
            'birth_date' => now()->subYears(11)->format('Y-m-d'),
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors('birth_date');
    }

    public function test_it_requires_either_an_email_or_a_phone(): void
    {
        $this->postJson('/api/auth/register', array_merge($this->payload(), [
            'email' => null,
            'phone' => null,
        ]))->assertUnprocessable();
    }

    public function test_it_rejects_a_compromised_password(): void
    {
        // `password` is in every breach corpus; uncompromised() must catch it.
        $this->postJson('/api/auth/register', $this->payload([
            'password' => 'password',
            'password_confirmation' => 'password',
        ]))->assertUnprocessable();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'ava@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'first_name' => 'Ava',
            'last_name' => 'Chen',
            'birth_date' => '1994-06-14',
            'gender' => 'female',
        ], $overrides);
    }
}
