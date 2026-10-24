---
title: Configuration
description: Every option of I18nOptions, with its default and what reads it.
order: 50
---

The plugin reads only its own [`I18nOptions`](../reference/Io/I18n/I18nOptions.md). Set them with `$app->configure()`, before `attach()`. The Bakery sets `directory`, `default`, `supported` and `scanPaths` in its `app.php`; here is every option with its default:

```php
use Io\Environment;
use Io\I18n\{I18nOptions, I18nPlugin};

$app->plugin(new I18nPlugin());

$app->configure(function (I18nOptions $i18n) {
    $i18n->directory = __DIR__ . '/lang';
    $i18n->default = 'en';
    $i18n->fallback = null;
    $i18n->supported = ['en', 'de'];
    $i18n->queryParam = 'lang';
    $i18n->cookieName = 'lang';
    $i18n->cookieTtl = 31536000;
    $i18n->resolver = null;
    $i18n->includeDefaults = true;
    $i18n->scanPaths = [__DIR__ . '/views', __DIR__ . '/src'];
});
```

| Option | Default | Used by | Meaning |
|---|---|---|---|
| `directory` | `null` | translator, tasks | Where the catalogs live, `{directory}/{locale}.json`. Without it, only the bundled catalogs and the source texts are used, and every task stops with `I18nOptions::$directory is not configured.` |
| `default` | `'en'` | all | The language the source texts are written in. It needs no catalog, is always accepted for a request, and is skipped by the tasks. |
| `fallback` | `null` | translator | The locale looked up next when the current one has no translation. `null` looks in `default`, which normally has no catalog, so the source text is shown. |
| `supported` | `[]` | middleware, tasks | The locales a request may select. Empty: the default plus every locale with a catalog file. The tasks treat a listed locale without a file as a problem. |
| `queryParam` | `'lang'` | middleware | The query parameter that selects the locale and stores it in the cookie. `''` turns it off. |
| `cookieName` | `'lang'` | middleware | The cookie that remembers the selection. `''` turns it off, for reading and writing. |
| `cookieTtl` | `31536000` | middleware | The cookie's `Max-Age` in seconds (one year). |
| `resolver` | `null` | middleware | A `Closure(Request): ?string` asked before anything else; see [a custom resolver](locale.md#a-custom-resolver). |
| `includeDefaults` | `true` | translator | Use the bundled [validation-message translations](validation-messages.md) underneath your own catalogs. |
| `scanPaths` | `[]` | tasks | Files and directories the tasks scan for `t()` calls. Empty: `RendererOptions::$directory`. |

## Values from the environment

Options are plain PHP, so values that differ between machines come from `Io\Environment` (environment variables, `.env`, `config.json`) like every other Io setting. The Bakery reads its time zone that way (`BAKERY_TIMEZONE`). A deployment can, for example, offer a language only once it is translated:

```php
$env = $app->get(Environment::class);

$app->configure(function (I18nOptions $i18n) use ($env) {
    $i18n->directory = __DIR__ . '/lang';
    $i18n->supported = explode(',', $env->string('BAKERY_LOCALES', 'en,de'));
});
```

See also: [The locale of a request](locale.md), [Catalogs and the CLI tasks](catalogs.md).
