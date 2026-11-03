<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\AccountStatus;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;

/**
 * Registration, login throttling, session inventory and device trust.
 *
 * Brute force defence: after `AUTH_MAX_LOGIN_ATTEMPTS` failures for an
 * identity the account is locked for `AUTH_LOCKOUT_MINUTES`. Attempts are
 * counted per identity *and* per IP so a distributed attack on one account is
 * still throttled, and a single NAT'd office cannot be locked out by one
 * person's typos.
 */
final class AuthService
{
    /**
     * Conservative defaults for a brand new account: contact details are
     * private, social graph details are friends-only, and location is public
     * (matching Facebook's own defaults).
     *
     * @var array<string, string>
     */
    public const array PRIVACY_DEFAULTS = [
        'email' => 'only_me',
        'phone' => 'only_me',
        'birth_date' => 'friends',
        'friends_list' => 'friends',
        'current_city' => 'public',
        'hometown' => 'public',
    ];

    public function register(array $data, Request $request): User
    {
        return DB::transaction(function () use ($data, $request): User {
            /** @var User $user */
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'birth_date' => $data['birth_date'],
                'gender' => $data['gender'] ?? null,
                'locale' => $data['locale'] ?? config('app.locale'),
                'timezone' => $data['timezone'] ?? config('app.timezone'),
                'username' => $data['username'] ?? $this->uniqueUsername($data['first_name'], $data['last_name']),
            ]);

            $user->profile()->create([
                'friends_count' => 0,
                'posts_count' => 0,
            ]);

            $user->settings()->create([
                'theme' => 'system',
                'language' => $user->locale,
            ]);

            $this->defaultPrivacy($user);

            $user->logActivity('auth.registered');

            return $user->fresh(['profile', 'settings']);
        });
    }

    /**
     * Record an attempt and report whether the identity is currently locked.
     */
    public function recordAttempt(?User $user, string $identity, Request $request, bool $successful): void
    {
        $agent = new Agent($request->userAgent(), $request->headers->all());

        LoginAttempt::create([
            'user_id' => $user?->id,
            'email' => $identity,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512),
            'successful' => $successful,
            'attempted_at' => now(),
        ]);

        if (! $successful) {
            Log::channel('security')->warning('Failed login attempt', [
                'identity' => $identity,
                'ip' => $request->ip(),
                'device' => $agent->device(),
                'platform' => $agent->platform(),
            ]);
        }
    }

    public function isLockedOut(string $identity, string $ipAddress): bool
    {
        $window = now()->subMinutes((int) config('auth.lockout_duration', 15));
        $max = (int) config('auth.max_login_attempts', 5);

        $byIdentity = LoginAttempt::query()
            ->where('email', $identity)
            ->where('successful', false)
            ->where('attempted_at', '>=', $window)
            ->count();

        if ($byIdentity >= $max) {
            return true;
        }

        // Looser per-IP ceiling: three times the per-account limit, because
        // many legitimate users can share an egress address.
        $byIp = LoginAttempt::query()
            ->where('ip_address', $ipAddress)
            ->where('successful', false)
            ->where('attempted_at', '>=', $window)
            ->count();

        return $byIp >= $max * 3;
    }

    public function secondsUntilUnlock(string $identity): int
    {
        $oldest = LoginAttempt::query()
            ->where('email', $identity)
            ->where('successful', false)
            ->where('attempted_at', '>=', now()->subMinutes((int) config('auth.lockout_duration', 15)))
            ->orderBy('attempted_at')
            ->value('attempted_at');

        if ($oldest === null) {
            return 0;
        }

        return max(0, (int) $oldest->addMinutes((int) config('auth.lockout_duration', 15))->diffInSeconds(now()));
    }

    public function clearAttempts(string $identity): void
    {
        LoginAttempt::query()->where('email', $identity)->delete();
    }

    /**
     * Track a session so the user can review and revoke devices.
     */
    public function trackSession(User $user, Request $request): UserSession
    {
        $agent = new Agent($request->userAgent(), $request->headers->all());

        return UserSession::create([
            'user_id' => $user->id,
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512),
            'device_name' => $agent->device() ?: 'Unknown device',
            'device_type' => $agent->isMobile() ? 'mobile' : ($agent->isTablet() ? 'tablet' : 'desktop'),
            'platform' => $agent->platform() ?: null,
            'browser' => $agent->browser() ?: null,
            'is_current' => true,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * @return list<string>
     */
    public function verifyCredentials(string $identity, string $password): array
    {
        $user = User::query()
            ->where('email', $identity)
            ->orWhere('phone', $identity)
            ->orWhere('username', $identity)
            ->first();

        if ($user === null) {
            return [null, 'These credentials do not match our records.'];
        }

        if (! Hash::check($password, $user->password)) {
            return [null, 'The password you entered is incorrect.'];
        }

        $status = AccountStatus::tryFrom((string) $user->account_status) ?? AccountStatus::Active;

        if (! $status->canAuthenticate()) {
            return [null, match ($status) {
                AccountStatus::Suspended => 'Your account is suspended. Contact support for help.',
                AccountStatus::Deactivated => 'This account was deactivated. You can reactivate it by logging in.',
                AccountStatus::Disabled => 'This account has been disabled.',
                default => 'This account cannot sign in right now.',
            }];
        }

        return [$user, null];
    }

    private function uniqueUsername(string $first, string $last): string
    {
        $base = Str::lower(Str::ascii($first.'.'.$last));
        $base = preg_replace('/[^a-z0-9.]/', '', $base) ?: 'user';
        $candidate = $base;
        $suffix = 1;

        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = $base.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function defaultPrivacy(User $user): void
    {
        foreach (self::PRIVACY_DEFAULTS as $field => $audience) {
            $user->privacySettings()->create([
                'field' => $field,
                'audience' => $audience,
            ]);
        }
    }
}
