---
title: Io I18n
description: Translations for Io apps, with the text in the code as the key, JSON catalogs and a locale per request.
order: 1
---

Io I18n translates the texts of a [Io](https://github.com/io-framework/framework) app. The text you write in the code is the key: `$this->t('Shop')` in a template shows "Shop" in the source language and "Laden" once the German catalog has that entry. The source language needs no catalog. Every other language is one flat JSON file that CLI tasks keep in sync with the code, and a text without a translation shows the source text, never a raw key.

The plugin also picks a locale for every request (query parameter, cookie, `Accept-Language`), sends `Content-Language`, and translates the Validator's error messages, with German ones included.

## Install

```bash
composer require io/i18n
```

It needs PHP 8.5 and `io/framework`. Register the plugin before `attach()` and tell it where the catalogs live:

```php
use Io\I18n\{I18nOptions, I18nPlugin};

$app->plugin(new I18nPlugin());

$app->configure(function (I18nOptions $i18n) {
    $i18n->directory = __DIR__ . '/lang';                       // lang/de.json
    $i18n->default = 'en';                                      // the language the code is written in
    $i18n->supported = ['en', 'de'];
    $i18n->scanPaths = [__DIR__ . '/views', __DIR__ . '/src'];  // where the tasks look for t() calls
});
```

## A first translated page

The examples in these docs come from [Sonnenkorn](https://github.com/io-framework/bakery), a made-up bakery with an online shop and a back office, in English and German. The setup above is its `app.php`. The shop's layout, `views/_Layout.php`, shortened:

```html
<!doctype html>
<html lang="{{ $this->locale() }}">
<head>
    <title>@yield('title') · Sonnenkorn</title>
</head>
<body>
<header>
    <span>{{ $this->t('Bakery in Freiburg, order today, pick up fresh') }}</span>
    <nav>
        <a href="?lang=en" class="{{ $this->locale() === 'en' ? 'font-semibold' : 'text-stone-500' }}">EN</a>
        <a href="?lang=de" class="{{ $this->locale() === 'de' ? 'font-semibold' : 'text-stone-500' }}">DE</a>
    </nav>
</header>
<main>{{ $content }}</main>
</body>
</html>
```

And the catalog page that fills it, `views/Shop/Catalog.php`:

```html
@section('title')
    {{ $this->t('Shop') }}
@endsection
```

Create the German catalog from the code, then fill in the empty strings. The Bakery's `lang/de.json` has 81 texts; these two belong to this page:

```bash
tenma index.php i18n:add de
```

```json
{
    "Bakery in Freiburg, order today, pick up fresh": "Bäckerei in Freiburg, heute bestellen, frisch abholen",
    "Shop": "Laden"
}
```

Run it with `tenma index.php`. `/` answers in English with `Content-Language: en`; `/?lang=de` answers with the title "Laden · Sonnenkorn" and "Bäckerei in Freiburg, heute bestellen, frisch abholen", and sets the `lang` cookie, so the following pages stay German. A browser sending `Accept-Language: de-AT` gets German without asking.

## How these docs are organised

- **[Guides](guides/index.md)** cover one topic each: [writing translatable text](guides/translating-text.md), [catalogs and the CLI tasks](guides/catalogs.md), [the locale of a request](guides/locale.md), [validation messages](guides/validation-messages.md), [configuration](guides/configuration.md) and [testing](guides/testing.md).
- **[Reference](reference/index.md)** has one page per class, generated from the code: [`Translator`](reference/Io/I18n/Translator.md), [`I18nOptions`](reference/Io/I18n/I18nOptions.md), [`I18nTasks`](reference/Io/I18n/I18nTasks.md) and the rest.

What the plugin adds to an app:

| Part | What it does |
|---|---|
| `$this->t()`, `$this->locale()` | view helpers in every template and layout |
| [`Translator`](reference/Io/I18n/Translator.md) | the same translation in services and controllers, by `#[Inject]` |
| [`LocaleMiddleware`](reference/Io/I18n/LocaleMiddleware.md) | picks the locale of every request, sends `Content-Language` |
| `i18n:extract`, `check`, `status`, `add`, `lint` | keep the catalogs in sync with the code, fail in CI |
| bundled `resources/de.json` | German for the Validator's messages |
