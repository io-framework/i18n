---
title: "Catalog"
description: "One locale's catalog file, read and written by the CLI tasks."
order: 100
generated: true
---

`Io\I18n\Catalog` · final class · namespace [`Io\I18n`](index.md)

```php
final class Catalog
```

One locale's catalog file, read and written by the CLI tasks.

The file is a flat JSON object `{source text: translation}`, `""` meaning "not translated yet",
plus an optional `"__orphans"` object for entries whose source text no longer occurs in the code
(kept, not lost).

## Constants

### ORPHANS

```php
public const ORPHANS = '__orphans';
```

The key of the object holding orphaned entries.

## Properties

### $file

```php
public readonly string $file;
```

**Type** `string`

Path of the JSON file.

### $entries

```php
public array $entries = [];
```

**Type** `array<string, string>`

Source text => translation ("" = untranslated).

### $orphans

```php
public array $orphans = [];
```

**Type** `array<string, string>`

Entries whose source text is no longer in the code.

## Methods

### __construct()

```php
public function __construct(
    public readonly string $file,
    public array $entries = [],
    public array $orphans = [],
)
```

**Parameters**

- `$file` `string`: Path of the JSON file.
- `$entries` `array<string, string>`: Source text => translation ("" = untranslated).
- `$orphans` `array<string, string>`: Entries whose source text is no longer in the code.

### load()

```php
public static function load(string $file): \Io\I18n\Catalog
```

Reads a catalog file; a file that doesn't exist yet gives an empty catalog.

**Parameters**

- `$file` `string`

**Returns** [`Catalog`](Catalog.md)

**Throws**

- [`RuntimeException`](https://www.php.net/manual/en/class.runtimeexception.php): when the file is not a JSON object; invalid JSON is never silently empty.

### exists()

```php
public function exists(): bool
```

True when the file exists on disk.

**Returns** `bool`

### save()

```php
public function save(): void
```

Writes the file, entries sorted naturally and case-insensitively, orphans last.

### untranslated()

```php
public function untranslated(): array
```

**Returns** `list<string>`: Source texts without a translation.
