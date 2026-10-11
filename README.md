# WilyCode Router

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Tests](https://github.com/wilycode/router/actions/workflows/tests.yml/badge.svg)](https://github.com/wilycode/router/actions/workflows/tests.yml)

A lightweight PHP router for PSR-7 HTTP requests and PSR-15 middleware. It supports
named routes, typed path parameters, route groups, URL generation, and optional
precompiled route indexes. No framework or profiling package is required.

## Features

- HTTP methods: `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `HEAD`, and `any()`.
- Named routes with `{parameter}` placeholders and `[optional]` path sections.
- Typed parameter rules: `IntRule`, `FloatRule`, `BoolRule`, and `StringRule`.
- Collection prefixes, collection-level and route-level middleware.
- PSR-11 container integration and PSR-7/PSR-15 request processing.
- Named URL generation with optional query strings and fragments.
- Prepared lookup index; optional deployment-time compiled route manifest.
- Optional integrations for PHP attributes and diagnostics (not required by core).

## Requirements

- PHP 8.4 or newer.
- A PSR-11 compatible container (`psr/container`).
- PSR-7 request/response implementations and PSR-15 middleware interfaces. This
  library depends on the PSR interfaces; your application provides concrete HTTP
  message objects.

## Installation

After the first release is registered on [Packagist](https://packagist.org/):

```bash
composer require wilycode/router
```

Until then, use the repository as a development dependency in your application.
The GitHub repository alone does not make the package available on Packagist.

## Quick start

The following example assumes your application already provides a PSR-11 `$container`,
a PSR-7 `$request`, and a PSR-7 `$response` (from your HTTP message implementation):

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use WilyCode\Router\RouteCollection;
use WilyCode\Router\Router;
use WilyCode\Router\Rule\IntRule;

$routes = new RouteCollection();

$routes->get(
    name: 'hello',
    path: '/hello',
    handler: static fn (): ResponseInterface => $response,
);

$routes->get(
    name: 'article.show',
    path: '/articles/{id}',
    handler: static function (int $id) use ($response): ResponseInterface {
        // $id is validated and cast by IntRule.
        return $response;
    },
    rules: ['id' => IntRule::class],
);

$router = new Router($container);
$router->addCollection($routes);

$result = $router->dispatch($request);
$path = $router->generatePath('article.show', ['id' => 42]); // /articles/42
```

Route handlers must return a `Psr\Http\Message\ResponseInterface`. PSR-7
response creation and emission are handled by your application, not this package.

## Route collections and middleware

```php
use WilyCode\Router\RouteCollection;

$admin = (new RouteCollection())
    ->prefix('/admin')
    ->middleware(AuthenticateMiddleware::class);

$admin->get(
    name: 'article.edit',
    path: '/article/{id}/edit',
    handler: [ArticleController::class, 'edit'],
    rules: ['id' => \WilyCode\Router\Rule\IntRule::class],
);

$router->addCollection($admin);
```

`AuthenticateMiddleware` and `ArticleController` are application-provided classes.
Middleware must implement PSR-15 `MiddlewareInterface` (or be a compatible callable),
and class-based handlers are resolved through the container.

### Optional path sections

```php
$routes->get('article.list', '/articles[/{page}]', [ArticleController::class, 'list']);
```

Use `{name}` for parameters and `[ ... ]` for optional sections. Rule classes
control validation and casting. A rule that matches embedded `/` characters must
implement `RuleInterface::allowsPathSeparator()` accordingly.

## Generating URLs

```php
use WilyCode\Router\UrlGenerator;

$urls = new UrlGenerator($router);
$path = $urls->generate(
    'article.edit',
    ['id' => 42],
    ['tab' => 'seo'],
    'details',
);
// /admin/article/42/edit?tab=seo#details
```

Paths are generated using route names. Path parameter values, query values, and
fragments are URL-encoded as appropriate.

## Prepared and compiled routes

By default, `Router` prepares route indexes after loading its collections.
Static paths use direct lookups. Dynamic candidates are narrowed by a segment
index, then validated with `Route::match()` (including rules and casting).

For immutable deployments, `CompiledRoutesBuilder` can produce a scalar
manifest ahead of time. Create the manifest from your route collections, save
it in your deployment, and load it with `CompiledRoutes`:

```php
use WilyCode\Router\CompiledRoutes;
use WilyCode\Router\CompiledRoutesBuilder;

$manifest = (new CompiledRoutesBuilder())->build([$routes]);
$compiledRouter = new Router($container);
$compiledRouter->setCompiledRoutes(new CompiledRoutes($manifest));
```

Do not mix `setCompiledRoutes()` and `addCollection()` on the same router. Rebuild
saved manifests when route definitions change. For a manifest exported as PHP, use
exportable route definitions (for example class/method handlers rather than
closures or live service objects).

## Optional integrations

The following packages are **not** installed with `wilycode/router` and are **not**
required for routing:

| Package | Purpose |
| --- | --- |
| [`wilycode/route-attribute`](https://github.com/wilycode/route-attribute) | Declaring routes using PHP attributes instead of manually registering every route. |
| [`wilycode/router-profiler`](https://github.com/wilycode/router-profiler) | Optional timing and diagnostic measurements for route matching and controller execution. |

When these integrations are published and available for installation, they can be
added independently:

```bash
composer require wilycode/route-attribute
# or
composer require wilycode/router-profiler
```

Router does not import either integration. To attach a diagnostic observer,
pass an implementation of `WilyCode\Router\Observability\RoutingObserverInterface`
as the optional second constructor argument:

```php
$router = new Router($container, $observer);
```

See each integration's own documentation for its setup and API. Their suggested
package names do not make them mandatory dependencies.

## Missing routes

When no route matches, `dispatch()` throws `WilyCode\Router\NotFoundException`.
Catch it at your application boundary to produce the appropriate HTTP 404
response.

## Development

```bash
composer install
composer validate --strict
composer test
```

Tests use PHPUnit 11 and run automatically in GitHub Actions on push and pull
requests. The repository does not commit `vendor/` or `composer.lock`.

## Contributing

Bug reports and pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md)
and the [issue tracker](https://github.com/wilycode/router/issues).

## License

MIT — copyright © 2026 Simon Piter. See [LICENSE](LICENSE).
