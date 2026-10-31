<?php

namespace Io\I18n\Tests;

use PHPUnit\Framework\TestCase;
use Io\I18n\{I18nOptions, Translator};

final class PluralTest extends TestCase
{
    private const string TITLES = '{count} title|{count} titles';

    private Translator $translator;

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir() . '/io-i18n-plural-' . getmypid();
        @mkdir($dir);
        file_put_contents("$dir/ru.json", json_encode([self::TITLES => '{count} тайтл|{count} тайтла|{count} тайтлов']));
        file_put_contents("$dir/ja.json", json_encode([self::TITLES => '{count}件']));
        file_put_contents("$dir/de.json", json_encode([self::TITLES => '{count} Titel|{count} Titel']));

        $options = new I18nOptions();
        $options->directory = $dir;
        $this->translator = new Translator($options);
    }

    public function testTheIntegerCategoriesFollowCldr(): void
    {
        $this->assertSame(['one', 'other'], Translator::pluralCategories('en'));
        $this->assertSame(['one', 'other'], Translator::pluralCategories('de'));
        $this->assertSame(['one', 'other'], Translator::pluralCategories('fr'));
        $this->assertSame(['one', 'few', 'many'], Translator::pluralCategories('ru'));
        $this->assertSame(['other'], Translator::pluralCategories('ja'));
    }

    public function testRussianPicksOneFewOrMany(): void
    {
        $expected = [
            1 => '1 тайтл',
            21 => '21 тайтл',
            2 => '2 тайтла',
            24 => '24 тайтла',
            5 => '5 тайтлов',
            11 => '11 тайтлов',
            12 => '12 тайтлов',
            0 => '0 тайтлов',
        ];
        foreach ($expected as $count => $text) {
            $this->assertSame($text, $this->translator->t(self::TITLES, ['count' => $count], 'ru'));
        }
    }

    public function testJapaneseHasOneFormAndTwoFormLocalesStayOneOther(): void
    {
        $this->assertSame('3件', $this->translator->t(self::TITLES, ['count' => 3], 'ja'));
        $this->assertSame('1 title', $this->translator->t(self::TITLES, ['count' => 1]));
        $this->assertSame('0 titles', $this->translator->t(self::TITLES, ['count' => 0]));
        $this->assertSame('2 Titel', $this->translator->t(self::TITLES, ['count' => 2], 'de'));
    }

    public function testAMissingTranslationUsesTheSourceLocalesRules(): void
    {
        // ru has no entry: the English source is shown with English rules (21 is "one" in ru)
        $this->assertSame('21 files', $this->translator->t('{count} file|{count} files', ['count' => 21], 'ru'));
    }
}
