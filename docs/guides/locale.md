---
title: The locale of a request
description: How LocaleMiddleware picks the locale from a resolver, the query, a cookie or Accept-Language, and how to change that.
order: 30
---

The plugin adds [`LocaleMiddleware`](../reference/Io/I18n/LocaleMiddleware.md) to the app. Before the route runs, it decides the locale of the request once, sets it on the [`Translator`](../reference/Io/I18n/Translator.md), and adds `Content-Language` to the response. After the route, it sets the Translator back to the default locale.

## The order

The first source that names an accepted locale wins:

1. `I18nOptions::$resolver`, a `Closure(Request): ?string`, if set
2. the query parameter, `?lang=de`; this choice is also stored in the `lang` cookie
3. the `lang` cookie
4. `Accept-Language`, by quality, with `de-AT` falling back to `de`
5. `I18nOptions::$default`

A locale is accepted when it looks like a language tag (`de`, `pt-BR`, `zh-Hant`: two or three lowercase letters, then `-` parts) and

- is in `$supported`, when that list is set, or
- is `$default` or has a catalog file, when `$supported` is empty.

Anything else is ignored and the next source is asked, so `?lang=xx` or a cookie with a removed language simply falls through.

The Bakery's shop:

```bash
$ curl -i 'localhost:8080/?lang=de'
content-language: de
set-cookie: lang=de; Path=/; Max-Age=31536000; SameSite=Lax
...
    <title>Laden · Sonnenkorn</title>
```

The cookie is not `HttpOnly`, so a language switcher in JavaScript can read and set it too.

## Accept-Language

The header is sorted by `q` (a missing `q` counts as 1, equal values keep their order), `*` is skipped, and each tag is tried as it is and then as its base language. With `$supported = ['en', 'de']`:

| `Accept-Language` | Locale |
|---|---|
| `de-AT,de;q=0.9,en;q=0.8` | `de` (from `de-AT`) |
| `es;q=0.9, de-AT;q=0.8` | `de` (no `es`) |
| `fr, *;q=0.5` | `en`, the default |

## A language switcher

Links with the query parameter are enough: the first click stores the cookie, later pages need no parameter. The header of the Bakery's `views/_Layout.php`:

```html
<nav class="ml-auto flex items-center gap-3 text-sm">
    <a href="?lang=en" class="{{ $this->locale() === 'en' ? 'font-semibold' : 'text-stone-500' }}">EN</a>
    <a href="?lang=de" class="{{ $this->locale() === 'de' ? 'font-semibold' : 'text-stone-500' }}">DE</a>
    <app-cart-badge></app-cart-badge>
</nav>
```

The live components on the page (here the cart badge) keep the locale of the page they were rendered in, also over their WebSocket.

## A custom resolver

The resolver is asked first. It returns a locale, or `null` to let the usual order decide. What it returns must still be accepted, so a wrong value cannot select a language the app does not have. Here the subdomain picks the language (`de.sonnenkorn.test`), and `www.sonnenkorn.test` falls through to the cookie and the browser:

```php
use Io\Http\Request;
use Io\I18n\I18nOptions;

$app->configure(function (I18nOptions $i18n) {
    $i18n->directory = __DIR__ . '/lang';
    $i18n->supported = ['en', 'de'];
    $i18n->resolver = fn (Request $req): ?string => explode('.', $req->headers['Host'] ?? '')[0];
});
```

Request headers are normalized to `Some-Header` casing, so `Host` and `Accept-Language` are always found under that name.

## Turning sources off

```php
$app->configure(function (I18nOptions $i18n) {
    $i18n->queryParam = '';   // ?lang= is ignored
    $i18n->cookieName = '';   // no cookie is read or written
});
```

With both off, only the resolver and `Accept-Language` choose. `cookieTtl` sets the cookie's `Max-Age` in seconds (default one year); `queryParam` and `cookieName` rename the two.

## The locale outside a request

CLI tasks, timers and code at startup run outside the middleware, so `locale()` is `$default` there. Pass the locale to `t()` instead. The Bakery stores the customer's language with each order (`Order::$locale`, from `$this->i18n->locale()` at checkout), so a timer in `index.php` that tells customers their order is ready can write in their language:

```php
$subject = $translator->t('Your order {code} is ready for pickup.', ['code' => $order->code], $order->locale);
```

The current locale belongs to the request. The `Translator` is one shared service, but it keeps the locale in a `Io\Support\FiberLocal`, so under Tenma, where one worker runs requests concurrently, a request that waits (a database query, `Threading\Task::delay()`, an outgoing HTTP call) still translates in its own locale when it continues, whatever other requests chose in the meantime.

See also: [`LocaleMiddleware`](../reference/Io/I18n/LocaleMiddleware.md), [Configuration](configuration.md).
