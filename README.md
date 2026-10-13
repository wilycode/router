# WilyCode Router

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Tests](https://github.com/wilycode/router/actions/workflows/tests.yml/badge.svg)](https://github.com/wilycode/router/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/wilycode/router)](https://packagist.org/packages/wilycode/router)

**A lightweight PSR-7/PSR-15 HTTP router for PHP 8.4+.** Define named routes, validate typed path parameters, build middleware pipelines, and generate URLs without tying your application to a framework.

> **WilyCode PHP Ecosystem**
>
> Part of **[WilyCode](https://github.com/wilycode/wilycode)** — independent, reusable PHP components with separate repositories, releases, and Composer installation.
>
> **[Explore the WilyCode ecosystem →](https://github.com/wilycode/wilycode)**

## Table of contents

- [Overview](#overview)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Defining routes](#defining-routes)
  - [HTTP methods](#http-methods)
  - [Named routes](#named-routes)
  - [Route parameters and rules](#route-parameters-and-rules)
  - [Custom regular expressions](#custom-regular-expressions)
  - [UUID, slugs, enumerated values, and dates](#uuid-slugs-enumerated-values-and-dates)
  - [Rule validation when generating URLs](#rule-validation-when-generating-urls)
  - [Optional path sections and defaults](#optional-path-sections-and-defaults)
- [Route collections and prefixes](#route-collections-and-prefixes)
- [Middleware](#middleware)
- [Handlers and dependency injection](#handlers-and-dependency-injection)
- [URL generation](#url-generation)
- [Route matching and priority](#route-matching-and-priority)
- [Prepared and compiled routes](#prepared-and-compiled-routes)
- [Optional profiling and observability](#optional-profiling-and-observability)
- [Error handling](#error-handling)
- [API reference](#api-reference)
- [Related packages](#related-packages)
- [Development](#development)
- [Contributing and license](#contributing-and-license)

## Overview

`wilycode/router` handles routing and nothing more: it matches an incoming PSR-7 request to a route and invokes the route's handler through PSR-15 middleware.

```text
PSR-7 ServerRequestInterface
             |
             v
      Router::dispatch()
             |
       route matching
             |
     middleware pipeline
             |
       route handler
             |
             v
PSR-7 ResponseInterface
```

The router does **not** create HTTP request/response objects, emit responses, render templates, or include a dependency injection container implementation. Your application supplies those components.

Features include named routes, path parameters and optional segments, configurable matching rules, collection prefixes, route-level and collection-level middleware, URL generation, route indexes, and optional compiled route manifests.

## Requirements

- **PHP 8.4+**.
- A **PSR-11** container implementation.
- A **PSR-7** implementation providing request and response objects.
- The required **PSR-15** contracts (installed through Composer).

This package requires the PSR interfaces, not a particular container or HTTP message implementation.

## Installation

The package is **published on [Packagist](https://packagist.org/packages/wilycode/router)**. Install it with Composer:

```bash
composer require wilycode/router
```

For available versions, see [Packagist](https://packagist.org/packages/wilycode/router) or [GitHub Releases](https://github.com/wilycode/router/releases).

## Quick start

The example assumes you already have `$container` (`Psr\Container\ContainerInterface`), `$request` (`Psr\Http\Message\ServerRequestInterface`), and `$response` (`Psr\Http\Message\ResponseInterface`) from your application's PSR implementations.

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use WilyCode\Router\RouteCollection;
use WilyCode\Router\Router;
use WilyCode\Router\Rule\IntRule;

$routes = new RouteCollection();

$routes->get(
    name: 'home',
    path: '/',
    handler: static fn (): ResponseInterface => $response,
);

$routes->get(
    name: 'articles.show',
    path: '/articles/{id}',
    handler: static function (int $id) use ($response): ResponseInterface {
        // IntRule validates and casts {id} to an integer.
        return $response;
    },
    rules: ['id' => IntRule::class],
);

$router = new Router($container);
$router->addCollection($routes);

$result = $router->dispatch($request); // ResponseInterface
$path = $router->generatePath('articles.show', ['id' => 42]); // /articles/42
```

A route handler **must return `ResponseInterface`**. Creating and emitting a response remains the application's responsibility.

## Defining routes

### HTTP methods

A `RouteCollection` exposes the following helpers:

| Method | HTTP method |
| --- | --- |
| `get()` | GET |
| `post()` | POST |
| `put()` | PUT |
| `patch()` | PATCH |
| `delete()` | DELETE |
| `head()` | HEAD |
| `options()` | OPTIONS |
| `any()` | Registers a route for every HTTP method in `HttpMethod` |
| `add()` | Registers an explicit `HttpMethod` enum case |

```php
$routes->get('users.index', '/users', [UserController::class, 'index']);
$routes->post('users.store', '/users', [UserController::class, 'store']);
$routes->put('users.update', '/users/{id}', [UserController::class, 'update']);
$routes->delete('users.delete', '/users/{id}', [UserController::class, 'delete']);
```

`UserController` is an application-provided class. Route paths must begin with `/`.

### Named routes

Names identify routes for URL generation. For example, a route named `users.show` with path `/users/{id}` can produce `/users/15`.

```php
$routes->get(
    name: 'users.show',
    path: '/users/{id}',
    handler: [UserController::class, 'show'],
);

$router->generatePath('users.show', ['id' => 15]); // /users/15
```

Route names must not be empty. Registering the same route name for different paths is rejected during preparation.

### Route parameters and rules

Use `{name}` placeholders in route paths. Assign optional rule classes or rule objects using the `rules` argument:

```php
use WilyCode\Router\Rule\IntRule;
use WilyCode\Router\Rule\BoolRule;

$routes->get(
    name: 'articles.show',
    path: '/articles/{id}',
    handler: [ArticleController::class, 'show'],
    rules: ['id' => IntRule::class],
);

$routes->get(
    name: 'features.show',
    path: '/features/{enabled}',
    handler: [FeatureController::class, 'show'],
    rules: ['enabled' => BoolRule::class],
);
```

| Rule | Accepted path values | PHP value delivered to the handler |
| --- | --- | --- |
| `StringRule` | Nonempty text without `/` (default) | `string` |
| `IntRule` | Nonnegative integers up to `PHP_INT_MAX`, e.g. `15` | `int` |
| `FloatRule` | Finite decimal values, e.g. `-1.25`, `3`, `0.5` | `float` |
| `BoolRule` | `true`, `false` (case-insensitive), `1`, `0` | `bool` |
| `RegexRule` | A custom regular expression for one path segment | `string` |
| `UuidRule` | Hexadecimal UUID in `8-4-4-4-12` format | Lowercase `string` |
| `SlugRule` | Lowercase ASCII slug, e.g. `hello-world` | `string` |
| `EnumRule` | One of the configured string values | `string` |
| `DateRule` | Real `YYYY-MM-DD` calendar date | `DateTimeImmutable` (UTC, midnight) |

`IntRule` keeps its nonnegative-only syntax and rejects overflowing values instead of truncating them. `FloatRule` now accepts negative numbers but rejects infinity and scientific notation. `BoolRule` uses the same case-insensitive rules for both matching and conversion. The other rules do not perform implicit format normalization except for `UuidRule` (lowercase output).

A custom rule must implement `RuleInterface`, including `getPattern()`, `validate()`, `cast()`, and `allowsPathSeparator()`.

### Custom regular expressions

Use `RegexRule` when none of the built-in formats fits your path parameter:

```php
use WilyCode\Router\Rule\RegexRule;

$routes->get(
    name: 'codes.show',
    path: '/codes/{code}',
    handler: [CodeController::class, 'show'],
    rules: ['code' => new RegexRule('[A-Z]{2}[0-9]{3}')],
);
```

| Path | Matches? |
| --- | --- |
| `/codes/PL123` | Yes |
| `/codes/DE008` | Yes |
| `/codes/pl123` | No |
| `/codes/PL123/extra` | No |

Pass the **pattern without delimiters**. The rule must match the entire value, and `/` is not allowed: it always represents one URL path segment. Patterns are validated when the rule object is created. Avoid named capturing groups inside a route regex, as the Router already creates a named capture for each parameter. Use `(?:...)` for grouping. Patterns must be compatible with the Router's `#` regex delimiter, so a literal `#` must be escaped as `\#`. Treat patterns as trusted application code; avoid pathological backtracking expressions.

### UUID, slugs, enumerated values, and dates

```php
use WilyCode\Router\Rule\UuidRule;
use WilyCode\Router\Rule\SlugRule;
use WilyCode\Router\Rule\EnumRule;
use WilyCode\Router\Rule\DateRule;

$routes->get('api.resource', '/api/resources/{id}', [ApiController::class, 'show'],
    rules: ['id' => UuidRule::class]);

$routes->get('blog.article', '/blog/{slug}', [BlogController::class, 'show'],
    rules: ['slug' => SlugRule::class]);

$routes->get('posts.byStatus', '/posts/status/{status}', [PostController::class, 'list'],
    rules: ['status' => new EnumRule(['draft', 'published', 'archived'])]);

$routes->get('calendar.day', '/calendar/{date}', [CalendarController::class, 'show'],
    rules: ['date' => DateRule::class]);
```

`UuidRule` checks the hexadecimal UUID shape, not a particular UUID version, and normalizes matched values to lowercase. `SlugRule` accepts lowercase ASCII letters, digits, and interior single hyphens (`my-article-2026`); uppercase letters, underscores, consecutive hyphens, and surrounding hyphens are rejected. `EnumRule` is case-sensitive, accepts a nonempty list of URL-safe strings, and requires an **instance** because it has constructor arguments. `DateRule` checks calendar validity (including leap years) and passes a `DateTimeImmutable` representing midnight UTC to your handler.

### Rule validation when generating URLs

The same parameter rules now apply to both **matching incoming routes** and **generating URLs**:

```php
$router->generatePath('blog.article', ['slug' => 'hello-world']);
// /blog/hello-world

$router->generatePath('posts.byStatus', ['status' => 'published']);
// /posts/status/published

$router->generatePath('calendar.day', ['date' => new DateTimeImmutable('2026-10-13')]);
// /calendar/2026-10-13

$router->generatePath('blog.article', ['slug' => 'Bad Slug']);
// Throws InvalidArgumentException.
```

The generator accepts string path values, PHP integers/floats when permitted by the rule, PHP booleans with `BoolRule`, and `DateTimeInterface` objects with `DateRule`. Missing required values still raise `InvalidArgumentException` from `Router::generatePath()`; invalid **provided** values also raise `InvalidArgumentException`. Invalid values inside optional segments are **not silently omitted**. Values are URL-encoded before insertion and checked against the corresponding rule after encoding.

**Compiled routes:** `UuidRule`, `SlugRule`, and `DateRule` can be referenced by class name. `EnumRule` and `RegexRule` are configured objects; they support `var_export()`/`__set_state()` when included in an exported compiled route manifest.

### Optional path sections and defaults

Square brackets mark optional sections:

```php
$routes->get(
    name: 'articles.index',
    path: '/articles[/{page}]',
    handler: [ArticleController::class, 'index'],
    defaults: ['page' => 1],
    rules: ['page' => IntRule::class],
);
```

Both `/articles` and `/articles/2` match. The default supplies `page = 1` when the optional segment is omitted.

A generated path includes the optional segment when its parameter is supplied or has a default. To generate `/articles` without a page segment, use an optional route without a `page` default. Required path parameters must be provided when generating URLs.

## Route collections and prefixes

Apply a prefix to every route in a collection:

```php
$admin = (new RouteCollection())
    ->prefix('/admin')
    ->middleware(AuthenticateMiddleware::class);

$admin->get(
    name: 'articles.edit',
    path: '/articles/{id}/edit',
    handler: [ArticleController::class, 'edit'],
    rules: ['id' => IntRule::class],
);

$router->addCollection($admin);
```

The full route path becomes `/admin/articles/{id}/edit`. `AuthenticateMiddleware` and `ArticleController` are defined by your application.

## Middleware

Register middleware at the collection level or on an individual route:

```php
$api = (new RouteCollection())
    ->prefix('/api')
    ->middleware(AuthenticationMiddleware::class);

$api->get(
    name: 'api.profile',
    path: '/profile',
    handler: [ProfileController::class, 'show'],
    middlewares: [AuditMiddleware::class],
);
```

Middleware registered on the collection runs before middleware registered for its route. Middleware classes are resolved through the application's container or instantiated if possible. Middleware should implement PSR-15 `MiddlewareInterface`; compatible callables are also supported by the middleware pipeline.

## Handlers and dependency injection

Handlers may be closures, invokable objects, callable functions, or controller class/method definitions:

```php
$routes->get(
    name: 'articles.show',
    path: '/articles/{id}',
    handler: [ArticleController::class, 'show'],
    rules: ['id' => IntRule::class],
);
```

For a controller method such as `show(int $id)`, the matched route parameter is injected by name. The router also resolves `ServerRequestInterface`, `RequestHandlerInterface`, `ContainerInterface`, and supported services from the container. Handler parameters with default or nullable values can use those fallbacks.

The router is not a dependency injection container; supply your application's PSR-11 container to `new Router($container)`.

## URL generation

The router can generate relative paths from route names:

```php
$path = $router->generatePath('articles.edit', ['id' => 42]);
// /admin/articles/42/edit
```

Use `UrlGenerator` to append a query string and fragment:

```php
use WilyCode\Router\UrlGenerator;

$urls = new UrlGenerator($router);

$url = $urls->generate(
    'articles.edit',
    ['id' => 42],
    ['tab' => 'seo', 'lang' => 'en'],
    'details',
);
// /admin/articles/42/edit?tab=seo&lang=en#details
```

Route parameters, query parameters, and fragments are URL-encoded. The generator creates paths, not absolute URLs with scheme and host. Unknown names or missing required parameters raise `InvalidArgumentException`.

## Route matching and priority

`Router::dispatch()` selects a route using the HTTP method and request URI path. Routes are prepared on first use:

- Fixed paths can use direct lookup.
- Dynamic paths use a segment-based index and are finally verified against their rules.
- Routes with rules that may consume `/` use a fallback lookup.
- Where candidates overlap, a higher `priority` is considered first; registration order breaks ties for dynamic candidates.

A route can set priority using the last argument of a collection method:

```php
$routes->get(
    name: 'articles.special',
    path: '/articles/{slug}',
    handler: [ArticleController::class, 'special'],
    priority: 10,
);
```

## Prepared and compiled routes

The router builds its index automatically when needed; no manual compilation is required for a typical application.

For immutable deployments, `CompiledRoutesBuilder` can create a route manifest ahead of time:

```php
use WilyCode\Router\CompiledRoutes;
use WilyCode\Router\CompiledRoutesBuilder;

$manifest = (new CompiledRoutesBuilder())->build([$routes]);

$compiledRouter = new Router($container);
$compiledRouter->setCompiledRoutes(new CompiledRoutes($manifest));
```

The manifest is suitable for deployment storage (for example as a PHP array) when its contents are serializable. Prefer class/method handlers to closures for an exported manifest. **Do not mix `setCompiledRoutes()` and `addCollection()` on the same router**, and regenerate the manifest when route definitions change.

## Optional profiling and observability

Router has an optional observability hook that does not require a profiling library:

```php
use WilyCode\Router\Observability\RoutingObserverInterface;

$observer = new class implements RoutingObserverInterface {
    public function around(string $phase, callable $callback): mixed
    {
        // Measure or record the phase if needed.
        return $callback();
    }
};

$router = new Router($container, $observer);
```

Observable phases include `router.prepare`, `router.match`, `router.middleware`, and controller-related phases. An observer **must call the callback** to preserve routing behavior.

For reusable integration packages, see [router-profiler](https://github.com/wilycode/router-profiler).

## Error handling

When a request cannot be matched, `dispatch()` throws `NotFoundException`. Turn it into an HTTP 404 response at your application's boundary:

```php
use WilyCode\Router\NotFoundException;

try {
    $response = $router->dispatch($request);
} catch (NotFoundException $exception) {
    // Create and return your application's PSR-7 404 response.
}
```

This package does not automatically emit a 404 response, and a mismatched method can also result in `NotFoundException`. Invalid route definitions, names, or missing URL-generation parameters may raise `InvalidArgumentException`. Handler and middleware failures may propagate as exceptions.

## API reference

| Class | Main methods / responsibility |
| --- | --- |
| `RouteCollection` | `get()`, `post()`, `put()`, `patch()`, `delete()`, `head()`, `options()`, `any()`, `add()`, `prefix()`, `middleware()` |
| `Router` | `addCollection()`, `dispatch()`, `generatePath()`, `getCurrentRoute()`, `setCompiledRoutes()` |
| `UrlGenerator` | `generate($routeName, $params = [], $query = [], $fragment = null)` |
| `Route` | Route matching, parameter casting, path generation, metadata |
| `CompiledRoutesBuilder` | `build()` a route manifest |
| `CompiledRoutes` | Look up routes from a compiled manifest |
| `RoutingObserverInterface` | Optional `around($phase, $callback)` instrumentation |

For full signatures and edge cases, consult the [source](https://github.com/wilycode/router/tree/main/src) and [tests](https://github.com/wilycode/router/tree/main/tests).

## Related packages

These packages are **optional**. They are not dependencies of `wilycode/router`.

| Package | Purpose |
| --- | --- |
| [wilycode/router-attribute](https://github.com/wilycode/router-attribute) | Declare routes using PHP attributes. |
| [wilycode/router-profiler](https://github.com/wilycode/router-profiler) | Record route matching and controller diagnostics. |

Install only the integration you need:

```bash
composer require wilycode/router-attribute
# or
composer require wilycode/router-profiler
```

See the corresponding packages for setup instructions.

## Development

```bash
composer install
composer validate --strict
composer test
```

Tests run with PHPUnit 11 on PHP 8.4 in [GitHub Actions](https://github.com/wilycode/router/actions). Tests live in the Git repository but are omitted from distribution archives.

## Contributing and license

Issues and pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) and [GitHub Issues](https://github.com/wilycode/router/issues).

MIT © 2026 Simon Piter. See [LICENSE](LICENSE).
