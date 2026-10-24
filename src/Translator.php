<?php

namespace Io\I18n;

use Io\Support\FiberLocal;
use Io\Validation\Messages;

/**
 * Translates source texts into the current locale, falling back to the fallback locale and then to the source text.
 *
 * `t('Source text', [...])` returns the current locale's translation, else the fallback locale's,
 * else the source text itself. `{name}` placeholders are replaced from `$params`; `one|other` picks by
 * `$params['count']`. The plugin registers one instance as a service (`#[Inject] Translator`) and binds
 * it as `Validation\Messages`, so the Validator's English messages are translated the same way.
 * Catalogs are read once per locale and kept for the life of the process. The current locale is kept per
 * request (a FiberLocal), so requests a worker runs interleaved each translate in their own locale.
 */
final class Translator implements Messages
{
    /** @var array<string, array<string, string>> locale => source => translation (bundled defaults underneath the app's catalog) */
    private array $catalogs = [];

    /** @var FiberLocal<string> */
    private FiberLocal $locale;

    public function __construct(private I18nOptions $options)
    {
        $this->locale = new FiberLocal();
    }

    /** The locale of the current request; the default locale outside a request. */
    public function locale(): string
    {
        return $this->locale->get() ?? $this->options->default;
    }

    /** Sets the locale t() uses for the current request; LocaleMiddleware calls it at the start of each request. */
    public function setLocale(string $locale): void
    {
        $this->locale->set($locale);
    }

    /**
     * Translates a source text and fills in its placeholders. The text is returned unescaped.
     *
     * ```php
     * $translator->t('{count} pretzel|{count} pretzels', ['count' => 3]);   // "3 pretzels", or "3 Brezeln" in de
     * ```
     *
     * @param string $text The source text, which is also the catalog key.
     * @param array<string, mixed> $params Scalars replace {placeholders}; 'count' selects the plural form.
     * @param ?string $locale A locale for this call only, instead of the current one.
     */
    public function t(string $text, array $params = [], ?string $locale = null): string
    {
        $locale ??= $this->locale();
        $translated = $this->lookup($text, $locale)
            ?? $this->lookup($text, $this->options->fallback ?? $this->options->default)
            ?? $text;
        return self::format($translated, $params);
    }

    /** Validation\Messages: translates a Validator message in the current locale, the same as t(). */
    public function translate(string $text, array $params): string
    {
        return $this->t($text, $params);
    }

    /** True for the default locale or any locale with a catalog file. */
    public function has(string $locale): bool
    {
        return $locale === $this->options->default || is_file($this->catalogFile($locale));
    }

    /** @return list<string> The default locale plus every locale with a catalog file. */
    public function available(): array
    {
        $locales = [$this->options->default];
        foreach (glob(($this->options->directory ?? '') . '/*.json') ?: [] as $file) {
            $locales[] = basename($file, '.json');
        }
        return array_values(array_unique($locales));
    }

    /** The path of a locale's catalog file, `{directory}/{locale}.json`, whether it exists or not. */
    public function catalogFile(string $locale): string
    {
        return rtrim($this->options->directory ?? '', '/') . "/$locale.json";
    }

    private function lookup(string $text, string $locale): ?string
    {
        $value = $this->catalog($locale)[$text] ?? '';
        return $value === '' ? null : $value;
    }

    /** @return array<string, string> */
    private function catalog(string $locale): array
    {
        if (!isset($this->catalogs[$locale])) {
            $entries = [];
            if ($this->options->includeDefaults) {
                $entries = Catalog::load(__DIR__ . "/../resources/$locale.json")->entries;
            }
            if ($this->options->directory !== null) {
                $entries = array_filter(Catalog::load($this->catalogFile($locale))->entries, fn (string $v): bool => $v !== '') + $entries;
            }
            $this->catalogs[$locale] = $entries;
        }
        return $this->catalogs[$locale];
    }

    /** @param array<string, mixed> $params */
    private static function format(string $message, array $params): string
    {
        $count = $params['count'] ?? null;
        if (str_contains($message, '|') && is_numeric($count)) {
            [$one, $other] = explode('|', $message, 2);
            $message = (int) $count === 1 ? $one : $other;
        }
        foreach ($params as $name => $value) {
            if (is_scalar($value)) {
                $message = str_replace('{' . $name . '}', (string) $value, $message);
            }
        }
        return $message;
    }
}
