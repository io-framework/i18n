<?php

namespace Io\I18n;

use Io\Support\FiberLocal;
use Io\Validation\Messages;

/**
 * Translates source texts into the current locale, falling back to the fallback locale and then to the source text.
 *
 * `t('Source text', [...])` returns the current locale's translation, else the fallback locale's,
 * else the source text itself. `{name}` placeholders are replaced from `$params`; `|`-separated plural
 * forms are picked by `$params['count']` and the CLDR plural rules of the text's locale
 * (pluralCategories()). The plugin registers one instance as a service (`#[Inject] Translator`) and binds
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

    /** @var array<string, \NumberFormatter> one per locale and decimals, built on first use */
    private array $numbers = [];

    /** @var array<string, \IntlDateFormatter> one per locale and style */
    private array $dates = [];

    private const array DATE_STYLES = ['short' => 3, 'medium' => 2, 'long' => 1, 'full' => 0];

    /** CLDR plural categories in CLDR order; a catalog's `|` forms follow this order. */
    private const array PLURAL_ORDER = ['zero', 'one', 'two', 'few', 'many', 'other'];

    private const string PLURAL_PATTERN = '{n, plural, zero{zero} one{one} two{two} few{few} many{many} other{other}}';

    /** @var array<string, \MessageFormatter> one per locale, answers a count's plural category */
    private static array $pluralFormatters = [];

    /** @var array<string, list<string>> locale => its integer plural categories */
    private static array $pluralCategories = [];

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
        $translated = $this->lookup($text, $locale);
        if ($translated === null) {
            // The plural forms follow the rules of the locale the text comes from
            $locale = $this->options->fallback ?? $this->options->default;
            $translated = $this->lookup($text, $locale);
        }
        if ($translated === null) {
            $locale = $this->options->default;
            $translated = $text;
        }
        return self::format($translated, $params, $locale);
    }

    /**
     * The CLDR plural categories a locale uses for whole numbers, in CLDR order: `one, other` for en
     * and de, `one, few, many` for ru, `other` for ja. A plural text has one `|` form per category.
     * Categories only very large numbers reach (fr `many` for millions) are left out; such a count
     * takes the last form. Without the intl extension every locale is `one, other`.
     *
     * @return list<string>
     */
    public static function pluralCategories(string $locale): array
    {
        if (isset(self::$pluralCategories[$locale])) {
            return self::$pluralCategories[$locale];
        }
        // Integer rules repeat every 100 (ru 11 vs 21, 111 vs 121), so 0 to 199 reaches every category
        $seen = [];
        foreach (range(0, 199) as $count) {
            $seen[self::pluralCategory($locale, $count)] = true;
        }
        $categories = array_filter(self::PLURAL_ORDER, fn (string $category): bool => isset($seen[$category]));

        return self::$pluralCategories[$locale] = array_values($categories);
    }

    /** The CLDR plural category of a whole number in a locale: `few` for 24 in ru. */
    private static function pluralCategory(string $locale, int $count): string
    {
        if (!class_exists(\MessageFormatter::class)) {
            return $count === 1 ? 'one' : 'other';
        }
        $formatter = self::$pluralFormatters[$locale] ??= new \MessageFormatter($locale, self::PLURAL_PATTERN);
        $category = $formatter->format(['n' => $count]);

        return is_string($category) && in_array($category, self::PLURAL_ORDER, true) ? $category : 'other';
    }

    /**
     * A number as the locale writes it: `8.7` in en, `8,7` in de, `12 345` in fr. Without the intl extension it is
     * number_format()'s `8.70`-style English.
     */
    public function number(float|int $value, int $decimals = 0, ?string $locale = null): string
    {
        $locale ??= $this->locale();
        if (!class_exists(\NumberFormatter::class)) {
            return number_format($value, $decimals);
        }
        $formatter = $this->numbers["$locale/$decimals"] ??= self::numberFormatter($locale, $decimals);
        $text = $formatter->format($value);

        return $text === false ? number_format($value, $decimals) : $text;
    }

    /**
     * A date as the locale writes it, in the app's time zone: `Jan 8, 2026` (medium) in en, `08.01.2026` in de.
     * $style is short, medium, long or full; a string date is read by DateTimeImmutable (`2026-01-08`).
     * Without the intl extension it is `2026-01-08`.
     */
    public function date(\DateTimeInterface|string $date, string $style = 'medium', ?string $locale = null): string
    {
        $locale ??= $this->locale();
        $date = is_string($date) ? new \DateTimeImmutable($date) : $date;
        $dateStyle = self::DATE_STYLES[$style] ?? throw new \InvalidArgumentException("Date style $style: short, medium, long or full");
        if (!class_exists(\IntlDateFormatter::class)) {
            return $date->format('Y-m-d');
        }
        $formatter = $this->dates["$locale/$style"] ??= new \IntlDateFormatter($locale, $dateStyle, \IntlDateFormatter::NONE);
        $text = $formatter->format($date);

        return $text === false ? $date->format('Y-m-d') : $text;
    }

    private static function numberFormatter(string $locale, int $decimals): \NumberFormatter
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        return $formatter;
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
    private static function format(string $message, array $params, string $locale): string
    {
        $count = $params['count'] ?? null;
        if (str_contains($message, '|') && is_numeric($count)) {
            $message = self::pluralForm(explode('|', $message), (int) $count, $locale);
        }
        foreach ($params as $name => $value) {
            if (is_scalar($value)) {
                $message = str_replace('{' . $name . '}', (string) $value, $message);
            }
        }
        return $message;
    }

    /**
     * The form for $count: forms map to pluralCategories() in order. A category without a form of its
     * own (too few forms, or fr `many`) takes the last form.
     *
     * @param list<string> $forms
     */
    private static function pluralForm(array $forms, int $count, string $locale): string
    {
        $categories = self::pluralCategories($locale);
        $index = array_search(self::pluralCategory($locale, $count), $categories, true);
        $last = count($forms) - 1;
        if ($index === false || $index > $last) {
            return $forms[$last];
        }
        return $forms[$index];
    }
}
