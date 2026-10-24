---
title: "LocaleMiddleware"
description: "Picks the locale of each request and sets it on the Translator; the plugin adds it app-wide."
order: 100
generated: true
---

`Io\I18n\LocaleMiddleware` · final class · namespace [`Io\I18n`](index.md)

```php
final class LocaleMiddleware extends \Io\Http\MiddlewareBase
```

Extends `Io\Http\MiddlewareBase`

Picks the locale of each request and sets it on the Translator; the plugin adds it app-wide.

The order is: resolver, `?lang=`, cookie, Accept-Language, default. An explicit choice by query
parameter is remembered in the cookie, and the response carries Content-Language. The locale
stays with the request, also for a WebSocket its response hands over to (Live components).

## Methods

### __construct()

```php
public function __construct(
    private \Io\I18n\Translator $translator,
    private \Io\I18n\I18nOptions $options,
)
```

**Parameters**

- `$translator` [`Translator`](Translator.md)
- `$options` [`I18nOptions`](I18nOptions.md)
