---
title: Guides
description: One topic per page, with examples taken from a running app.
order: 10
---

Each guide covers one part of the plugin, with examples from [Sonnenkorn](https://github.com/io-framework/bakery), the example bakery app, which runs in English and German. The [reference](../reference/index.md) lists every class and method.

- [Writing translatable text](translating-text.md): `t()` in templates and services, placeholders, plural forms, escaping
- [Catalogs and the CLI tasks](catalogs.md): the JSON format, fallback, orphans, `i18n:extract`, `check`, `status`, `add`, `lint`
- [The locale of a request](locale.md): query parameter, cookie, `Accept-Language`, a custom resolver
- [Validation messages](validation-messages.md): translated Validator errors and the bundled German
- [Configuration](configuration.md): every option of `I18nOptions`
- [Testing](testing.md): requests in another language and the tasks in PHPUnit
