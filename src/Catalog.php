<?php

namespace Io\I18n;

use RuntimeException;
use Io\Support\Arr;

/**
 * One locale's catalog file, read and written by the CLI tasks.
 *
 * The file is a flat JSON object `{source text: translation}`, `""` meaning "not translated yet",
 * plus an optional `"__orphans"` object for entries whose source text no longer occurs in the code
 * (kept, not lost).
 */
final class Catalog
{
    /** The key of the object holding orphaned entries. */
    public const ORPHANS = '__orphans';

    /**
     * @param string $file Path of the JSON file.
     * @param array<string, string> $entries Source text => translation ("" = untranslated).
     * @param array<string, string> $orphans Entries whose source text is no longer in the code.
     */
    public function __construct(public readonly string $file, public array $entries = [], public array $orphans = [])
    {
    }

    /**
     * Reads a catalog file; a file that doesn't exist yet gives an empty catalog.
     *
     * @throws RuntimeException when the file is not a JSON object; invalid JSON is never silently empty.
     */
    public static function load(string $file): self
    {
        if (!is_file($file)) {
            return new self($file);
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new RuntimeException("$file is not a valid JSON object.");
        }
        $orphans = Arr::stringMap($data[self::ORPHANS] ?? []);
        unset($data[self::ORPHANS]);
        return new self($file, Arr::stringMap($data), $orphans);
    }

    /** True when the file exists on disk. */
    public function exists(): bool
    {
        return is_file($this->file);
    }

    /** Writes the file, entries sorted naturally and case-insensitively, orphans last. */
    public function save(): void
    {
        uksort($this->entries, 'strnatcasecmp');
        uksort($this->orphans, 'strnatcasecmp');
        $data = $this->entries;
        if ($this->orphans !== []) {
            $data[self::ORPHANS] = $this->orphans;
        }
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($this->file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /** @return list<string> Source texts without a translation. */
    public function untranslated(): array
    {
        return array_keys(array_filter($this->entries, fn (string $v): bool => $v === ''));
    }
}
