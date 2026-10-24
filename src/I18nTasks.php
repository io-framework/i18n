<?php

namespace Io\I18n;

use RuntimeException;
use Io\Attributes\{Inject, Manager, Task};
use Io\Options\RendererOptions;

/**
 * The catalog workflow as CLI tasks (`tenma index.php i18n:extract` etc.). A task
 * that finds problems throws, so the process exits with 1 for CI.
 */
#[Manager('i18n')]
final class I18nTasks
{
    private const LOCALE = '/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/';

    #[Inject]
    private I18nOptions $options; // @phpstan-ignore property.onlyRead (set by Io's PropertyInjector)

    #[Inject]
    private RendererOptions $renderer; // @phpstan-ignore property.onlyRead

    /**
     * Scans the code for t('...') and updates every catalog: new texts get "",
     * texts no longer in the code move to "__orphans" (translations kept), and a
     * text that reappears comes back out of the orphans.
     * @param list<string> $argv
     */
    #[Task('extract')]
    public function extract(array $argv, int $argc): string
    {
        $texts = $this->sourceTexts();
        $lines = [count($texts) . ' source texts found.'];
        foreach ($this->locales() as $locale) {
            $catalog = $this->catalog($locale);
            if (!$catalog->exists()) {
                $lines[] = "$locale: no catalog file; run i18n:add $locale";
                continue;
            }
            $added = 0;
            $orphaned = 0;
            foreach ($texts as $text) {
                if (!array_key_exists($text, $catalog->entries)) {
                    $catalog->entries[$text] = $catalog->orphans[$text] ?? '';
                    unset($catalog->orphans[$text]);
                    $added++;
                }
            }
            foreach (array_keys($catalog->entries) as $text) {
                if (!in_array($text, $texts, true)) {
                    $catalog->orphans[$text] = $catalog->entries[$text];
                    unset($catalog->entries[$text]);
                    $orphaned++;
                }
            }
            $catalog->save();
            $lines[] = sprintf('%s: %d added, %d orphaned, %d untranslated -> %s', $locale, $added, $orphaned, count($catalog->untranslated()), $catalog->file);
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * Fails (exit 1) on untranslated entries, orphans, placeholder or plural
     * mismatches, and supported locales without a catalog.
     * @param list<string> $argv
     */
    #[Task('check')]
    public function check(array $argv, int $argc): string
    {
        $problems = [];
        foreach ($this->locales() as $locale) {
            $catalog = $this->catalog($locale);
            if (!$catalog->exists()) {
                $problems[] = "$locale: no catalog file ({$catalog->file}); run i18n:add $locale";
                continue;
            }
            foreach ($catalog->untranslated() as $text) {
                $problems[] = "$locale: untranslated: \"$text\"";
            }
            foreach ($catalog->orphans as $text => $translation) {
                $problems[] = "$locale: orphan: \"$text\"";
            }
            foreach ($catalog->entries as $text => $translation) {
                if ($translation === '') {
                    continue;
                }
                $missing = array_diff(self::placeholders($text), self::placeholders($translation));
                $extra = array_diff(self::placeholders($translation), self::placeholders($text));
                if ($missing !== [] || $extra !== []) {
                    $problems[] = "$locale: placeholders differ in \"$text\" (missing: " . implode(', ', $missing) . '; extra: ' . implode(', ', $extra) . ')';
                }
                if (str_contains($text, '|') !== str_contains($translation, '|')) {
                    $problems[] = "$locale: plural form differs in \"$text\"";
                }
            }
        }
        if ($problems !== []) {
            throw new RuntimeException(implode("\n", $problems) . "\n" . count($problems) . " problem(s).");
        }
        return "OK: every catalog is complete and consistent.\n";
    }

    /**
     * Prints a table per locale: total entries, translated, empty, orphans and percent done.
     * @param list<string> $argv
     */
    #[Task('status')]
    public function status(array $argv, int $argc): string
    {
        $rows = [sprintf('%-10s %7s %11s %7s %8s %6s', 'locale', 'total', 'translated', 'empty', 'orphans', 'done')];
        foreach ($this->locales() as $locale) {
            $catalog = $this->catalog($locale);
            $total = count($catalog->entries);
            $empty = count($catalog->untranslated());
            $done = $total === 0 ? 100 : (int) round(($total - $empty) * 100 / $total);
            $rows[] = sprintf('%-10s %7d %11d %7d %8d %5d%%', $locale . ($catalog->exists() ? '' : '*'), $total, $total - $empty, $empty, count($catalog->orphans), $done);
        }
        $rows[] = '(* = no catalog file yet)';
        return implode("\n", $rows) . "\n";
    }

    /**
     * Creates {directory}/{locale}.json with every source text untranslated.
     * @param list<string> $argv
     */
    #[Task('add')]
    public function add(array $argv, int $argc): string
    {
        $locale = $argv[2] ?? '';
        if (!preg_match(self::LOCALE, $locale)) {
            throw new RuntimeException("Usage: i18n:add <locale>  (e.g. i18n:add fr)");
        }
        $catalog = $this->catalog($locale);
        if ($catalog->exists()) {
            throw new RuntimeException("{$catalog->file} already exists.");
        }
        foreach ($this->sourceTexts() as $text) {
            $catalog->entries[$text] = '';
        }
        $catalog->save();
        return sprintf("%s created with %d untranslated texts.\n", $catalog->file, count($catalog->entries));
    }

    /**
     * Reports t() calls that extract can't see or that will fragment the catalog:
     * a non-literal argument, surrounding whitespace, a double-quoted literal with
     * a $ in it, and texts that differ only in case or trailing punctuation.
     * @param list<string> $argv
     */
    #[Task('lint')]
    public function lint(array $argv, int $argc): string
    {
        $findings = [];
        $byNormalized = [];
        foreach (SourceScanner::scan($this->scanPaths()) as $call) {
            $where = "{$call['file']}:{$call['line']}";
            if ($call['text'] === null) {
                $findings[] = "$where: t() with a non-literal argument ({$call['raw']}): extract cannot see it";
                continue;
            }
            if ($call['text'] !== trim($call['text'])) {
                $findings[] = "$where: text has leading or trailing whitespace: \"{$call['text']}\"";
            }
            if ($call['quote'] === '"' && str_contains($call['text'], '$')) {
                $findings[] = "$where: double-quoted text contains \$, and PHP interpolates it before t() sees it";
            }
            $byNormalized[strtolower(rtrim(trim($call['text']), '.:!?'))][$call['text']] = $where;
        }
        foreach ($byNormalized as $variants) {
            if (count($variants) > 1) {
                $list = [];
                foreach ($variants as $text => $where) {
                    $list[] = "\"$text\" ($where)";
                }
                $findings[] = 'near-duplicate texts: ' . implode(', ', $list);
            }
        }
        if ($findings !== []) {
            throw new RuntimeException(implode("\n", $findings) . "\n" . count($findings) . " finding(s).");
        }
        return "OK: no lint findings.\n";
    }

    /** @return list<string> Distinct literal source texts in the scanned code, in first-seen order. */
    private function sourceTexts(): array
    {
        $texts = [];
        foreach (SourceScanner::scan($this->scanPaths()) as $call) {
            if ($call['text'] !== null && !in_array($call['text'], $texts, true)) {
                $texts[] = $call['text'];
            }
        }
        return $texts;
    }

    /** @return list<string> */
    private function scanPaths(): array
    {
        if ($this->options->scanPaths !== []) {
            return $this->options->scanPaths;
        }
        if ($this->renderer->directory !== null) {
            return [$this->renderer->directory];
        }
        throw new RuntimeException('Nothing to scan: set I18nOptions::$scanPaths (or RendererOptions::$directory).');
    }

    /** @return list<string> Every locale except the default: $supported, plus any catalog file present. */
    private function locales(): array
    {
        $dir = $this->directory();
        $locales = $this->options->supported;
        foreach (glob("$dir/*.json") ?: [] as $file) {
            $locales[] = basename($file, '.json');
        }
        return array_values(array_unique(array_filter($locales, fn (string $l): bool => $l !== $this->options->default)));
    }

    private function catalog(string $locale): Catalog
    {
        return Catalog::load($this->directory() . "/$locale.json");
    }

    private function directory(): string
    {
        return rtrim($this->options->directory ?? throw new RuntimeException('I18nOptions::$directory is not configured.'), '/');
    }

    /** @return list<string> */
    private static function placeholders(string $text): array
    {
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $text, $m);
        return array_values(array_unique($m[1]));
    }
}
