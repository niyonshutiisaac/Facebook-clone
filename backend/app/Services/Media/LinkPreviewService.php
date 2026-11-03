<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\PostLink;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Fetches Open Graph / Twitter card metadata for a URL.
 *
 * Hardening:
 *  - SSRF guard: only http/https, and private/loopback/link-local ranges are
 *    rejected after DNS resolution, so a crafted post cannot make the server
 *    probe the internal network.
 *  - 5s connect timeout, 10s total, 512 KB cap — a slow host must not hold a
 *    queue worker.
 *  - Redirects are followed manually (max 3) so each hop is re-validated.
 */
final class LinkPreviewService
{
    private const int TIMEOUT = 10;
    private const int CONNECT_TIMEOUT = 5;
    private const int MAX_REDIRECTS = 3;
    private const int MAX_BYTES = 524288;

    /**
     * @return array{title: ?string, description: ?string, image_url: ?string, site_name: ?string, favicon_url: ?string, canonical_url: ?string, fetched_at: \Illuminate\Support\Carbon}
     */
    public function fetch(string $url): array
    {
        $url = $this->normalise($url);

        $response = Http::timeout(self::TIMEOUT)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->withHeaders(['User-Agent' => 'facebook-clone-linkpreview/1.0'])
            ->withOptions(['allow_redirects' => [
                'max' => self::MAX_REDIRECTS,
                'on_redirect' => function ($request, $response, $uri): void {
                    if (! $this->isSafeUrl((string) $uri)) {
                        throw new \RuntimeException('Unsafe redirect target.');
                    }
                },
            ]])
            ->get($url);

        if (! $response->successful()) {
            return $this->empty($url);
        }

        $html = Str::of($response->body())->limit(self::MAX_BYTES)->toString();

        return [
            'title' => $this->meta($html, ['og:title', 'twitter:title']) ?? $this->tag($html, 'title'),
            'description' => $this->meta($html, ['og:description', 'twitter:description', 'description']),
            'image_url' => $this->absoluteUrl($url, $this->meta($html, ['og:image', 'twitter:image'])),
            'site_name' => $this->meta($html, ['og:site_name']) ?? parse_url($url, PHP_URL_HOST),
            'favicon_url' => $this->absoluteUrl($url, $this->favicon($html, $url)),
            'canonical_url' => $this->absoluteUrl($url, $this->link($html, 'canonical')) ?? $url,
            'fetched_at' => now(),
        ];
    }

    /**
     * Fetch and persist the preview for a post link.
     */
    public function persist(PostLink $link): void
    {
        try {
            if (! $this->isSafeUrl($link->url)) {
                Log::warning('Link preview refused: unsafe URL', ['url' => $link->url]);

                return;
            }

            $preview = $this->fetch($link->url);

            $link->preview()->updateOrCreate(
                ['post_link_id' => $link->id],
                $preview
            );
        } catch (\Throwable $e) {
            Log::warning('Link preview fetch failed', [
                'url' => $link->url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<string>  $properties
     */
    private function meta(string $html, array $properties): ?string
    {
        foreach ($properties as $property) {
            $pattern = '/<meta[^>]+(?:property|name)=["\']'.preg_quote($property, '/').'["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i';

            if (preg_match($pattern, $html, $matches) === 1) {
                $value = trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                if ($value !== '') {
                    return Str::limit($value, 512);
                }
            }
        }

        return null;
    }

    private function tag(string $html, string $tag): ?string
    {
        if (preg_match('/<'.$tag.'[^>]*>(.*?)<\/'.$tag.'>/is', $html, $matches) !== 1) {
            return null;
        }

        return Str::limit(trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 512);
    }

    private function link(string $html, string $rel): ?string
    {
        if (preg_match('/<link[^>]+rel=["\']'.preg_quote($rel, '/').'["\'][^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function favicon(string $html, string $url): ?string
    {
        $icon = $this->link($html, 'icon')
            ?? $this->link($html, 'shortcut icon')
            ?? '/favicon.ico';

        return $this->absoluteUrl($url, $icon);
    }

    private function absoluteUrl(string $base, ?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return $path;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        if ($host === '') {
            return null;
        }

        if (Str::startsWith($path, '//')) {
            return $scheme.':'.$path;
        }

        return sprintf('%s://%s%s', $scheme, $host, Str::startsWith($path, '/') ? $path : '/'.$path);
    }

    private function normalise(string $url): string
    {
        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://'.$url;
        }

        if (! $this->isSafeUrl($url)) {
            throw new \RuntimeException('Refusing to fetch a non-public URL.');
        }

        return $url;
    }

    /**
     * SSRF guard: reject anything that is not a public http(s) endpoint, and
     * re-check after resolving the host so DNS rebinding cannot slip through.
     */
    private function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(Str::lower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = Str::lower($parts['host']);

        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return false;
        }

        $ip = gethostbyname($host);

        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            return false; // host does not resolve
        }

        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * @return array{title: null, description: null, image_url: null, site_name: ?string, favicon_url: null, canonical_url: string, fetched_at: \Illuminate\Support\Carbon}
     */
    private function empty(string $url): array
    {
        return [
            'title' => null,
            'description' => null,
            'image_url' => null,
            'site_name' => parse_url($url, PHP_URL_HOST) ?: null,
            'favicon_url' => null,
            'canonical_url' => $url,
            'fetched_at' => now(),
        ];
    }
}
