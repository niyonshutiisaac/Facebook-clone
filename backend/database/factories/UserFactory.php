<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Services\Auth\AuthService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\User>
 */
final class UserFactory extends Factory
{
    protected $model = \App\Models\User::class;

    /** @var ?string */
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'username' => Str::slug($first.$last.fake()->unique()->numberBetween(10, 9999), ''),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional(0.4)->e164PhoneNumber(),
            'password' => static::$password ??= Hash::make('Password1!'),
            'first_name' => $first,
            'last_name' => $last,
            'status' => AccountStatus::Active,
            'locale' => 'en',
            'timezone' => 'UTC',
            'email_verified_at' => now(),
            'is_online' => false,
            'last_seen_at' => now()->subMinutes(fake()->numberBetween(1, 600)),
            'allow_friend_requests' => true,
            'allow_messages_from_strangers' => false,
            'show_activity_status' => true,
            'timezone_auto' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => AccountStatus::Suspended]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => AccountStatus::Deactivated]);
    }

    public function online(): static
    {
        return $this->state(fn (): array => ['is_online' => true, 'last_seen_at' => now()]);
    }

    /**
     * Attach the rows every account is expected to have.
     */
    public function withProfile(array $attributes = []): static
    {
        return $this->afterCreating(function (\App\Models\User $user) use ($attributes): void {
            $user->profile()->create(array_merge([
                'headline' => fake()->optional(0.5)->sentence(4),
                'about' => fake()->optional(0.6)->paragraph(),
                'current_city' => fake()->optional(0.7)->city(),
                'hometown' => fake()->optional(0.6)->city(),
                'website' => fake()->optional(0.2)->url(),
            ], $attributes));

            $user->settings()->create(['theme' => 'system', 'language' => 'en']);

            foreach (AuthService::PRIVACY_DEFAULTS as $field => $audience) {
                $user->privacySettings()->create(['field' => $field, 'audience' => $audience]);
            }
        });
    }
}
