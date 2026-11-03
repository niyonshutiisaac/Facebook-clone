<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\UserSetting>
 */
final class UserSettingFactory extends Factory
{
    protected $model = \App\Models\UserSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'theme' => 'system',
            'language' => 'en',
            'sound_enabled' => true,
            'email_digest_enabled' => true,
            'push_enabled' => true,
            'show_active_status' => true,
            'tag_review_enabled' => true,
        ];
    }
}
