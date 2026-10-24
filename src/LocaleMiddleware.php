<?php

namespace Io\I18n;

use Io\Http\{MiddlewareBase, Request, Response};

/**
 * Picks the locale of each request and sets it on the Translator; the plugin adds it app-wide.
 *
 * The order is: resolver, `?lang=`, cookie, Accept-Language, default. An explicit choice by query
 * parameter is remembered in the cookie, and the response carries Content-Language. The locale
 * stays with the request, also for a WebSocket its response hands over to (Live components).
 */
final class LocaleMiddleware extends MiddlewareBase
{
    public function __construct(private Translator $translator, private I18nOptions $options)
    {
    }

    protected function before(Request $req, Response $res): bool
    {
        $locale = $this->resolve($req, $res);
        $this->translator->setLocale($locale);
        $res->withHeader('Content-Language', $locale);
        return true;
    }

    private function resolve(Request $req, Response $res): string
    {
        $custom = $this->options->resolver !== null ? ($this->options->resolver)($req) : null;
        if (is_string($custom) && $this->supports($custom)) {
            return $custom;
        }

        $fromQuery = $this->options->queryParam !== '' ? $req->query($this->options->queryParam) : null;
        if (is_string($fromQuery) && $this->supports($fromQuery)) {
            if ($this->options->cookieName !== '') {
                $res->withCookie($this->options->cookieName, $fromQuery, maxAge: $this->options->cookieTtl, httpOnly: false);
            }
            return $fromQuery;
        }

        $fromCookie = $this->options->cookieName !== '' ? ($req->cookies[$this->options->cookieName] ?? null) : null;
        if (is_string($fromCookie) && $this->supports($fromCookie)) {
            return $fromCookie;
        }

        foreach (self::acceptLanguage($req->headers['Accept-Language'] ?? '') as $candidate) {
            if ($this->supports($candidate)) {
                return $candidate;
            }
            $base = strtok($candidate, '-');
            if (is_string($base) && $this->supports($base)) {
                return $base;
            }
        }
        return $this->options->default;
    }

    private function supports(string $locale): bool
    {
        if (!preg_match('/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $locale)) {
            return false;
        }
        return $this->options->supported !== [] ? in_array($locale, $this->options->supported, true) : $this->translator->has($locale);
    }

    /** @return list<string> Language tags by descending q. */
    private static function acceptLanguage(string $header): array
    {
        $ranked = [];
        foreach (explode(',', $header) as $i => $part) {
            [$tag, $q] = array_map('trim', explode(';q=', $part, 2) + [1 => '1']);
            if ($tag !== '' && $tag !== '*') {
                $ranked[] = [$tag, (float) $q, $i];
            }
        }
        usort($ranked, fn (array $a, array $b): int => $b[1] <=> $a[1] ?: $a[2] <=> $b[2]);
        return array_map(fn (array $r): string => $r[0], $ranked);
    }
}
