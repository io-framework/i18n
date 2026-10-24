---
title: "I18nPlugin"
description: "Translations for Io: register with `$app->plugin(new I18nPlugin())` and configure I18nOptions."
order: 100
generated: true
---

`Io\I18n\I18nPlugin` · final class · namespace [`Io\I18n`](index.md)

```php
final class I18nPlugin extends \Io\Plugin\PluginBase implements \Io\Plugin\ViewHelper
```

Extends `Io\Plugin\PluginBase`  
Implements `Io\Plugin\ViewHelper`

Translations for Io: register with `$app->plugin(new I18nPlugin())` and configure I18nOptions.

Adds
`$this->t('Source text', [...])` and `$this->locale()` to templates, resolves the
locale per request (LocaleMiddleware), translates the Validator's messages, and
provides the i18n:extract/check/status/add/lint CLI tasks.

## Methods

### name()

```php
public function name(): string
```

Short unique name ('i18n'): the plan namespace for its route data, and the prefix in logs.

**Returns** `string`

### boot()

```php
public function boot(\Io\Container $container, \Io\I18n\I18nOptions $options): void
```

Creates the Translator, registers it as a service and binds it as the Validator's Messages.

**Parameters**

- `$container` `\Io\Container`
- `$options` [`I18nOptions`](I18nOptions.md)

### classes()

```php
public function classes(): array
```

**Returns** `list<class-string>`: Controllers and #[Manager] classes attach() adds.

### middlewares()

```php
public function middlewares(): array
```

**Returns** `list<callable>`: App-level middlewares, added at attach() in registration order.

### hooks()

```php
public function hooks(): array
```

**Returns** `list<object>`: Instances of Endpoint, Fallback, RouteAttributeCompiler, Guard, ResponseHook, ArgumentBinder, Renderer, OpenApiExtension, ViewHelper, ClientAsset.

### helpers()

```php
public function helpers(): array
```

`$this->t(...)` and `$this->locale()` in templates.

**Returns** `array`
