<?php

namespace App\Support;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LocalizedUrl
{
    public static function route(string $name, mixed $parameters = [], ?string $locale = null): string
    {
        $locale = self::locale($locale);
        $name = str_starts_with($name, 'en.') ? substr($name, 3) : $name;
        $localized = $locale === 'en' && Route::has('en.'.$name) ? 'en.'.$name : $name;

        return route($localized, $parameters);
    }

    /** Змінює лише зареєстровані публічні адреси; файли й зовнішні URL зберігає. */
    public static function to(string $url, ?string $locale = null): string
    {
        $locale = self::locale($locale);
        $parts = parse_url($url);

        if ($parts === false || $url === '' || str_starts_with($url, '//') || str_starts_with($url, '#')) {
            return $url;
        }

        $root = rtrim(url('/'), '/');
        if (isset($parts['scheme']) || isset($parts['host'])) {
            $rootParts = parse_url($root);
            $port = fn (array $uri) => $uri['port'] ?? (strtolower($uri['scheme'] ?? '') === 'https' ? 443 : 80);
            $basePath = rtrim($rootParts['path'] ?? '', '/');
            $absolutePath = $parts['path'] ?? '';
            if (strtolower($parts['scheme'] ?? '') !== strtolower($rootParts['scheme'] ?? '')
                || strtolower($parts['host'] ?? '') !== strtolower($rootParts['host'] ?? '')
                || $port($parts) !== $port($rootParts)
                || isset($parts['user']) || isset($parts['pass'])
                || ($basePath !== '' && $absolutePath !== $basePath && ! str_starts_with($absolutePath, $basePath.'/'))) {
                return $url;
            }
            $relative = substr($absolutePath, strlen($basePath));
            $relative .= substr($url, strcspn($url, '?#'));
        } else {
            if (! str_starts_with($url, '/')) {
                // Відносні адреси контенту трактуємо так само, як браузер.
                $resolved = (string) UriResolver::resolve(new Uri(request()->url()), new Uri($url));
                $localized = self::to($resolved, $locale);

                return $localized === $resolved ? $url : $localized;
            }
            $relative = $url;
        }

        $path = parse_url($relative, PHP_URL_PATH) ?: '/';
        $path = $path === '/en' ? '/' : (str_starts_with($path, '/en/') ? substr($path, 3) : $path);

        if (preg_match('~^/(?:admin|livewire|storage|build|vendor|up)(?:/|$)~', $path)) {
            return $url;
        }

        // Службові шляхи, файли та невідомі адреси не локалізуємо.
        try {
            $route = Route::getRoutes()->match(Request::create($root.$path));
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return $url;
        }

        if (! $route->getName() || ! Route::has('en.'.$route->getName())) {
            return $url;
        }

        // Catch-all не повинен перетворювати посилання на файли в CMS-адреси.
        if ($route->getName() === 'pages.show' && str_contains($path, '.')) {
            return $url;
        }

        $suffix = substr($relative, strlen(parse_url($relative, PHP_URL_PATH) ?: ''));
        $localized = $locale === 'en' ? '/en'.($path === '/' ? '' : $path) : $path;

        return rtrim($root.$localized, '/').$suffix;
    }

    /** Адреса того самого матеріалу з фільтрами, пошуком і пагінацією. */
    public static function current(string $locale): string
    {
        return self::to(request()->fullUrl(), $locale);
    }

    private static function locale(?string $locale): string
    {
        $locale ??= app()->getLocale();

        if (! in_array($locale, ['uk', 'en'], true)) {
            throw new InvalidArgumentException('Unsupported public locale.');
        }

        return $locale;
    }
}
