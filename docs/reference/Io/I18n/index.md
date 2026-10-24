---
title: "Io\\I18n"
description: "The classes, interfaces, traits and enums in Io\\I18n."
order: 200
generated: true
---

## Types

- [`Catalog`](Catalog.md) · final class: One locale's catalog file, read and written by the CLI tasks.
- [`I18nOptions`](I18nOptions.md) · class: Settings of the i18n plugin: where the catalogs live, the source language and how a request picks its locale.
- [`I18nPlugin`](I18nPlugin.md) · final class: Translations for Io: register with `$app->plugin(new I18nPlugin())` and configure I18nOptions.
- [`I18nTasks`](I18nTasks.md) · final class: The catalog workflow as CLI tasks (`tenma index.php i18n:extract` etc.).
- [`LocaleMiddleware`](LocaleMiddleware.md) · final class: Picks the locale of each request and sets it on the Translator; the plugin adds it app-wide.
- [`SourceScanner`](SourceScanner.md) · final class: Finds every `->t(...)` call in PHP files and reports its first argument: a string literal, or the raw expression when it isn't one.
- [`Translator`](Translator.md) · final class: Translates source texts into the current locale, falling back to the fallback locale and then to the source text.
