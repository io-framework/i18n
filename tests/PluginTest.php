<?php

namespace Io\I18n\Tests;

use Io\Http\Request;
use Io\I18n\{I18nOptions, I18nPlugin, Translator};
use Io\Options\RendererOptions;
use Io\Testing\TestCase;

final class PluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->app();
        $app->plugin(new I18nPlugin());
        $app->configure(function (I18nOptions $opts) {
            $opts->directory = __DIR__ . '/catalogs';
        });
        $app->configure(function (RendererOptions $opts) {
            $opts->directory = __DIR__ . '/views';
        });
        $app->attach([HelloController::class]);
    }

    public function testDefaultLocaleRendersTheSourceText(): void
    {
        $this->get('/hello')->assertOk()->assertBody("<p>Hello Ada (en)</p>\n")->assertHeader('Content-Language', 'en');
    }

    public function testQueryParameterSelectsAndRemembersTheLocale(): void
    {
        $this->get('/hello?lang=de')->assertBody("<p>Hallo Ada (de)</p>\n")->assertHeader('Content-Language', 'de')->assertCookie('lang', 'de');
    }

    public function testCookieWinsOverAcceptLanguage(): void
    {
        $this->request('GET', '/hello')->withCookie('lang', 'de')->withHeader('Accept-Language', 'en')->send()->assertBody("<p>Hallo Ada (de)</p>\n");
    }

    public function testAcceptLanguageFallsBackToTheBaseLanguageByQuality(): void
    {
        $this->get('/hello', ['Accept-Language' => 'es;q=0.9, de-AT;q=0.8'])->assertBody("<p>Hallo Ada (de)</p>\n");
    }

    public function testUnknownLocaleFallsBackToDefault(): void
    {
        $this->get('/hello?lang=xx')->assertBody("<p>Hello Ada (en)</p>\n");
    }

    public function testAnUpgradedRequestKeepsItsLocaleForTheRuntime(): void
    {
        $app = $this->app();
        $res = $app->handle(Request::fromParts('GET', '/socket?lang=de'));
        $translator = $app->get(Translator::class);
        $this->assertNotNull($translator);
        $context = $res->getContext();
        $this->assertNotNull($context);

        // The WebSocket handler runs later, in a task that starts without the request's values
        $translator->setLocale('en');
        $context();
        $this->assertSame('de', $translator->locale());
    }

    public function testValidationMessagesAreTranslated(): void
    {
        $this->json('POST', '/signup?lang=de', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email', 'Dieser Wert ist keine gültige E-Mail-Adresse.');
        $this->json('POST', '/signup', ['email' => 'not-an-email'])
            ->assertJsonPath('errors.email', 'This value is not a valid email address.');
    }
}
