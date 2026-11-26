<?php

declare(strict_types=1);

namespace App\Services\PdaVerification;

use App\Enums\PdaVerificationCode;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Validasi link universal: format URL, domain/path terlarang, dan aksesibilitas publik (HTTP 200).
 */
class PdaLinkValidator
{
    /** @var array<int, string> */
    private const FORBIDDEN_HOSTS = ['drive.google.com', 'docs.google.com'];

    /** @var array<int, string> */
    private const FORBIDDEN_PATH_SEGMENTS = ['login', 'signin', 'auth', 'dashboard', 'admin'];

    /** @var array<string, ?VerificationIssue> */
    private array $cache = [];

    /**
     * Mengembalikan null jika link valid, atau VerificationIssue jika bermasalah.
     */
    public function validate(string $url): ?VerificationIssue
    {
        return $this->cache[$url] ??= $this->check($url);
    }

    public function isWellFormed(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ($parts['host'] ?? '') !== '';
    }

    public function forbiddenReason(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        foreach (self::FORBIDDEN_HOSTS as $forbiddenHost) {
            if ($host === $forbiddenHost || str_ends_with($host, ".{$forbiddenHost}")) {
                return "mengarah ke Google Drive/Docs ({$host})";
            }
        }

        $segments = array_filter(explode('/', $path), fn (string $segment): bool => $segment !== '');

        foreach ($segments as $segment) {
            if (in_array($segment, self::FORBIDDEN_PATH_SEGMENTS, true)) {
                return "mengarah ke halaman login/dashboard (/{$segment})";
            }
        }

        return null;
    }

    private function check(string $url): ?VerificationIssue
    {
        if (! $this->isWellFormed($url)) {
            return new VerificationIssue(
                PdaVerificationCode::MalformedLink,
                'link_universal',
                "Format URL tidak valid: {$url}",
            );
        }

        if (($reason = $this->forbiddenReason($url)) !== null) {
            return $this->invalid("Link universal {$reason}.");
        }

        $maxRedirects = (int) config('services.pda.link_check.max_redirects', 5);
        $currentUrl = $url;

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            try {
                $response = $this->request($currentUrl);
            } catch (Throwable $exception) {
                return $this->invalid("Link universal tidak dapat diakses: {$exception->getMessage()}");
            }

            if (! $response->redirect()) {
                return $response->status() === 200
                    ? null
                    : $this->invalid("Link universal mengembalikan HTTP {$response->status()} (wajib HTTP 200).");
            }

            $location = trim($response->header('Location'));

            if ($location === '') {
                return $this->invalid("Link universal mengembalikan redirect HTTP {$response->status()} tanpa header Location.");
            }

            $currentUrl = $this->resolveUrl($currentUrl, $location);

            if (! $this->isWellFormed($currentUrl)) {
                return $this->invalid("Link universal di-redirect ke URL tidak valid: {$currentUrl}");
            }

            if (($reason = $this->forbiddenReason($currentUrl)) !== null) {
                return $this->invalid("Link universal di-redirect ke {$currentUrl} yang {$reason}.");
            }
        }

        return $this->invalid("Link universal melebihi batas {$maxRedirects} redirect.");
    }

    private function request(string $url): Response
    {
        return Http::withOptions(['allow_redirects' => false])
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; AkreditasiPBL-LinkVerifier/1.0)'])
            ->connectTimeout((int) config('services.pda.link_check.connect_timeout', 5))
            ->timeout((int) config('services.pda.link_check.timeout', 10))
            ->get($url);
    }

    private function resolveUrl(string $baseUrl, string $location): string
    {
        if (preg_match('~^https?://~i', $location) === 1) {
            return $location;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? 'https';

        if (str_starts_with($location, '//')) {
            return "{$scheme}:{$location}";
        }

        $origin = "{$scheme}://".($base['host'] ?? '').(isset($base['port']) ? ":{$base['port']}" : '');

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $directory = rtrim(str_replace('\\', '/', dirname($base['path'] ?? '/')), '/');

        return "{$origin}{$directory}/{$location}";
    }

    private function invalid(string $message): VerificationIssue
    {
        return new VerificationIssue(PdaVerificationCode::InvalidLink, 'link_universal', $message);
    }
}
