---
title: Catalogs and the CLI tasks
description: The JSON catalog format, how a text is looked up, and the i18n tasks that keep catalogs in sync with the code.
order: 20
---

Every language except the source language has one catalog: `{directory}/{locale}.json`, a flat JSON object from source text to translation. The tasks write and update these files; a translator only fills in the empty strings.

A part of the Bakery's `lang/de.json`, while a new text still waits for its translation:

```json
{
    "Picked up": "Abgeholt",
    "Ready for pickup": "Abholbereit",
    "Thank you, {name}! Your order is reserved.": "Danke, {name}! Deine Bestellung ist reserviert.",
    "Your cart is empty.": "",
    "__orphans": {
        "Thanks, {name}!": "Danke, {name}!"
    }
}
```

- `""` means not translated yet; the source text is shown.
- `__orphans` holds entries whose text no longer occurs in the code. They are kept so a translation can be carried over after a rewording, and are never used for lookups.
- The tasks write the file sorted naturally and case-insensitively, orphans last, pretty-printed with unescaped Unicode and slashes, so diffs stay small.
- A file that is not a JSON object is an error (`lang/de.json is not a valid JSON object.`), never an empty catalog. Non-string values are skipped.

## How a text is looked up

`t('Picked up')` in locale `de`:

1. the app's `lang/de.json`, if it has a non-empty entry,
2. the bundled catalog of the plugin for `de` (the [validation messages](validation-messages.md)), unless `includeDefaults` is off,
3. the same two for the fallback locale, `I18nOptions::$fallback`, or for `$default` when no fallback is set,
4. the source text itself.

With `fallback = 'de'` a bakery with a shop in Basel can keep `de-CH.json` to the few words that differ (`"Butter croissant": "Buttergipfeli"`) and get everything else from `de.json`:

```php
$app->configure(function (I18nOptions $i18n) {
    $i18n->directory = __DIR__ . '/lang';
    $i18n->supported = ['en', 'de', 'de-CH'];
    $i18n->fallback = 'de';
});
```

The Translator reads a catalog the first time a locale is used and keeps it for the life of the process. A long-running worker (Tenma) keeps using the catalog it read until it restarts, so restart it after editing a catalog; under FPM every request reads it again.

## The tasks

The tasks run through the app's own front controller, like any `#[Task]` of Io. The Bakery runs its tasks under `tenma`, because its Postgres client needs Tenma's event loop; an app that does not need it can use `php index.php` the same way.

```bash
tenma index.php i18n:extract     # scan the code, add new texts to every catalog, move gone ones to __orphans
tenma index.php i18n:check       # exit 1 on untranslated entries, orphans, placeholder/plural mismatches, missing catalogs
tenma index.php i18n:status      # per-locale table: total, translated, empty, orphans, percent done
tenma index.php i18n:add fr      # create lang/fr.json with every source text untranslated
tenma index.php i18n:lint        # exit 1 on t($var), padded texts, "$interpolation", near-duplicate texts
```

They scan the files and directories in `I18nOptions::$scanPaths` (every `.php` file, recursively; the Bakery lists `views` and `src`, so the live components' templates and `OrderStatus::label()` are found too), or `RendererOptions::$directory` when that list is empty. The locales they work on are those in `$supported` plus every `*.json` already in the catalog directory, without `$default`. A task that finds a problem throws, so the process exits with 1.

A typical loop: write code, `i18n:extract`, translate the empty strings, `i18n:check` in CI.

### i18n:add

```bash
$ tenma index.php i18n:add fr
/srv/bakery/lang/fr.json created with 81 untranslated texts.
```

Creates the catalog with every text found in the code set to `""`. The locale must look like `fr`, `de-AT` or `zh-Hant` (two or three lowercase letters, then `-` parts); an existing file is never overwritten.

### i18n:extract

After the flash message in `ShopController::thanks()` changed from `'Thanks, {name}!'` to `'Thank you, {name}! Your order is reserved.'`:

```bash
$ tenma index.php i18n:extract
81 source texts found.
de: 1 added, 1 orphaned, 1 untranslated -> /srv/bakery/lang/de.json
```

```json
{
    "Thank you, {name}! Your order is reserved.": "",
    "__orphans": {
        "Thanks, {name}!": "Danke, {name}!"
    }
}
```

New texts are added as `""`; texts no longer in the code move to `__orphans` with their translation. When a text that is in the orphans appears in the code again, it comes back with its translation. A supported locale without a file is reported (`fr: no catalog file; run i18n:add fr`) and left alone.

### i18n:check

```bash
$ tenma index.php i18n:check
de: untranslated: "Thank you, {name}! Your order is reserved."
de: orphan: "Thanks, {name}!"
2 problem(s).
```

Every one of these fails the check:

- an entry that is `""`,
- an orphan (translate the new text, then delete the orphan),
- a translation whose `{placeholders}` differ from the source text's: `de: placeholders differ in "Sign out {name}" (missing: name; extra: name2)`,
- a translation that has `|` when the source text has none, or the other way round,
- a locale in `$supported` without a catalog file.

When everything is complete it prints `OK: every catalog is complete and consistent.` and exits with 0, which makes it a CI step:

```bash
tenma index.php i18n:extract && tenma index.php i18n:check
```

Run `extract` first, or the check compares the catalogs with the code as it was at the last extract.

### i18n:status

The Bakery, with `fr` added to `$supported` but no `fr.json` yet:

```bash
$ tenma index.php i18n:status
locale       total  translated   empty  orphans   done
de              81          81       0        0   100%
fr*              0           0       0        0   100%
(* = no catalog file yet)
```

Only reports; it never fails.

### i18n:lint

With a few careless calls in a template of the back office:

```bash
$ tenma index.php i18n:lint
/srv/bakery/views/Backoffice/Stock.php:4: t() with a non-literal argument ($label): extract cannot see it
/srv/bakery/views/Backoffice/Stock.php:5: double-quoted text contains $, and PHP interpolates it before t() sees it
/srv/bakery/views/Backoffice/Stock.php:6: text has leading or trailing whitespace: " Stock "
near-duplicate texts: "Stock" (/srv/bakery/views/_Backoffice.php:24), " Stock " (/srv/bakery/views/Backoffice/Stock.php:6)
4 finding(s).
```

Reports calls that `extract` cannot see or that split one text into several keys: a non-literal argument, whitespace around the text, a double-quoted text with `$`, and texts that are equal once trimmed, lowercased and stripped of a trailing `.`, `:`, `!` or `?`. Without findings it prints `OK: no lint findings.`, as it does for the Bakery.

## As Composer scripts

In the app's `composer.json`; arguments pass through, so `composer i18n:add fr` works:

```json
{
    "scripts": {
        "i18n:extract": "tenma index.php i18n:extract",
        "i18n:check": "tenma index.php i18n:check",
        "i18n:status": "tenma index.php i18n:status",
        "i18n:add": "tenma index.php i18n:add",
        "i18n:lint": "tenma index.php i18n:lint"
    }
}
```

See also: [`I18nTasks`](../reference/Io/I18n/I18nTasks.md), [`Catalog`](../reference/Io/I18n/Catalog.md), [Configuration](configuration.md).
