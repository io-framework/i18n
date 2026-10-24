---
title: "I18nOptions"
description: "Settings of the i18n plugin: where the catalogs live, the source language and how a request picks its locale."
order: 100
generated: true
---

`Io\I18n\I18nOptions` · class · namespace [`Io\I18n`](index.md)

```php
class I18nOptions
```

Settings of the i18n plugin: where the catalogs live, the source language and how a request picks its locale.

Set them with `$app->configure(function (I18nOptions $opts) { ... })`. Source texts in code are the
keys; a catalog is `{directory}/{locale}.json` mapping them to translations, kept up to date by the
`i18n:*` CLI tasks.

## Properties

### $directory

```php
public ?string $directory = null;
```

Where the catalogs live. Required for translations and for the CLI tasks.

### $default

```php
public string $default = 'en';
```

The language the source texts are written in; needs no catalog.

### $fallback

```php
public ?string $fallback = null;
```

Locale a missing translation is looked up in next; null looks in $default, which normally has no catalog, so the source text is shown.

### $supported

```php
public array $supported = [];
```

**Type** `list<string>`

Locales a request may select; empty = every locale with a catalog file.

### $queryParam

```php
public string $queryParam = 'lang';
```

Query parameter selecting the locale for this request and storing it in the cookie ('' disables).

### $cookieName

```php
public string $cookieName = 'lang';
```

Cookie remembering the selected locale ('' disables).

### $cookieTtl

```php
public int $cookieTtl = 31536000;
```

Cookie lifetime in seconds.

### $resolver

```php
public ?\Closure $resolver = null;
```

Custom resolver consulted first: Closure(Request): ?string.

### $includeDefaults

```php
public bool $includeDefaults = true;
```

Use the bundled translations of the Validator's messages underneath your own catalogs.

### $scanPaths

```php
public array $scanPaths = [];
```

**Type** `list<string>`

Files/directories the CLI tasks scan for t('...') calls; empty = RendererOptions::$directory.
