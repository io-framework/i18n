---
title: "I18nTasks"
description: "The catalog workflow as CLI tasks (`tenma index.php i18n:extract` etc.)."
order: 100
generated: true
---

`Io\I18n\I18nTasks` · final class · namespace [`Io\I18n`](index.md)

```php
final class I18nTasks
```

The catalog workflow as CLI tasks (`tenma index.php i18n:extract` etc.). A task
that finds problems throws, so the process exits with 1 for CI.

## Methods

### extract()

```php
public function extract(array $argv, int $argc): string
```

Scans the code for t('...') and updates every catalog: new texts get "",
texts no longer in the code move to "__orphans" (translations kept), and a
text that reappears comes back out of the orphans.

**Parameters**

- `$argv` `list<string>`
- `$argc` `int`

**Returns** `string`

### check()

```php
public function check(array $argv, int $argc): string
```

Fails (exit 1) on untranslated entries, orphans, placeholder or plural
mismatches, and supported locales without a catalog.

**Parameters**

- `$argv` `list<string>`
- `$argc` `int`

**Returns** `string`

### status()

```php
public function status(array $argv, int $argc): string
```

Prints a table per locale: total entries, translated, empty, orphans and percent done.

**Parameters**

- `$argv` `list<string>`
- `$argc` `int`

**Returns** `string`

### add()

```php
public function add(array $argv, int $argc): string
```

Creates {directory}/{locale}.json with every source text untranslated.

**Parameters**

- `$argv` `list<string>`
- `$argc` `int`

**Returns** `string`

### lint()

```php
public function lint(array $argv, int $argc): string
```

Reports t() calls that extract can't see or that will fragment the catalog:
a non-literal argument, surrounding whitespace, a double-quoted literal with
a $ in it, and texts that differ only in case or trailing punctuation.

**Parameters**

- `$argv` `list<string>`
- `$argc` `int`

**Returns** `string`
