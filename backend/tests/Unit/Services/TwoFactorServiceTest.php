<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class TwoFactorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_totp_verifier_accepts_the_current_window(): void
    {
        /** @var TwoFactorService $service */
        $service = app(TwoFactorService::class);

        $user = User::factory()->create();

        $secret = $service->enableTotp($user)['secret'];

        $code = $service->currentCode($secret);

        $this->assertTrue($service->verifyCode($secret, $code));
    }

    public function test_a_code_from_the_previous_window_is_still_accepted(): void
    {
        /** @var TwoFactorService $service */
        $service = app(TwoFactorService::class);

        $user = User::factory()->create();
        $secret = $service->enableTotp($user)['secret'];

        // 30 seconds ago = the previous TOTP window.
        $old = $service->codeAt($secret, now()->subSeconds(30)->getTimestamp());

        $this->assertTrue($service->verifyCode($secret, $old));
    }

    public function test_a_random_code_is_rejected(): void
    {
        /** @var TwoFactorService $service */
        $service = app(TwoFactorService::class);

        $user = User::factory()->create();
        $secret = $service->enableTotp($user)['secret'];

        $this->assertFalse($service->verifyCode($secret, '000000'));
    }

    public function test_recovery_codes_are_stored_hashed_and_work_once(): void
    {
        /** @var TwoFactorService $service */
        $service = app(TwoFactorService::class);

        $user = User::factory()->create();

        $payload = $service->enableTotp($user);
        $plain = $payload['recovery_codes'][0];

        // The plaintext must never be persisted.
        $this->assertStringNotContainsString($plain, (string) $user->fresh()->two_factor_recovery_codes);
        $this->assertTrue(Hash::check($plain, json_decode((string) $user->fresh()->two_factor_recovery_codes, true)[0]));

        $this->assertTrue($service->disable($user, $plain));
        $this->assertNull($user->fresh()->two_factor_secret);
    }
}
