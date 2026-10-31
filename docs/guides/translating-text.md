---
title: Writing translatable text
description: t() in templates and services, placeholders, plural forms, escaping and the rules that keep texts extractable.
order: 10
---

A translatable text is a call to `t()` with the text in the source language. The text is the catalog key, so the code reads as it renders, and a text nobody has translated yet still shows something sensible.

## In templates

The plugin adds two [view helpers](https://github.com/io-framework/framework/blob/main/README.md#plugins) to every template and layout:

From the Bakery's `views/_Layout.php` and `views/_Backoffice.php`:

```html
<html lang="{{ $this->locale() }}">
<title>@yield('title') · Sonnenkorn {{ $this->t('Back office') }}</title>
<a href="/backoffice/stock">{{ $this->t('Stock') }}</a>
<button>{{ $this->t('Sign out {name}', ['name' => $user['name']]) }}</button>
```

- `$this->t(string $text, array $params = [], ?string $locale = null)` returns the translation in the current locale.
- `$this->locale()` returns the current locale, for example `de`.

In the template of a live component, `$this` is the component. The Bakery's components inject the `Translator` as `$i18n` and call it there, as in `src/Components/Checkout.Template.php`:

```html
<button class="rounded-lg bg-amber-800 px-4 py-3 font-semibold text-white hover:bg-amber-700">{{ $this->i18n->t('Reserve my order') }}</button>
```

## In services and controllers

The same [`Translator`](../reference/Io/I18n/Translator.md) is a service; inject it. From `src/Controllers/ShopController.php`, where the checkout ends:

```php
use Io\Attributes\{Controller, Get, Inject};
use Io\Exceptions\NotFoundException;
use Io\Flash\Flash;
use Io\Http\Redirect;
use Io\I18n\Translator;

#[Controller]
final class ShopController
{
    // ... $orders and the other repositories

    #[Inject]
    private Flash $flash;

    #[Inject]
    private Translator $translator;

    #[Get('/orders/{code}/thanks')]
    public function thanks(string $code): Redirect
    {
        $order = $this->orders->byCode($code) ?? throw new NotFoundException('No such order');
        $this->flash->success($this->translator->t('Thank you, {name}! Your order is reserved.', ['name' => $order->customerName]));
        return new Redirect("/orders/{$order->code}", 303);
    }
}
```

A German customer sees "Danke, Mia! Deine Bestellung ist reserviert." once `lang/de.json` has that entry.

Code that has no request of its own takes the `Translator` as a parameter. `src/Models/OrderStatus.php` names each step of an order that way, for the order board, the customer's page and the exports:

```php
public function label(Translator $i18n): string
{
    return match ($this) {
        self::Pending => $i18n->t('Not confirmed yet'),
        self::Received => $i18n->t('Received'),
        self::Baking => $i18n->t('In the oven'),
        self::Ready => $i18n->t('Ready for pickup'),
        self::PickedUp => $i18n->t('Picked up'),
        self::Cancelled => $i18n->t('Cancelled'),
    };
}
```

The third parameter translates into a locale other than the current one. Each Bakery order keeps the language its customer ordered in, so a mail to the customer can be written in it:

```php
$subject = $this->translator->t('Your order {code} is ready for pickup.', ['code' => $order->code], $order->locale);
```

## Placeholders

`{name}` in the text is replaced with `$params['name']`. Every scalar parameter is replaced, numbers and booleans as PHP turns them into strings; arrays and objects are ignored. A placeholder without a parameter stays as it is. Placeholder names are letters, digits and `_`; `i18n:check` reports a translation whose placeholders differ from the source text's.

From `src/Components/OrderTracker.Template.php`:

```php
$this->i18n->t('Order {code} for {name}. Pickup: {day}', ['code' => $order->code, 'name' => $order->customerName, 'day' => Format::day($order->pickupDate, $this->i18n->locale())]);
// "Order 3f9a0c71be for Mia. Pickup: Fri 17 Oct"
```

The translation may put the placeholders in any order: `"Abholung am {day}: Bestellung {code} für {name}"`.

## Plural forms

A text with `|` has plural forms. When `$params['count']` is numeric, it is cut to an integer (so `1.5` counts as `1`) and its CLDR plural category in the text's locale picks the form. The forms follow the categories the locale uses for whole numbers, in CLDR order (`zero`, `one`, `two`, `few`, `many`, `other`):

| Locale | Forms | Example |
|---|---|---|
| en, de, fr | `one\|other` | 1 / 0, 2, 5 |
| ru, pl | `one\|few\|many` | 1, 21 / 2, 24 / 0, 5, 11 |
| ja | one form, no `\|` | |

`Translator::pluralCategories($locale)` returns that list. The source text is written in the default locale's forms:

```php
$this->t('{count} pretzel|{count} pretzels', ['count' => 1]);   // "1 pretzel"
$this->t('{count} pretzel|{count} pretzels', ['count' => 3]);   // "3 pretzels"
```

Each translation has its own locale's forms: `"{count} Brezel|{count} Brezeln"` in de, `"{count} крендель|{count} кренделя|{count} кренделей"` in ru, `"{count}個のプレッツェル"` in ja. The form is chosen after the lookup with the rules of the locale the text came from, so a missing translation shows the source text with the source language's rules. A count whose category has no form of its own (too few forms, or French `many` for millions) takes the last form. Without a numeric `count`, the text is returned with the `|` in it. `i18n:check` reports a translation whose number of forms does not match its locale. Without the intl extension every locale uses `one|other`.

## Escaping

`t()` never escapes. That keeps it usable for `<title>`, attributes, JSON and mail text. In a view `{{ }}` escapes the result, parameters from a user included:

```html
<button>{{ $this->t('Sign out {name}', ['name' => $user['name']]) }}</button>
```

A translation may contain markup on purpose (`"See the <a href=\"/allergens\">allergens</a>"`); then escape the parameters instead of the result and print it as a `Io\Html`:

```html
<p>{{ new Io\Html($this->t('See the <a href="/allergens">allergens</a> of {product}.', ['product' => htmlspecialchars($product->name($this->locale()))])) }}</p>
```

## Keep texts extractable

The CLI tasks find texts by scanning PHP files for `->t(` followed by a string literal. So:

- Write the text as a literal: `t('Stock')`. `t($label)` works at runtime, but `i18n:extract` cannot see the text, and `i18n:lint` reports the call.
- Use single quotes, or double quotes without `$`: in `t("Hi $name")` PHP interpolates before `t()` sees the text, so every name is a different key. Use a placeholder.
- Call it as a method, `$this->t(...)` or `$translator->t(...)`; a function named `t()` or a static call is not scanned.
- Keep whitespace and punctuation stable: `'Stock'`, `' Stock '` and `'stock.'` are three keys, which `i18n:lint` reports as near-duplicates.

Texts are compared byte for byte, so a changed comma is a new text; [`i18n:extract`](catalogs.md#i18nextract) moves the old one to the orphans with its translation.

See also: [Catalogs and the CLI tasks](catalogs.md), [`Translator`](../reference/Io/I18n/Translator.md).
