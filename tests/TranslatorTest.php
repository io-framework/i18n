<?php

namespace Io\I18n\Tests;

use Fiber;
use PHPUnit\Framework\TestCase;
use Io\I18n\{I18nOptions, Translator};

final class TranslatorTest extends TestCase
{
    private function translator(string $default = 'en', ?string $fallback = null): Translator
    {
        $options = new I18nOptions();
        $options->directory = __DIR__ . '/catalogs';
        $options->default = $default;
        $options->fallback = $fallback;
        return new Translator($options);
    }

    public function testTheSourceTextIsReturnedInTheDefaultLocale(): void
    {
        $this->assertSame('Hello Ada', $this->translator()->t('Hello {name}', ['name' => 'Ada']));
    }

    public function testTranslatesInTheCurrentLocaleWithPlaceholders(): void
    {
        $t = $this->translator();
        $t->setLocale('de');
        $this->assertSame('Hallo Ada', $t->t('Hello {name}', ['name' => 'Ada']));
    }

    public function testSelectsPluralByCount(): void
    {
        $t = $this->translator();
        $this->assertSame('1 file', $t->t('{count} file|{count} files', ['count' => 1]));
        $t->setLocale('de');
        $this->assertSame('3 Dateien', $t->t('{count} file|{count} files', ['count' => 3]));
    }

    public function testAnUntranslatedOrUnknownTextFallsBackToTheFallbackLocaleThenTheSource(): void
    {
        $t = $this->translator('en', 'de');
        $t->setLocale('fr');
        $this->assertSame('Start', $t->t('Home'));            // fr has no entry, de has
        $this->assertSame('Untranslated here', $t->t('Untranslated here')); // "" everywhere -> source
        $this->assertSame('Never seen', $t->t('Never seen'));
    }

    public function testBundledValidationMessagesUnderlieTheAppCatalog(): void
    {
        $t = $this->translator();
        $t->setLocale('de');
        $this->assertSame('Dieser Wert ist zu kurz. Mindestlänge ist 8.', $t->translate('This value is too short. Minimum length is {min}.', ['min' => 8]));
    }

    public function testHasAndAvailable(): void
    {
        $t = $this->translator();
        $this->assertTrue($t->has('en'));
        $this->assertTrue($t->has('de'));
        $this->assertFalse($t->has('xx'));
        $this->assertSame(['en', 'de', 'fr'], $t->available());
    }

    public function testTheLocaleIsKeptPerRequestWhenRequestsInterleave(): void
    {
        $t = $this->translator();
        $seen = [];
        // Two requests in Fibers of one worker, as under Tenma: each sets its locale, waits, then translates
        $request = function (string $locale) use ($t, &$seen): Fiber {
            return new Fiber(function () use ($t, $locale, &$seen): void {
                $t->setLocale($locale);
                Fiber::suspend();
                $seen[$locale] = $t->t('Hello {name}', ['name' => 'Ada']);
            });
        };

        $de = $request('de');
        $en = $request('en');
        $de->start();
        $en->start();
        $en->resume();
        $de->resume();

        $this->assertSame(['en' => 'Hello Ada', 'de' => 'Hallo Ada'], $seen);
        $this->assertSame('en', $t->locale());
    }

    public function testNumbersAndDatesFollowTheLocale(): void
    {
        $t = $this->translator();
        $this->assertSame('8.7', $t->number(8.66, 1));
        $this->assertSame('12,345', $t->number(12345));
        $this->assertSame('Jan 8, 2026', $t->date('2026-01-08'));
        $t->setLocale('de');
        $this->assertSame('8,7', $t->number(8.66, 1));
        $this->assertSame('08.01.2026', $t->date(new \DateTimeImmutable('2026-01-08')));
        $this->assertSame('8. Januar 2026', $t->date('2026-01-08', 'long'));
        $this->assertSame('3,50', $t->number(3.5, 2, 'fr'));
    }
}
