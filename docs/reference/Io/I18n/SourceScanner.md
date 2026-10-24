---
title: "SourceScanner"
description: "Finds every `->t(...)` call in PHP files and reports its first argument: a string literal, or the raw expression when it isn't one."
order: 100
generated: true
---

`Io\I18n\SourceScanner` · final class · namespace [`Io\I18n`](index.md)

```php
final class SourceScanner
```

Finds every `->t(...)` call in PHP files and reports its first argument: a string literal, or the raw expression when it isn't one.

## Methods

### scan()

```php
public static function scan(array $paths): array
```

**Parameters**

- `$paths` `list<string>`: Files or directories.

**Returns** `list<array{file: string, line: int, text: ?string, raw: string, quote: ?string}>`
