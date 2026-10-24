<?php

namespace Io\I18n\Tests;

use RuntimeException;
use Io\I18n\{I18nOptions, I18nPlugin};
use Io\Options\RendererOptions;
use Io\Testing\TestCase;

final class TasksTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = $this->tempDir();
        mkdir("$this->dir/views");
        mkdir("$this->dir/src");
        mkdir("$this->dir/lang");
        file_put_contents("$this->dir/views/Home.php", "<h1><?= \$this->t('Welcome') ?></h1><p><?= \$this->t(\"Hello {name}\", ['name' => \$n]) ?></p>\n");
        file_put_contents("$this->dir/src/Mailer.php", "<?php \$x = \$translator->t('Welcome'); \$y = \$translator->t('It\\'s done');\n");
        file_put_contents("$this->dir/lang/de.json", json_encode(['Welcome' => 'Willkommen', 'Gone' => 'Weg']) . "\n");

        $app = $this->app();
        $app->plugin(new I18nPlugin());
        $app->configure(function (I18nOptions $opts) {
            $opts->directory = "$this->dir/lang";
            $opts->supported = ['en', 'de', 'fr'];
            $opts->scanPaths = ["$this->dir/views", "$this->dir/src"];
        });
        $app->configure(function (RendererOptions $opts) {
            $opts->directory = "$this->dir/views";
        });
        $app->attach([]);
    }

    private function task(string $task, string ...$args): string
    {
        $argv = ['index.php', $task, ...$args];
        return (string) $this->app()->getRouter()->executeTask($task, $argv, count($argv));
    }

    public function testExtractAddsNewTextsMovesOrphansAndKeepsTranslations(): void
    {
        $output = $this->task('i18n:extract');

        $this->assertStringContainsString('3 source texts found.', $output);
        $this->assertStringContainsString('de: 2 added, 1 orphaned, 2 untranslated', $output);

        $de = json_decode((string) file_get_contents("$this->dir/lang/de.json"), true);
        $this->assertSame(['Hello {name}' => '', "It's done" => '', 'Welcome' => 'Willkommen', '__orphans' => ['Gone' => 'Weg']], $de);
    }

    public function testExtractRestoresAnOrphanWhoseTextReappears(): void
    {
        $this->task('i18n:extract');
        file_put_contents("$this->dir/views/Extra.php", "<?= \$this->t('Gone') ?>");

        $this->task('i18n:extract');

        $de = json_decode((string) file_get_contents("$this->dir/lang/de.json"), true);
        $this->assertSame('Weg', $de['Gone']);
        $this->assertArrayNotHasKey('__orphans', $de);
    }

    public function testCheckFailsOnEmptyOrphanedAndInconsistentEntriesAndPassesWhenClean(): void
    {
        $this->task('i18n:extract');
        try {
            $this->task('i18n:check');
            $this->fail('check should have failed');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('de: untranslated: "Hello {name}"', $e->getMessage());
            $this->assertStringContainsString('de: orphan: "Gone"', $e->getMessage());
            $this->assertStringContainsString('fr: no catalog file', $e->getMessage());
        }

        file_put_contents("$this->dir/lang/de.json", json_encode(['Welcome' => 'Willkommen', 'Hello {name}' => 'Hallo {nom}', "It's done" => 'Fertig|Fertig']));
        try {
            $this->task('i18n:check');
            $this->fail('check should have failed');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('placeholders differ in "Hello {name}" (missing: name; extra: nom)', $e->getMessage());
            $this->assertStringContainsString('plural form differs in "It\'s done"', $e->getMessage());
        }

        file_put_contents("$this->dir/lang/de.json", json_encode(['Welcome' => 'Willkommen', 'Hello {name}' => 'Hallo {name}', "It's done" => 'Fertig']));
        $this->task('i18n:add', 'fr');
        file_put_contents("$this->dir/lang/fr.json", json_encode(['Welcome' => 'Bienvenue', 'Hello {name}' => 'Bonjour {name}', "It's done" => 'Fini']));
        $this->assertStringStartsWith('OK', $this->task('i18n:check'));
    }

    public function testStatusListsEveryLocaleWithCounts(): void
    {
        $this->task('i18n:extract');

        $output = $this->task('i18n:status');

        $this->assertMatchesRegularExpression('/^de\s+3\s+1\s+2\s+1\s+33%$/m', $output);
        $this->assertMatchesRegularExpression('/^fr\*\s+0/m', $output);
    }

    public function testAddCreatesACatalogWithEverySourceTextAndRefusesToOverwrite(): void
    {
        $output = $this->task('i18n:add', 'fr');

        $this->assertStringContainsString('created with 3 untranslated texts', $output);
        $this->assertSame(['Hello {name}' => '', "It's done" => '', 'Welcome' => ''], json_decode((string) file_get_contents("$this->dir/lang/fr.json"), true));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already exists');
        $this->task('i18n:add', 'fr');
    }

    public function testAddRejectsABadLocale(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Usage');
        $this->task('i18n:add', 'not a locale');
    }

    public function testLintReportsDynamicWhitespaceInterpolationAndNearDuplicates(): void
    {
        $this->assertStringStartsWith('OK', $this->task('i18n:lint'));

        file_put_contents("$this->dir/src/Bad.php", "<?php \$t->t(\$var); \$t->t(' Padded '); \$t->t(\"Hi \$name\"); \$t->t('welcome.');\n");
        try {
            $this->task('i18n:lint');
            $this->fail('lint should have failed');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('non-literal argument ($var)', $e->getMessage());
            $this->assertStringContainsString('leading or trailing whitespace: " Padded "', $e->getMessage());
            $this->assertStringContainsString('double-quoted text contains $', $e->getMessage());
            $this->assertStringContainsString('near-duplicate texts:', $e->getMessage());
            $this->assertStringContainsString('"Welcome"', $e->getMessage());
            $this->assertStringContainsString('"welcome."', $e->getMessage());
        }
    }
}
