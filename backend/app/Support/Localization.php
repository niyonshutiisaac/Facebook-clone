<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Locale helpers shared by the API and the notification mailers.
 */
final class Localization
{
    /**
     * @return list<string>
     */
    public static function available(): array
    {
        return (array) config('app.available_locales', ['en', 'ar', 'fr', 'es']);
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::available(), true);
    }

    public static function isRtl(string $locale): bool
    {
        return in_array($locale, (array) config('app.rtl_locales', ['ar', 'he', 'fa']), true);
    }

    public static function direction(string $locale): string
    {
        return self::isRtl($locale) ? 'rtl' : 'ltr';
    }
}
