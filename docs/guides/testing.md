---
title: Testing
description: Requests in another language, catalogs as fixtures, and the i18n tasks in PHPUnit.
order: 60
---

Tests use Io's testing kit, `Io\Testing\TestCase`. Requests go through the whole app, middleware included, so the locale is picked exactly as in production.

## Translated pages and responses

The Bakery's tests extend `Bakery\Tests\Support\AppTestCase`, a `TestCase` that loads the real `app.php`, so they run with the real `lang/de.json`. Choose the language per request with the query parameter, a cookie or `Accept-Language`. From `tests/Controllers/ShopControllerTest.php`, with three more cases in the same style:

```php
use Bakery\Tests\Support\AppTestCase;

final class ShopControllerTest extends AppTestCase
{
    public function testTheCatalogShowsTheRangeInEnglishAndGerman(): void
    {
        $this->get('/')->assertOk()->assertSee('Lye pretzel')->assertHeader('Content-Language', 'en');
        $this->get('/?lang=de')->assertOk()->assertSee('Laugenbrezel')->assertSee('In den Warenkorb')
            ->assertCookie('lang', 'de');
    }

    public function testTheBrowserLanguage(): void
    {
        $this->get('/', ['Accept-Language' => 'de-AT,de;q=0.9'])->assertSee('In den Warenkorb');
    }

    public function testTheCookie(): void
    {
        $this->request('GET', '/')->withCookie('lang', 'de')->send()->assertSee('In den Warenkorb');
    }

    public function testValidationErrorsInGerman(): void
    {
        $this->json('POST', '/v1/tokens?lang=de', ['email' => 'ben', 'password' => ''])
            ->assertStatus(422)
            ->assertJsonPath('errors.email', 'Dieser Wert ist keine gültige E-Mail-Adresse.');
    }
}
```

A live component has no request of its own in a test; set the locale on the `Translator` before mounting it. From `tests/Components/CheckoutTest.php`:

```php
public function testTheFormIsValidatedInTheCustomersLanguage(): void
{
    $this->app()->get(Translator::class)->setLocale('de');
    $tab = $this->customerTab();
    $tab->mount('app-add-to-cart', ['product' => '4'])->click('add');
    $tab->mount('app-checkout')
        ->submit('form', ['name' => '', 'email' => 'nope', 'pickupDate' => date('Y-m-d')])
        ->assertSee('Dieser Wert darf nicht leer sein.')
        ->assertSee('Dieser Wert ist keine gültige E-Mail-Adresse.')
        ->assertSee('Bitte wähle einen der Abholtage.');
}
```

## The Translator on its own

The [`Translator`](../reference/Io/I18n/Translator.md) needs nothing but its options, so formatting and fallback can be tested with plain PHPUnit:

```php
use PHPUnit\Framework\TestCase;
use Io\I18n\{I18nOptions, Translator};

final class PluralTest extends TestCase
{
    public function testGermanPlural(): void
    {
        $options = new I18nOptions();
        $options->directory = __DIR__ . '/lang';   // tests/lang/de.json: {"{count} pretzel|{count} pretzels": "{count} Brezel|{count} Brezeln"}
        $translator = new Translator($options);
        $translator->setLocale('de');

        $this->assertSame('3 Brezeln', $translator->t('{count} pretzel|{count} pretzels', ['count' => 3]));
    }
}
```

## Catalogs in CI

The simplest guard runs the tasks themselves; `i18n:check` exits with 1 when a text is untranslated or a translation lost a placeholder:

```bash
tenma index.php i18n:extract && tenma index.php i18n:check && tenma index.php i18n:lint
```

Run `extract` on a clean checkout; if it changes a catalog, someone forgot to run it. A CI step can fail on that:

```bash
tenma index.php i18n:extract && git diff --exit-code lang/
```

The tasks can also run inside a test. They are methods on the router; a failing task throws a `RuntimeException` with the problems in its message:

```php
public function testEveryCatalogIsComplete(): void
{
    $argv = ['index.php', 'i18n:check'];
    $output = (string) $this->app()->getRouter()->executeTask('i18n:check', $argv, count($argv));
    $this->assertStringStartsWith('OK', $output);
}
```

For that test, configure `directory` and `scanPaths` with the app's real catalogs and code, not fixtures; an `AppTestCase` like the Bakery's already has them from `app.php`.

See also: [Io's testing kit](https://github.com/io-framework/framework/blob/main/README.md#testing).
