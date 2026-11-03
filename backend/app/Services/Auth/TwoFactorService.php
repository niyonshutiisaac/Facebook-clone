<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * TOTP (authenticator app) and SMS code challenges.
 *
 * Recovery codes are single use and stored hashed, exactly like passwords, so a
 * database leak cannot be replayed against the login form.
 */
final class TwoFactorService
{
    private const int CODE_TTL_MINUTES = 10;
    private const int MAX_ATTEMPTS = 5;
    private const int RECOVERY_CODES = 8;

    /** TOTP step size in seconds (RFC 6238 default). */
    public const int WINDOW_SECONDS = 30;

    /** Number of digits in a TOTP code. */
    public const int CODE_LENGTH = 6;

    /**
     * @return array{secret: string, recovery_codes: list<string>, otpauth_url: string}
     */
    public function enableTotp(User $user): array
    {
        $secret = $this->generateSecret();
        $codes = $this->recoveryCodes();

        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            // Recovery codes are stored hashed, like passwords, so a database
            // leak cannot be replayed against the login form.
            'two_factor_recovery_codes' => encrypt(json_encode($this->hashRecoveryCodes($codes), JSON_THROW_ON_ERROR)),
        ])->save();

        $user->logActivity('2fa.enabled');

        return [
            'secret' => $secret,
            'recovery_codes' => $codes,
            'otpauth_url' => $this->otpauthUrl($user, $secret),
        ];
    }

    public function confirm(User $user, string $code): bool
    {
        if (! $this->verifyTotp($user, $code)) {
            return false;
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $user->logActivity('2fa.confirmed');

        return true;
    }

    public function disable(User $user, string $code): bool
    {
        if (! $this->verifyTotp($user, $code) && ! $this->useRecoveryCode($user, $code)) {
            return false;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $user->logActivity('2fa.disabled');

        return true;
    }

    /**
     * Issue an SMS challenge and return the code (in production this is where
     * the SMS gateway call happens; the code is never returned to the client).
     */
    public function sendSmsChallenge(User $user, string $phone): string
    {
        $code = (string) random_int(100000, 999999);

        DB::table('two_factor_auth')->updateOrInsert(
            ['user_id' => $user->id, 'method' => 'sms'],
            [
                'destination' => $phone,
                'code' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
                'attempts' => 0,
                'confirmed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return $code;
    }

    public function verifySmsChallenge(User $user, string $code): bool
    {
        $row = DB::table('two_factor_auth')
            ->where('user_id', $user->id)
            ->where('method', 'sms')
            ->whereNull('deleted_at')
            ->first();

        if ($row === null || $row->expires_at === null || now()->greaterThan($row->expires_at)) {
            return false;
        }

        if ((int) $row->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! password_verify($code, $row->code)) {
            DB::table('two_factor_auth')->where('id', $row->id)->increment('attempts');

            return false;
        }

        DB::table('two_factor_auth')
            ->where('id', $row->id)
            ->update(['confirmed_at' => now(), 'deleted_at' => now()]);

        return true;
    }

    /**
     * The six digits an authenticator app would show right now.
     */
    public function currentCode(string $secret): string
    {
        return $this->codeAt($secret, now()->getTimestamp());
    }

    /**
     * The six digits for an arbitrary Unix timestamp — used by tests to
     * generate codes for the previous and next TOTP windows.
     */
    public function codeAt(string $secret, int $timestamp): string
    {
        $counter = intdiv($timestamp, self::WINDOW_SECONDS);
        $key = $this->base32Decode($secret);

        // RFC 6238: HOTP(K, C) where C = floor(time / step).
        $counterBytes = pack('J', $counter);
        $hash = hash_hmac('sha1', $counterBytes, $key, true);

        // Dynamic truncation (RFC 4226 §5.4).
        $offset = ord($hash[19]) & 0x0F;

        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($binary % (10 ** self::CODE_LENGTH)), self::CODE_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Constant-time comparison of a submitted code against the current,
     * previous and next windows (±30 s of clock drift is tolerated — a phone
     * whose clock is a few seconds out must still let you in).
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $now = now()->getTimestamp();

        foreach ([-1, 0, 1] as $offset) {
            $candidate = $this->codeAt($secret, $now + ($offset * self::WINDOW_SECONDS));

            if (hash_equals($candidate, $code)) {
                return true;
            }
        }

        return false;
    }

    public function verifyTotp(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        try {
            $secret = decrypt($user->two_factor_secret);
        } catch (\Throwable) {
            return false;
        }

        return hash_equals($this->totp($secret), $code) || hash_equals($this->totp($secret, -1), $code);
    }

    public function useRecoveryCode(User $user, string $code): bool
    {
        if ($user->two_factor_recovery_codes === null) {
            return false;
        }

        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true, 512, JSON_THROW_ON_ERROR);

        foreach ($codes as $index => $hash) {
            if (password_verify(Str::upper($code), $hash)) {
                unset($codes[$index]);

                $user->forceFill([
                    'two_factor_recovery_codes' => encrypt(json_encode(array_values($codes), JSON_THROW_ON_ERROR)),
                ])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * RFC 6238 TOTP, 6 digits, 30 second window, SHA-1.
     *
     * @param  int  $offset  window offset, -1/0/+1 for clock drift tolerance
     */
    private function totp(string $secret, int $offset = 0): string
    {
        $timeSlice = (int) floor(now()->getTimestamp() / 30) + $offset;
        $key = $this->base32Decode($secret);
        $time = pack('N*', 0).pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);

        $offsetBits = ord($hash[19]) & 0xF;
        $value = (
            ((ord($hash[$offsetBits + 0]) & 0x7F) << 24)
            | ((ord($hash[$offsetBits + 1]) & 0xFF) << 16)
            | ((ord($hash[$offsetBits + 2]) & 0xFF) << 8)
            | (ord($hash[$offsetBits + 3]) & 0xFF)
        );

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    private function otpauthUrl(User $user, string $secret): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&digits=6&period=30',
            rawurlencode(config('app.name', 'Facebook Clone')),
            rawurlencode($user->email),
            $secret,
            rawurlencode(config('app.name', 'Facebook Clone'))
        );
    }

    private function generateSecret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < 32; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }

        return $secret;
    }

    /**
     * @return list<string>
     */
    private function recoveryCodes(): array
    {
        $codes = [];

        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $code = Str::upper(Str::random(10));
            $codes[] = $code;
        }

        return $codes;
    }

    /**
     * Hashed copy of the recovery codes for storage.
     *
     * @param  list<string>  $codes
     * @return list<string>
     */
    private function hashRecoveryCodes(array $codes): array
    {
        return array_map(static fn (string $code): string => password_hash($code, PASSWORD_BCRYPT), $codes);
    }

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bits = 0;
        $output = '';

        foreach (str_split($secret) as $char) {
            $index = strpos($alphabet, $char);

            if ($index === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $index;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $output .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $output;
    }
}
