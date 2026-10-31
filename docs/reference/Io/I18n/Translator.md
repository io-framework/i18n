---
title: "Translator"
description: "Translates source texts into the current locale, falling back to the fallback locale and then to the source text."
order: 100
generated: true
---

`Io\I18n\Translator` · final class · namespace [`Io\I18n`](index.md)

```php
final class Translator implements \Io\Validation\Messages
```

Implements `Io\Validation\Messages`

Translates source texts into the current locale, falling back to the fallback locale and then to the source text.

`t('Source text', [...])` returns the current locale's translation, else the fallback locale's,
else the source text itself. `{name}` placeholders are replaced from `$params`; `|`-separated plural
forms are picked by `$params['count']` and the CLDR plural rules of the text's locale (pluralCategories()). The plugin registers one instance as a service (`#[Inject] Translator`) and binds
it as `Validation\Messages`, so the Validator's English messages are translated the same way.
Catalogs are read once per locale and kept for the life of the process. The current locale is kept per
request (a FiberLocal), so requests a worker runs interleaved each translate in their own locale.

## Methods

### __construct()

```php
public function __construct(private \Io\I18n\I18nOptions $options)
```

**Parameters**

- `$options` [`I18nOptions`](I18nOptions.md)

### locale()

```php
public function locale(): string
```

The locale of the current request; the default locale outside a request.

**Returns** `string`

### setLocale()

```php
public function setLocale(string $locale): void
```

Sets the locale t() uses for the current request; LocaleMiddleware calls it at the start of each request.

**Parameters**

- `$locale` `string`

### t()

```php
public function t(string $text, array $params = [], ?string $locale = null): string
```

Translates a source text and fills in its placeholders. The text is returned unescaped.

```php
$translator->t('{count} pretzel|{count} pretzels', ['count' => 3]);   // "3 pretzels", or "3 Brezeln" in de
```

**Parameters**

- `$text` `string`: The source text, which is also the catalog key.
- `$params` `array<string, mixed>`: Scalars replace {placeholders}; 'count' selects the plural form.
- `$locale` `?string`: A locale for this call only, instead of the current one.

**Returns** `string`

### pluralCategories()

```php
public static function pluralCategories(string $locale): array
```

The CLDR plural categories a locale uses for whole numbers, in CLDR order: `one, other` for en
and de, `one, few, many` for ru, `other` for ja. A plural text has one `|` form per category.
Categories only very large numbers reach (fr `many` for millions) are left out; such a count
takes the last form. Without the intl extension every locale is `one, other`.

**Parameters**

- `$locale` `string`

**Returns** `list<string>`

### number()

```php
public function number(int|float $value, int $decimals = 0, ?string $locale = null): string
```

A number as the locale writes it: `8.7` in en, `8,7` in de, `12 345` in fr. Without the intl extension it is
number_format()'s `8.70`-style English.

**Parameters**

- `$value` `int|float`
- `$decimals` `int`
- `$locale` `?string`

**Returns** `string`

### date()

```php
public function date(
    \DateTimeInterface|string $date,
    string $style = 'medium',
    ?string $locale = null,
): string
```

A date as the locale writes it, in the app's time zone: `Jan 8, 2026` (medium) in en, `08.01.2026` in de.
$style is short, medium, long or full; a string date is read by DateTimeImmutable (`2026-01-08`).
Without the intl extension it is `2026-01-08`.

**Parameters**

- `$date` [`DateTimeInterface`](https://www.php.net/manual/en/class.datetimeinterface.php)`|string`
- `$style` `string`
- `$locale` `?string`

**Returns** `string`

### translate()

```php
public function translate(string $text, array $params): string
```

Validation\Messages: translates a Validator message in the current locale, the same as t().

**Parameters**

- `$text` `string`
- `$params` `array`

**Returns** `string`

### has()

```php
public function has(string $locale): bool
```

True for the default locale or any locale with a catalog file.

**Parameters**

- `$locale` `string`

**Returns** `bool`

### available()

```php
public function available(): array
```

**Returns** `list<string>`: The default locale plus every locale with a catalog file.

### catalogFile()

```php
public function catalogFile(string $locale): string
```

The path of a locale's catalog file, `{directory}/{locale}.json`, whether it exists or not.

**Parameters**

- `$locale` `string`

**Returns** `string`
