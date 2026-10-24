<?php

namespace Io\I18n;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** Finds every `->t(...)` call in PHP files and reports its first argument: a string literal, or the raw expression when it isn't one. */
final class SourceScanner
{
    /**
     * @param list<string> $paths Files or directories.
     * @return list<array{file: string, line: int, text: ?string, raw: string, quote: ?string}>
     */
    public static function scan(array $paths): array
    {
        $found = [];
        foreach (self::phpFiles($paths) as $file) {
            $source = (string) file_get_contents($file);
            preg_match_all("/->t\\(\\s*(?:(['\"])((?:\\\\.|(?!\\1).)*)\\1|([^\\s,)][^,)]*))/s", $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($matches as $m) {
                $offset = $m[0][1];
                $quote = isset($m[1]) && $m[1][1] >= 0 ? $m[1][0] : null;
                $literal = $quote !== null && isset($m[2]) ? $m[2][0] : null;
                $expression = isset($m[3]) ? trim($m[3][0]) : '';
                $found[] = [
                    'file' => $file,
                    'line' => substr_count($source, "\n", 0, $offset) + 1,
                    'text' => $literal === null ? null : self::unescape($literal, $quote),
                    'raw' => $quote !== null ? $quote . $literal . $quote : $expression,
                    'quote' => $quote,
                ];
            }
        }
        return $found;
    }

    /**
     * @param list<string> $paths
     * @return list<string>
     */
    private static function phpFiles(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;
                continue;
            }
            if (!is_dir($path)) {
                continue;
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)) as $info) {
                if ($info instanceof \SplFileInfo && $info->getExtension() === 'php') {
                    $files[] = $info->getPathname();
                }
            }
        }
        sort($files);
        return $files;
    }

    private static function unescape(string $literal, ?string $quote): string
    {
        return $quote === '"' ? stripcslashes($literal) : strtr($literal, ["\\'" => "'", '\\\\' => '\\']);
    }
}
