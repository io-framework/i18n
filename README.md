# io-i18n

Translations for [Io](https://github.com/Systonia/Io/framework): the text in your code is the key.

```html
<html lang="{{ $this->locale() }}">
...
<span>{{ $this->t('Bakery in Freiburg, order today, pick up fresh') }}</span>
<a href="?lang=de">DE</a>
```

The examples come from [Sonnenkorn](https://github.com/io-framework/bakery), the example bakery app,
which runs in English and German.

The source language needs no catalog. Every other language is one flat JSON file that the CLI
tasks keep in sync with the code, so nothing is maintained by hand and a missing translation shows
the source text, never a raw key.

Documentation: [guides and reference](docs/index.md): [writing text](docs/guides/translating-text.md),
[catalogs and tasks](docs/guides/catalogs.md), [locale per request](docs/guides/locale.md), [API reference](docs/reference/index.md).

## Installation

```bash
composer require io/i18n
```

Requires PHP 8.5 and `io/framework`.

## Setup

```php
use Io\I18n\{I18nOptions, I18nPlugin};

$app->plugin(new I18nPlugin());               // before attach()

$app->configure(function (I18nOptions $i18n) {
    $i18n->directory = __DIR__ . '/lang';      // lang/de.json
    $i18n->default = 'en';                     // the language the code is written in
    $i18n->supported = ['en', 'de'];
    $i18n->scanPaths = [__DIR__ . '/views', __DIR__ . '/src'];
});
```

## Writing text

- Templates: `$this->t('Sales')`, `$this->t('Sign out {name}', ['name' => $user['name']])`, `$this->locale()`.
- Services and controllers: `#[Inject] Io\I18n\Translator $translator` and `$translator->t(...)`.
- `{name}` is replaced from the parameters; `one|other` picks the first form when `count` is 1.
- `t()` never escapes, so it also works for `<title>`, attributes or mail text; in a view `{{ }}`
  escapes the result: `{{ $this->t('Thank you, {name}!', ['name' => $order->customerName]) }}`.
- Use string literals: `t($variable)` works at runtime but the tasks cannot see it (`i18n:lint` reports it).

## Catalogs

`lang/de.json`, written and updated by the tasks:

```json
{
    "Sales": "Verkäufe",
    "Thank you, {name}! Your order is reserved.": "Danke, {name}! Deine Bestellung ist reserviert.",
    "{count} pretzel|{count} pretzels": "{count} Brezel|{count} Brezeln",
    "Your cart is empty.": "",
    "__orphans": {
        "Thanks, {name}!": "Danke, {name}!"
    }
}
```

`""` means not translated yet (the source text is shown). `__orphans` holds entries whose text no
longer occurs in the code; they are kept so a translation can be carried over after a rewording.
A missing entry is looked up in `$fallback` before falling back to the source text.

## Tasks

```bash
tenma index.php i18n:extract     # scan the code, add new texts to every catalog, move gone ones to __orphans
tenma index.php i18n:check       # exit 1 on untranslated entries, orphans, placeholder/plural mismatches, missing catalogs
tenma index.php i18n:status      # per-locale table: total, translated, empty, orphans, percent done
tenma index.php i18n:add fr      # create lang/fr.json with every source text untranslated
tenma index.php i18n:lint        # exit 1 on t($var), padded texts, "$interpolation", near-duplicate texts
```

Typical loop: write code, `extract`, translate the empty strings, `check` in CI. Handy as Composer
scripts in your app (`"i18n:check": "tenma index.php i18n:check"` etc.); arguments pass through
(`composer i18n:add fr`).

## Locale per request

`LocaleMiddleware` decides once per request, first match wins:

1. `I18nOptions::$resolver`, a `Closure(Request): ?string`, if set
2. `?lang=de`, which is also stored in the `lang` cookie
3. the `lang` cookie
4. `Accept-Language`, by quality, with `de-AT` falling back to `de`
5. `$default`

Only locales in `$supported` are accepted; with `$supported` empty, any locale that has a catalog
file. The response carries `Content-Language`. The locale belongs to its request, also when Tenma runs
several requests of one worker at once, and for a WebSocket the response hands over to (Live components).

## Validation messages

The Validator's English messages (`This value should not be blank.` etc.) are translated through
this plugin like any other text. It ships German in `resources/de.json`; a same-named entry in your
own catalog wins. `I18nOptions::$includeDefaults = false` drops the bundled ones.

## Options

| Setting | Default | Description |
|---|---|---|
| `directory` | `null` | Where the catalogs live. Required for translations and the tasks. |
| `default` | `'en'` | The source language; needs no catalog. |
| `fallback` | `null` | Locale a missing translation is looked up in next; `null` means the source text. |
| `supported` | `[]` | Locales a request may select; empty means every locale with a catalog file. |
| `queryParam` | `'lang'` | Query parameter selecting the locale (`''` disables). |
| `cookieName` | `'lang'` | Cookie remembering the selection (`''` disables). |
| `cookieTtl` | `31536000` | Cookie lifetime in seconds. |
| `resolver` | `null` | `Closure(Request): ?string` consulted first. |
| `includeDefaults` | `true` | Use the bundled validation-message translations underneath yours. |
| `scanPaths` | `[]` | Files/directories the tasks scan; empty means `RendererOptions::$directory`. |

## Development

```bash
composer install
composer test    # PHPUnit on the Io\Testing kit
composer stan    # PHPStan level 10
```

## License

MIT or Apache-2.0, at your option.
