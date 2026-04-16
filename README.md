# Prooph Service Bus

> Fork of [prooph/service-bus](https://github.com/prooph/service-bus) — maintained for PHP 8.1–8.5 compatibility.

PHP lightweight message bus supporting CQRS and micro services.

[![CI](https://github.com/maksimovic/service-bus/actions/workflows/ci.yml/badge.svg)](https://github.com/maksimovic/service-bus/actions/workflows/ci.yml)

## Installation

```console
$ composer require maksimovic/service-bus
```

Requires PHP 8.1 or later.

## Fork rationale

The upstream package was last released in 2021 and is no longer maintained. This fork:

- Bumps minimum PHP to 8.1
- Fixes implicit nullable type deprecations (PHP 8.4+)
- Upgrades PHPUnit to `^10.5` with schema migration
- Migrates tests from `phpspec/prophecy` to native PHPUnit mocks
- Upgrades `react/promise` to `^3.3` (`Promise` → `PromiseInterface`, `done()` removal)
- Replaces Travis CI with GitHub Actions (PHP 8.1–8.5 matrix)

Behavior is otherwise unchanged.

## Messaging API

prooph/service-bus is a lightweight messaging facade. It allows you to define the API of your model with the help of messages:

1. **Command** messages describe actions your model can handle.
2. **Event** messages describe things that happened while your model handled a command.
3. **Query** messages describe available information that can be fetched from your (read) model.

## Quick Start

```php
<?php

use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\Example\Command\EchoText;
use Prooph\ServiceBus\Plugin\Router\CommandRouter;

$commandBus = new CommandBus();

$router = new CommandRouter();

$router->route('Prooph\ServiceBus\Example\Command\EchoText')
    ->to(function (EchoText $aCommand): void {
        echo $aCommand->getText();
    });

$router->attachToMessageBus($commandBus);

$echoText = new EchoText('It works');
$commandBus->dispatch($echoText);

// Output: It works
```

## Documentation

See the original documentation at [prooph/service-bus](https://github.com/prooph/service-bus/tree/master/docs).

## License

Released under the BSD-3-Clause License.
